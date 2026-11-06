<?php

namespace Tests\Feature;

use App\Enums\ListingKind;
use App\Models\Conversation;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\FoundReport;
use App\Models\Library;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\LoanOverdue;
use App\Notifications\NewMessageReceived;
use App\Services\Telegram;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TelegramTest extends TestCase
{
    use RefreshDatabase;

    private const string BOT_TOKEN = '123456:test-bot-token';

    private const string SECRET = 'test-webhook-secret';

    private const int CHAT_ID = 555000111;

    private User $user;

    /** What the fake Telegram answers; null means success. */
    private ?Closure $telegramAnswer = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.token' => self::BOT_TOKEN,
            'services.telegram.bot_username' => 'KitobiyTestBot',
            'services.telegram.webhook_secret' => self::SECRET,
        ]);

        Http::fake(fn () => $this->telegramAnswer !== null ? ($this->telegramAnswer)() : Http::response(['ok' => true]));

        $this->user = User::factory()->create(['name' => 'Karim']);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function webhook(array $message, ?string $secret = self::SECRET): mixed
    {
        return $this->postJson(route('telegram.webhook'), ['message' => $message], array_filter(['X-Telegram-Bot-Api-Secret-Token' => $secret]));
    }

    private function start(string $text, int $chatId = self::CHAT_ID): mixed
    {
        return $this->webhook(['text' => $text, 'chat' => ['id' => $chatId, 'type' => 'private']]);
    }

    private function tokenFromLink(string $link): string
    {
        return substr($link, strpos($link, '=') + 1);
    }

    /**
     * @param  array<string, int|string>  $data
     * @return array<string, int|string>
     */
    private function signed(array $data, string $token = self::BOT_TOKEN): array
    {
        ksort($data);
        $check = collect($data)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
        $data['hash'] = hash_hmac('sha256', $check, hash('sha256', $token, true));

        return $data;
    }

    public function test_profile_page_connects_through_a_one_time_link(): void
    {
        $page = Livewire::actingAs($this->user)->test('pages::profile.edit')->call('connectTelegram');

        $link = $page->get('telegramLink');
        $this->assertStringStartsWith('https://t.me/KitobiyTestBot?start=', $link);
        $this->assertLessThanOrEqual(64, strlen($this->tokenFromLink($link)));

        $this->start('/start '.$this->tokenFromLink($link))->assertSuccessful();

        $this->assertSame(self::CHAT_ID, $this->user->fresh()->telegram_chat_id);
        $this->assertNull($this->user->fresh()->telegram_link_token);
        Http::assertSent(fn (ClientRequest $request): bool => $request['chat_id'] === self::CHAT_ID && str_contains($request['text'], 'Karim'));
    }

    public function test_only_a_hash_of_the_link_token_is_stored(): void
    {
        $token = $this->tokenFromLink($this->user->newTelegramLink('KitobiyTestBot'));

        $this->assertNotSame($token, $this->user->fresh()->telegram_link_token);
    }

    public function test_link_works_once_and_expires(): void
    {
        $token = $this->tokenFromLink($this->user->newTelegramLink('KitobiyTestBot'));
        $this->start("/start {$token}");
        $this->user->disconnectTelegram();

        $this->start("/start {$token}", 777);
        $this->assertNull($this->user->fresh()->telegram_chat_id);

        $token = $this->tokenFromLink($this->user->newTelegramLink('KitobiyTestBot'));
        $this->travel(User::TELEGRAM_LINK_MINUTES + 1)->minutes();
        $this->start("/start {$token}");

        $this->assertNull($this->user->fresh()->telegram_chat_id);
    }

    public function test_one_chat_belongs_to_one_account(): void
    {
        $other = User::factory()->create();
        $this->start('/start '.$this->tokenFromLink($other->newTelegramLink('KitobiyTestBot')));
        $this->start('/start '.$this->tokenFromLink($this->user->newTelegramLink('KitobiyTestBot')));

        $this->assertNull($other->fresh()->telegram_chat_id);
        $this->assertSame(self::CHAT_ID, $this->user->fresh()->telegram_chat_id);
    }

    public function test_stop_and_the_profile_button_disconnect(): void
    {
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();

        $this->start('/stop')->assertSuccessful();
        $this->assertNull($this->user->fresh()->telegram_chat_id);

        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();
        Livewire::actingAs($this->user->fresh())->test('pages::profile.edit')->call('disconnectTelegram');
        $this->assertNull($this->user->fresh()->telegram_chat_id);
    }

    public function test_webhook_refuses_a_wrong_or_missing_secret(): void
    {
        $token = $this->tokenFromLink($this->user->newTelegramLink('KitobiyTestBot'));
        $message = ['text' => "/start {$token}", 'chat' => ['id' => self::CHAT_ID, 'type' => 'private']];

        $this->webhook($message, 'wrong')->assertForbidden();
        $this->webhook($message, null)->assertForbidden();

        config(['services.telegram.webhook_secret' => null]);
        $this->webhook($message, '')->assertForbidden();

        $this->assertNull($this->user->fresh()->telegram_chat_id);
    }

    public function test_group_chats_are_ignored(): void
    {
        $token = $this->tokenFromLink($this->user->newTelegramLink('KitobiyTestBot'));

        $this->webhook(['text' => "/start {$token}", 'chat' => ['id' => -100, 'type' => 'group']])->assertSuccessful();

        $this->assertNull($this->user->fresh()->telegram_chat_id);
        Http::assertNothingSent();
    }

    public function test_notification_goes_to_telegram_and_email_without_the_private_text(): void
    {
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();
        $buyer = User::factory()->create();
        $library = Library::factory()->ownedBy($this->user)->create();
        $listing = Copy::factory()->for($library)->create()->createListing($this->user, ListingKind::Swap, null, null);
        $conversation = $listing->conversations()->create(['buyer_id' => $buyer->id, 'seller_id' => $this->user->id]);

        $conversation->send($buyer, 'Secret question');

        Http::assertSent(fn (ClientRequest $request): bool => $request['chat_id'] === self::CHAT_ID
            && str_contains($request['text'], route('messages.show', $conversation))
            && ! str_contains($request['text'], 'Secret question'));
    }

    public function test_user_without_telegram_gets_only_email(): void
    {
        $notification = new NewMessageReceived(new Conversation);

        $this->assertSame(['mail'], $notification->via($this->user));

        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID]);
        $this->assertSame([TelegramChannel::class, 'mail'], $notification->via($this->user));
    }

    public function test_found_book_message_reaches_the_owner_on_telegram_without_finder_details(): void
    {
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();
        $library = Library::factory()->ownedBy($this->user)->create();
        $copy = Copy::factory()->for($library)->create();

        FoundReport::send($copy, 'On a bench', '+998901234567');

        Http::assertSent(fn (ClientRequest $request): bool => $request['chat_id'] === self::CHAT_ID
            && ! str_contains($request['text'], 'On a bench')
            && ! str_contains($request['text'], '+998901234567'));
    }

    public function test_blocking_the_bot_removes_the_connection(): void
    {
        $this->telegramAnswer = fn () => Http::response(['ok' => false], 403);
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();
        $library = Library::factory()->ownedBy($this->user)->create();
        $copy = Copy::factory()->for($library)->create();

        FoundReport::send($copy, 'Found it', null);

        $this->assertNull($this->user->fresh()->telegram_chat_id);
        $this->assertSame(1, $copy->foundReports()->count());
    }

    public function test_other_telegram_errors_keep_the_connection(): void
    {
        $this->telegramAnswer = fn () => Http::response(['ok' => false], 500);
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();
        $library = Library::factory()->ownedBy($this->user)->create();

        FoundReport::send(Copy::factory()->for($library)->create(), 'Found it', null);

        $this->assertSame(self::CHAT_ID, $this->user->fresh()->telegram_chat_id);
    }

    public function test_telegram_being_down_does_not_lose_the_message(): void
    {
        $this->telegramAnswer = fn () => throw new ConnectionException('cURL error for https://api.telegram.org/bot'.self::BOT_TOKEN.'/sendMessage');
        Log::spy();
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();
        $library = Library::factory()->ownedBy($this->user)->create();
        $copy = Copy::factory()->for($library)->create();

        FoundReport::send($copy, 'Found it', null);

        $this->assertSame(1, $copy->foundReports()->count());
        $this->assertSame(self::CHAT_ID, $this->user->fresh()->telegram_chat_id);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Telegram could not be reached.'
            && ! str_contains($message.json_encode($context), self::BOT_TOKEN));
    }

    public function test_without_settings_telegram_stays_hidden_and_nothing_is_sent(): void
    {
        config(['services.telegram.token' => null, 'services.telegram.bot_username' => null]);
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();
        $library = Library::factory()->ownedBy($this->user)->create();

        FoundReport::send(Copy::factory()->for($library)->create(), 'Found it', null);
        Http::assertNothingSent();

        Livewire::actingAs($this->user)->test('pages::profile.edit')->assertDontSee(__('Connect Telegram'))->call('connectTelegram')->assertNotFound();

        auth()->logout();
        $this->get(route('login'))->assertDontSee('telegram-widget.js');
    }

    public function test_login_page_shows_the_widget_when_configured(): void
    {
        $this->get(route('login'))->assertSee('telegram-widget.js')->assertSee('KitobiyTestBot')->assertDontSee(self::BOT_TOKEN);
    }

    public function test_valid_signed_login_logs_in_the_connected_account(): void
    {
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();

        $this->get(route('telegram.login', $this->signed(['id' => self::CHAT_ID, 'first_name' => 'K', 'auth_date' => now()->timestamp])))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_forged_old_or_unknown_logins_are_refused(): void
    {
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();
        $now = now()->timestamp;

        $attempts = [
            'signed with another token' => $this->signed(['id' => self::CHAT_ID, 'auth_date' => $now], 'other-token'),
            'changed after signing' => [...$this->signed(['id' => 1, 'auth_date' => $now]), 'id' => self::CHAT_ID],
            'too old' => $this->signed(['id' => self::CHAT_ID, 'auth_date' => $now - Telegram::LOGIN_MAX_AGE_SECONDS - 1]),
            'from the future' => $this->signed(['id' => self::CHAT_ID, 'auth_date' => $now + 3600]),
            'no hash' => ['id' => self::CHAT_ID, 'auth_date' => $now],
            'array as hash' => ['id' => self::CHAT_ID, 'auth_date' => $now, 'hash' => ['x']],
            'telegram not connected' => $this->signed(['id' => 999, 'auth_date' => $now]),
        ];

        foreach ($attempts as $name => $query) {
            $this->get(route('telegram.login', $query))->assertRedirect(route('login'));
            $this->assertFalse(auth()->check(), "Logged in by: {$name}");
        }
    }

    public function test_no_token_means_no_login(): void
    {
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();
        $query = $this->signed(['id' => self::CHAT_ID, 'auth_date' => now()->timestamp], '');

        config(['services.telegram.token' => '']);
        $this->get(route('telegram.login', $query))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_chat_id_and_tokens_never_appear_in_json(): void
    {
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID, 'telegram_link_token' => 'x'])->save();

        $this->assertArrayNotHasKey('telegram_chat_id', $this->user->fresh()->toArray());
        $this->assertArrayNotHasKey('telegram_link_token', $this->user->fresh()->toArray());
    }

    public function test_webhook_command_needs_settings_and_https(): void
    {
        config(['services.telegram.webhook_secret' => null]);
        $this->artisan('kitobiy:telegram-webhook')->assertFailed();

        config(['services.telegram.webhook_secret' => self::SECRET, 'app.url' => 'http://localhost']);
        $this->artisan('kitobiy:telegram-webhook')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_overdue_loan_gets_one_reminder_on_both_sides(): void
    {
        Notification::fake();
        $borrower = User::factory()->create();
        $borrowerLibrary = Library::factory()->ownedBy($borrower)->create();
        $library = Library::factory()->ownedBy($this->user)->create();
        $copy = Copy::factory()->for($library)->for(Edition::factory()->create(['title' => 'Kecha']))->create();
        $link = $copy->offerLoan($this->user, null);
        $link->acceptFor($borrower, $borrowerLibrary);
        $link->refresh()->forceFill(['due_on' => now(Loan::TIMEZONE)->subDay()->toDateString()])->save();

        $this->artisan('kitobiy:send-loan-reminders')->assertSuccessful();
        $this->artisan('kitobiy:send-loan-reminders')->assertSuccessful();

        Notification::assertSentToTimes($this->user, LoanOverdue::class, 1);
        Notification::assertSentToTimes($borrower, LoanOverdue::class, 1);
        Notification::assertSentTo($borrower, LoanOverdue::class, fn (LoanOverdue $notification): bool => $notification->forBorrower);
    }

    public function test_reminder_skips_loans_not_yet_due_returned_or_without_a_date(): void
    {
        Notification::fake();
        $library = Library::factory()->ownedBy($this->user)->create();
        $today = now(Loan::TIMEZONE)->toDateString();

        Copy::factory()->for($library)->create()->lendToPerson($this->user, 'A', null, $today);
        Copy::factory()->for($library)->create()->lendToPerson($this->user, 'B', null, null);
        Copy::factory()->for($library)->create()->lendToPerson($this->user, 'C', null, now(Loan::TIMEZONE)->subDay()->toDateString())->markReturned();

        $this->artisan('kitobiy:send-loan-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_person_loan_reminder_names_the_borrower_but_not_their_phone(): void
    {
        $this->user->forceFill(['telegram_chat_id' => self::CHAT_ID])->save();
        $library = Library::factory()->ownedBy($this->user)->create();
        Copy::factory()->for($library)->create()
            ->lendToPerson($this->user, 'Anvar', '+998901234567', now(Loan::TIMEZONE)->subDay()->toDateString());

        $this->artisan('kitobiy:send-loan-reminders')->assertSuccessful();

        Http::assertSent(fn (ClientRequest $request): bool => str_contains($request['text'], 'Anvar') && ! str_contains($request['text'], '+998901234567'));
    }
}
