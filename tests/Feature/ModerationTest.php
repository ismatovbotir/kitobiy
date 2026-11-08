<?php

namespace Tests\Feature;

use App\Enums\ListingKind;
use App\Enums\ReportOutcome;
use App\Enums\ReportReason;
use App\Models\CollectorProfile;
use App\Models\Conversation;
use App\Models\Copy;
use App\Models\Library;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $seller;

    private User $buyer;

    private Listing $listing;

    private Conversation $conversation;

    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->admin = User::factory()->superAdmin()->create();
        $this->seller = User::factory()->create();
        CollectorProfile::factory()->for($this->seller)->create(['display_name' => 'Kitobxon']);
        $this->buyer = User::factory()->create();
        CollectorProfile::factory()->for($this->buyer)->create(['display_name' => 'Xaridor']);

        $library = Library::factory()->ownedBy($this->seller)->create();
        $this->listing = Copy::factory()->for($library)->create()->createListing($this->seller, ListingKind::Swap, null, 'Listing note');
        $this->conversation = $this->listing->conversations()->create(['buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);
        $this->conversation->send($this->buyer, 'First private message');
        $this->message = $this->conversation->send($this->buyer, 'Rude message');
    }

    private function reportOf(Listing|Message $reportable, ?User $reporter = null): Report
    {
        return Report::factory()->create([
            'reporter_id' => ($reporter ?? User::factory()->create())->id,
            'reportable_type' => $reportable->getMorphClass(),
            'reportable_id' => $reportable->getKey(),
        ]);
    }

    public function test_visitor_reports_a_listing(): void
    {
        Livewire::actingAs($this->buyer)
            ->test('report-form', ['type' => 'listing', 'reportableId' => $this->listing->id])
            ->set('reason', ReportReason::Scam->value)
            ->set('details', 'Asks for money first')
            ->call('send')
            ->assertSet('sent', true);

        $report = Report::query()->sole();
        $this->assertTrue($report->reportable->is($this->listing));
        $this->assertSame(ReportReason::Scam, $report->reason);
    }

    public function test_reporting_twice_keeps_one_report(): void
    {
        foreach ([1, 2] as $attempt) {
            Livewire::actingAs($this->buyer)
                ->test('report-form', ['type' => 'listing', 'reportableId' => $this->listing->id])
                ->set('reason', ReportReason::Spam->value)
                ->call('send');
        }

        $this->assertSame(1, Report::query()->count());
    }

    private function report(Listing|Message $reportable, User $reporter): void
    {
        Livewire::actingAs($reporter)
            ->test('report-form', ['type' => $reportable instanceof Message ? 'message' : 'listing', 'reportableId' => $reportable->id])
            ->set('reason', ReportReason::Abuse->value)
            ->set('details', 'Secret details')
            ->call('send')
            ->assertSet('sent', true);
    }

    /**
     * @return list<string>
     */
    private function adminTelegramTexts(): array
    {
        return Http::recorded(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/sendMessage') && $request['chat_id'] === 777000111)
            ->map(fn (array $pair): string => (string) $pair[0]['text'])
            ->values()
            ->all();
    }

    public function test_the_first_report_about_content_tells_the_admin_in_telegram_without_the_text(): void
    {
        $this->withTelegramBot();
        config(['services.telegram.admin_id' => '777000111']);

        $this->report($this->message, $this->seller);
        $this->report($this->listing, $this->buyer);
        $this->report($this->listing, User::factory()->create());

        $texts = $this->adminTelegramTexts();

        $this->assertCount(2, $texts);
        $this->assertStringContainsString(route('admin.reports.index'), $texts[0]);
        $this->assertStringContainsString(ReportReason::Abuse->label(), $texts[0]);
        foreach ($texts as $text) {
            $this->assertStringNotContainsString('Rude message', $text);
            $this->assertStringNotContainsString('Secret details', $text);
            $this->assertStringNotContainsString('Xaridor', $text);
        }

        Report::query()->where('reportable_type', 'listing')->first()->resolve($this->admin, ReportOutcome::Dismissed);
        $this->report($this->listing, User::factory()->create());

        $this->assertCount(3, $this->adminTelegramTexts());
    }

    public function test_the_admin_notice_uses_the_admins_language(): void
    {
        $this->withTelegramBot();
        config(['services.telegram.admin_id' => '777000111']);
        $this->admin->forceFill(['telegram_chat_id' => 777000111, 'locale' => 'ru'])->save();

        $this->report($this->listing, $this->buyer);

        $this->assertStringContainsString('Новая жалоба', $this->adminTelegramTexts()[0]);
    }

    public function test_without_an_admin_id_no_telegram_is_sent(): void
    {
        $this->withTelegramBot();

        $this->report($this->listing, $this->buyer);

        Http::assertNotSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/sendMessage'));
    }

    public function test_a_reason_is_required(): void
    {
        Livewire::actingAs($this->buyer)
            ->test('report-form', ['type' => 'listing', 'reportableId' => $this->listing->id])
            ->call('send')
            ->assertHasErrors('reason');
    }

    public function test_receiver_reports_a_message_but_nobody_else_can(): void
    {
        Livewire::actingAs($this->seller)
            ->test('report-form', ['type' => 'message', 'reportableId' => $this->message->id])
            ->set('reason', ReportReason::Abuse->value)
            ->call('send')
            ->assertSet('sent', true);

        Livewire::actingAs(User::factory()->create())
            ->test('report-form', ['type' => 'message', 'reportableId' => $this->message->id])
            ->set('reason', ReportReason::Abuse->value)
            ->call('send')
            ->assertForbidden();

        Livewire::actingAs($this->buyer)
            ->test('report-form', ['type' => 'message', 'reportableId' => $this->message->id])
            ->set('reason', ReportReason::Abuse->value)
            ->call('send')
            ->assertForbidden();

        $this->assertSame(1, Report::query()->count());
    }

    public function test_too_many_reports_are_refused(): void
    {
        $library = Library::factory()->ownedBy($this->seller)->create();

        for ($sent = 0; $sent <= Report::MAX_PER_HOUR; $sent++) {
            $listing = Copy::factory()->for($library)->create()->createListing($this->seller, ListingKind::Swap, null, null);

            $page = Livewire::actingAs($this->buyer)
                ->test('report-form', ['type' => 'listing', 'reportableId' => $listing->id])
                ->set('reason', ReportReason::Spam->value)
                ->call('send');
        }

        $page->assertHasErrors('reason');
        $this->assertSame(Report::MAX_PER_HOUR, Report::query()->count());
    }

    public function test_only_super_admin_opens_the_reports_page(): void
    {
        $this->actingAs($this->seller)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertOk();

        Livewire::actingAs($this->seller)->test('pages::admin.reports.index')->assertForbidden();
    }

    public function test_moderator_sees_only_the_reported_message_not_the_whole_chat(): void
    {
        $this->reportOf($this->message, $this->seller);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.reports.index')
            ->assertSee('Rude message')
            ->assertDontSee('First private message');
    }

    public function test_dismiss_closes_every_open_report_about_the_same_content(): void
    {
        $first = $this->reportOf($this->listing);
        $second = $this->reportOf($this->listing);

        Livewire::actingAs($this->admin)->test('pages::admin.reports.index')->call('dismiss', $first->id);

        $this->assertSame(ReportOutcome::Dismissed, $second->refresh()->outcome);
        $this->assertSame($this->admin->id, $second->resolved_by);
        $this->assertTrue($this->listing->fresh()->isVisible());
    }

    public function test_hiding_a_listing_removes_it_from_the_market(): void
    {
        $report = $this->reportOf($this->listing);

        Livewire::actingAs($this->admin)->test('pages::admin.reports.index')->call('hideContent', $report->id);

        $this->assertFalse($this->listing->fresh()->isVisible());
        $this->assertSame(ReportOutcome::ContentHidden, $report->refresh()->outcome);

        Livewire::actingAs($this->seller)
            ->test('pages::market.show', ['listing' => $this->listing->fresh()])
            ->assertSee(__('A moderator has hidden this listing.'));
    }

    public function test_hiding_a_message_removes_its_text_from_the_chat(): void
    {
        $report = $this->reportOf($this->message, $this->seller);

        Livewire::actingAs($this->admin)->test('pages::admin.reports.index')->call('hideContent', $report->id);

        Livewire::actingAs($this->seller)
            ->test('pages::messages.show', ['conversation' => $this->conversation])
            ->assertDontSee('Rude message')
            ->assertSee(__('Hidden by a moderator.'));
    }

    public function test_suspending_removes_the_market_and_messages_but_keeps_the_library(): void
    {
        $report = $this->reportOf($this->listing);

        Livewire::actingAs($this->admin)->test('pages::admin.reports.index')->call('suspendAuthor', $report->id);

        $seller = $this->seller->fresh();
        $this->assertTrue($seller->isSuspended());
        $this->assertFalse($this->listing->fresh()->isVisible());
        $this->assertFalse($seller->can('reply', $this->conversation));
        $this->assertFalse($seller->can('createListing', $this->listing->copy));
        $this->assertTrue($seller->can('update', $this->listing->copy));
        $this->assertSame(ReportOutcome::UserSuspended, $report->refresh()->outcome);
    }

    public function test_restoring_brings_the_listing_back(): void
    {
        $this->seller->suspend();
        $this->assertFalse($this->listing->fresh()->isVisible());

        Livewire::actingAs($this->admin)->test('pages::admin.reports.index')->call('restoreUser', $this->seller->id);

        $this->assertTrue($this->listing->fresh()->isVisible());
    }

    public function test_super_admin_cannot_be_suspended(): void
    {
        $this->admin->collectorProfile()->create(['display_name' => 'Admin']);
        $library = Library::factory()->ownedBy($this->admin)->create();
        $listing = Copy::factory()->for($library)->create()->createListing($this->admin, ListingKind::Swap, null, null);
        $report = $this->reportOf($listing);

        Livewire::actingAs($this->admin)->test('pages::admin.reports.index')->call('suspendAuthor', $report->id);

        $this->assertFalse($this->admin->fresh()->isSuspended());
        $this->assertNull($report->refresh()->resolved_at);
    }

    public function test_normal_user_cannot_use_the_moderation_actions(): void
    {
        $report = $this->reportOf($this->listing);

        Livewire::actingAs($this->buyer)->test('pages::admin.reports.index')->assertForbidden();

        $this->assertFalse($this->seller->fresh()->isSuspended());
        $this->assertNull($report->refresh()->resolved_at);
    }
}
