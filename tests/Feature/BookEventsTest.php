<?php

namespace Tests\Feature;

use App\Enums\EditionSource;
use App\Models\BookEvent;
use App\Models\Edition;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookEventsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_readers_see_upcoming_and_past_events_but_not_drafts(): void
    {
        $upcoming = BookEvent::factory()->create(['title_uz' => 'Kitob yarmarkasi']);
        $today = BookEvent::factory()->create(['title_uz' => 'Bugungi uchrashuv', 'starts_at' => now()->startOfDay()->addMinute(), 'ends_at' => null]);
        $past = BookEvent::factory()->past()->create(['title_uz' => 'O‘tgan taqdimot']);
        $draft = BookEvent::factory()->draft()->create(['title_uz' => 'Qoralama tadbir']);

        $this->get(route('events.index'))
            ->assertOk()
            ->assertSeeInOrder([__('Upcoming'), 'Bugungi uchrashuv', 'Kitob yarmarkasi', __('Past events'), 'O‘tgan taqdimot'])
            ->assertDontSee('Qoralama tadbir');

        $this->get($upcoming->publicUrl())->assertOk()->assertSee('"@type":"Event"', false)->assertSee('Toshkent')->assertDontSee(__('This event is over.'));
        $this->get($past->publicUrl())->assertOk()->assertSee(__('This event is over.'));
        $this->get($draft->publicUrl())->assertNotFound();
        $this->actingAs($this->admin())->get($draft->publicUrl())->assertOk()->assertSee(__('Draft: only super admins see this page.'));

        $this->get(route('sitemap'))->assertSee($upcoming->publicUrl())->assertSee($past->publicUrl())->assertDontSee($draft->publicUrl());
        $this->assertTrue($today->fresh()->is_published);
    }

    public function test_an_online_event_is_marked_as_online(): void
    {
        $event = BookEvent::factory()->online()->create();

        $this->assertSame(__('Online'), $event->placeLabel());
        $this->get($event->publicUrl())->assertSee('OnlineEventAttendanceMode')->assertSee('example.com');
    }

    public function test_super_admins_add_and_publish_an_event_shown_on_related_pages(): void
    {
        $admin = $this->admin();
        $publisher = Publisher::factory()->create(['name' => 'Sharq']);
        Edition::factory()->for($publisher)->create(['isbn_13' => '9789943000001', 'source' => EditionSource::Publisher]);

        $this->actingAs(User::factory()->create())->get(route('admin.events.create'))->assertForbidden();

        $start = now()->addDays(3)->timezone(config('app.timezone'))->setTime(15, 0);

        Livewire::actingAs($admin)->test('pages::admin.events.edit')
            ->set('titleUz', 'Sharq kitoblari taqdimoti')
            ->set('bodyUz', 'Yangi kitoblar bilan tanishing.')
            ->set('startsAt', $start->format('Y-m-d\TH:i'))
            ->set('endsAt', $start->copy()->addHours(2)->format('Y-m-d\TH:i'))
            ->set('place', 'Milliy kutubxona')
            ->set('city', 'Toshkent')
            ->set('infoUrl', 'https://sharq.uz/event')
            ->set('publisherName', 'Sharq')
            ->call('publish')
            ->assertHasNoErrors();

        $event = BookEvent::query()->sole();
        $this->assertTrue($event->is_published);
        $this->assertSame('sharq-kitoblari-taqdimoti', $event->slug);
        $this->assertTrue($event->starts_at->eq($start));
        $this->assertTrue($event->publisher->is($publisher));

        auth()->logout();
        $this->get($event->publicUrl())->assertOk()->assertSee(__('Details and registration'))->assertSee($publisher->publicUrl(), false);
        $this->get($publisher->publicUrl())->assertSee(__('Upcoming events'))->assertSee('Sharq kitoblari taqdimoti');

        $this->travel(4)->days();
        $this->get($publisher->publicUrl())->assertDontSee('Sharq kitoblari taqdimoti');
        $this->get($event->publicUrl())->assertDontSee(__('Details and registration'));
    }

    public function test_the_event_form_checks_its_fields(): void
    {
        Livewire::actingAs($this->admin())->test('pages::admin.events.edit')
            ->set('titleUz', 'Tadbir')
            ->set('bodyUz', 'Matn')
            ->set('startsAt', '2026-12-01T15:00')
            ->set('endsAt', '2026-12-01T14:00')
            ->set('onlineUrl', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors(['endsAt', 'onlineUrl']);

        Livewire::actingAs($this->admin())->test('pages::admin.events.edit')
            ->set('titleUz', 'Tadbir')
            ->set('bodyUz', 'Matn')
            ->set('startsAt', '2026-12-01T15:00')
            ->call('save')
            ->assertHasErrors(['place']);

        $this->assertSame(0, BookEvent::count());
    }

    public function test_a_published_events_address_never_changes(): void
    {
        $event = BookEvent::factory()->create(['slug' => 'kitob-kuni']);

        Livewire::actingAs($this->admin())->test('pages::admin.events.edit', ['bookEvent' => $event])
            ->assertSet('place', $event->place)
            ->set('titleUz', 'Yangi nom')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('kitob-kuni', $event->fresh()->slug);
        $this->assertSame('Yangi nom', $event->fresh()->title_uz);
    }

    public function test_a_super_admin_deletes_an_event(): void
    {
        $event = BookEvent::factory()->create();

        Livewire::actingAs($this->admin())->test('pages::admin.events.edit', ['bookEvent' => $event])
            ->call('delete')
            ->assertRedirect(route('admin.events.index'));

        $this->assertSame(0, BookEvent::count());
    }
}
