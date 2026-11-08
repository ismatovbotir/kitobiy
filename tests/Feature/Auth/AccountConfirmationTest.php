<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AccountConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private const int CHAT_ID = 555000222;

    private function register(): User
    {
        Livewire::test('pages::auth.register')
            ->set('name', 'Aziz')
            ->set('email', 'aziz@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasNoErrors();

        return User::firstWhere('email', 'aziz@example.com');
    }

    private function pressStart(string $link, int $chatId = self::CHAT_ID): void
    {
        $token = substr($link, strpos($link, '=') + 1);

        $this->postJson(
            route('telegram.webhook'),
            ['message' => ['text' => '/start '.$token, 'chat' => ['id' => $chatId, 'type' => 'private']]],
            ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'],
        )->assertNoContent();
    }

    public function test_sign_up_sends_no_email_and_leads_to_the_confirmation_page(): void
    {
        $this->withTelegramBot();
        Mail::fake();
        Notification::fake();

        $user = $this->register();

        $this->assertFalse($user->isConfirmed());
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();

        $this->get(route('dashboard'))->assertRedirect(route('confirmation.notice'));
    }

    public function test_unconfirmed_users_wait_on_the_confirmation_page(): void
    {
        $this->withTelegramBot();
        $user = User::factory()->unconfirmed()->superAdmin()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('confirmation.notice'));
        $this->actingAs($user)->get(route('books.index'))->assertRedirect(route('confirmation.notice'));
        $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('confirmation.notice'));
        $this->actingAs($user)->get(route('confirmation.notice'))->assertOk()->assertSee('https://t.me/KitobiyTestBot?start=', false);
    }

    public function test_without_a_bot_nobody_has_to_confirm(): void
    {
        $user = User::factory()->unconfirmed()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        Livewire::actingAs($user)->test('pages::auth.confirm')->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_confirmed_users_skip_the_confirmation_page(): void
    {
        $this->withTelegramBot();
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::auth.confirm')->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_pressing_start_in_the_bot_confirms_the_account_and_the_page_moves_on(): void
    {
        $this->withTelegramBot();
        $user = $this->register();
        session()->put('url.intended', route('books.index'));

        $page = Livewire::actingAs($user)->test('pages::auth.confirm');
        $page->call('checkConfirmation')->assertNoRedirect();

        $this->pressStart($page->get('telegramLink'));

        $user->refresh();
        $this->assertTrue($user->isConfirmed());
        $this->assertSame(self::CHAT_ID, $user->telegram_chat_id);
        $reply = __('The Kitobiy account of :name is confirmed. You can go back to the site. If this is not you, send /stop.', ['name' => 'Aziz'], 'uz');
        Http::assertSent(fn (ClientRequest $request): bool => $request['text'] === $reply);

        $page->call('checkConfirmation')->assertRedirect(route('books.index'));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_an_expired_link_does_not_confirm(): void
    {
        $this->withTelegramBot();
        $user = User::factory()->unconfirmed()->create();
        $link = Livewire::actingAs($user)->test('pages::auth.confirm')->get('telegramLink');

        $this->travel(User::TELEGRAM_LINK_MINUTES + 1)->minutes();
        $this->pressStart($link);

        $this->assertFalse($user->fresh()->isConfirmed());
    }

    public function test_disconnecting_telegram_keeps_the_account_confirmed(): void
    {
        $this->withTelegramBot();
        $user = User::factory()->unconfirmed()->create();
        $this->pressStart($user->newTelegramLink('KitobiyTestBot'));

        $user->fresh()->disconnectTelegram();

        $this->assertTrue($user->fresh()->isConfirmed());
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_new_links_are_limited(): void
    {
        $this->withTelegramBot();
        $this->actingAs(User::factory()->unconfirmed()->create());

        $page = Livewire::test('pages::auth.confirm');

        foreach (range(2, 10) as $attempt) {
            $page->call('newLink')->assertHasNoErrors();
        }

        $page->call('newLink')->assertHasErrors('link');
    }
}
