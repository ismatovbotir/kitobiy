<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Telegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTelegramTest extends TestCase
{
    use RefreshDatabase;

    private const string BOT_TOKEN = '123456:test-bot-token';

    private const int ADMIN_ID = 777000111;

    private string $webhookUrl = '';

    private bool $telegramRefuses = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.token' => self::BOT_TOKEN, 'services.telegram.admin_id' => (string) self::ADMIN_ID]);
        URL::forceRootUrl('https://kitobiy.uz');
        URL::forceScheme('https');

        Http::fake(function (ClientRequest $request) {
            if ($this->telegramRefuses) {
                return Http::response(['ok' => false, 'description' => 'Forbidden'], 403);
            }

            return match (true) {
                str_ends_with($request->url(), '/getMe') => Http::response(['ok' => true, 'result' => ['username' => 'KitobiyBot']]),
                str_ends_with($request->url(), '/getWebhookInfo') => Http::response(['ok' => true, 'result' => ['url' => $this->webhookUrl, 'pending_update_count' => 0]]),
                default => Http::response(['ok' => true, 'result' => true]),
            };
        });
    }

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_only_super_admins_open_the_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.telegram'))->assertForbidden();

        $this->actingAs($this->admin())->get(route('admin.telegram'))
            ->assertOk()
            ->assertSee('@KitobiyBot')
            ->assertSee(__('Not set'))
            ->assertDontSee(self::BOT_TOKEN);
    }

    public function test_set_webhook_points_the_bot_at_this_site_with_a_secret_from_app_key(): void
    {
        Livewire::actingAs($this->admin())->test('pages::admin.telegram')
            ->call('setWebhook')
            ->assertHasNoErrors();

        $secret = app(Telegram::class)->webhookSecret();

        $this->assertSame(hash_hmac('sha256', 'telegram-webhook', (string) config('app.key')), $secret);
        Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/setWebhook')
            && $request['url'] === 'https://kitobiy.uz/telegram/webhook'
            && $request['secret_token'] === $secret);

        $this->postJson(route('telegram.webhook'), ['message' => ['text' => 'hi', 'chat' => ['id' => 1, 'type' => 'private']]], ['X-Telegram-Bot-Api-Secret-Token' => $secret])
            ->assertNoContent();
        $this->postJson(route('telegram.webhook'), [], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])->assertForbidden();
    }

    public function test_page_shows_when_the_webhook_points_here(): void
    {
        $this->webhookUrl = 'https://kitobiy.uz/telegram/webhook';

        Livewire::actingAs($this->admin())->test('pages::admin.telegram')->assertSee(__('Set to this site'));
    }

    public function test_set_webhook_needs_https_and_reports_a_refusal(): void
    {
        URL::forceRootUrl('http://localhost');
        URL::forceScheme('http');
        $page = Livewire::actingAs($this->admin())->test('pages::admin.telegram');

        $page->call('setWebhook')->assertHasErrors('webhook');
        Http::assertNotSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/setWebhook'));

        URL::forceRootUrl('https://kitobiy.uz');
        URL::forceScheme('https');
        $this->telegramRefuses = true;

        $page->call('setWebhook')->assertHasErrors('webhook');
    }

    public function test_test_message_goes_to_the_admin_id(): void
    {
        Livewire::actingAs($this->admin())->test('pages::admin.telegram')
            ->call('sendTestMessage')
            ->assertHasNoErrors();

        Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/sendMessage') && $request['chat_id'] === self::ADMIN_ID);
    }

    public function test_test_message_needs_an_admin_id_and_reports_a_refusal(): void
    {
        $page = Livewire::actingAs($this->admin())->test('pages::admin.telegram');

        $this->telegramRefuses = true;
        $page->call('sendTestMessage')->assertHasErrors('test');

        config(['services.telegram.admin_id' => null]);
        $page->call('sendTestMessage')->assertHasErrors('test');
    }

    public function test_bot_username_is_asked_once_and_a_failure_is_not_retried_at_once(): void
    {
        $telegram = app(Telegram::class);

        $this->assertSame('KitobiyBot', $telegram->botUsername());
        $this->assertSame('KitobiyBot', $telegram->botUsername());
        Http::assertSentCount(1);

        config(['services.telegram.token' => '999:another-bot']);
        $this->telegramRefuses = true;

        $this->assertNull($telegram->botUsername());
        $this->assertNull($telegram->botUsername());
        $this->assertFalse($telegram->isConfigured());
        Http::assertSentCount(2);
    }

    public function test_username_from_env_wins_and_needs_no_request(): void
    {
        config(['services.telegram.bot_username' => '@OwnBot']);

        $this->assertSame('OwnBot', app(Telegram::class)->botUsername());
        Http::assertNothingSent();
    }
}
