<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the Telegram Bot API. The bot token is part of every API address, so
 * failures are logged without the request URL: the token must never reach a log.
 *
 * Only the token (and the admin's chat id) live in .env. The bot's username is
 * asked from Telegram and cached; the webhook secret is derived from APP_KEY.
 */
class Telegram
{
    /** How old a Login Widget answer may be, so a captured link stops working soon. */
    public const int LOGIN_MAX_AGE_SECONDS = 300;

    /** After Telegram could not tell the username, wait this long before asking again. */
    public const int USERNAME_RETRY_SECONDS = 600;

    /**
     * The bot works once there is a token and Telegram has told us the bot's username.
     */
    public function isConfigured(): bool
    {
        return $this->hasToken() && $this->botUsername() !== null;
    }

    public function hasToken(): bool
    {
        return filled(config('services.telegram.token'));
    }

    /**
     * The username without @: TELEGRAM_BOT_USERNAME when set, else asked from
     * Telegram once and kept. A failed answer is not asked again for 10 minutes,
     * so pages never keep waiting on an unreachable Telegram.
     */
    public function botUsername(): ?string
    {
        $configured = config('services.telegram.bot_username');

        if (filled($configured)) {
            return ltrim((string) $configured, '@');
        }

        if (! $this->hasToken()) {
            return null;
        }

        $key = $this->cacheKey('bot-username');
        $cached = Cache::get($key);

        if (is_string($cached)) {
            return $cached;
        }

        if (Cache::has($key.':failed')) {
            return null;
        }

        return $this->refreshBotUsername();
    }

    /**
     * Ask Telegram who the bot is and keep the answer.
     */
    public function refreshBotUsername(): ?string
    {
        $username = $this->call('getMe', [])?->json('result.username');

        if (! is_string($username) || $username === '') {
            Cache::put($this->cacheKey('bot-username').':failed', true, self::USERNAME_RETRY_SECONDS);

            return null;
        }

        Cache::forever($this->cacheKey('bot-username'), $username);
        Cache::forget($this->cacheKey('bot-username').':failed');

        return $username;
    }

    /**
     * The Telegram user id of the site's admin (TELEGRAM_ADMIN_ID), for test and admin messages.
     */
    public function adminChatId(): ?int
    {
        $id = config('services.telegram.admin_id');

        return filled($id) && is_numeric($id) ? (int) $id : null;
    }

    /**
     * A short message to the site's admin (TELEGRAM_ADMIN_ID). Does nothing without
     * a token or an admin id; never throws, so the caller's work always goes on.
     */
    public function notifyAdmin(string $text): void
    {
        $chatId = $this->adminChatId();

        if ($chatId !== null && $this->hasToken()) {
            $this->sendMessage($chatId, $text);
        }
    }

    /**
     * Telegram sends this secret with every update. TELEGRAM_WEBHOOK_SECRET when set,
     * else derived from APP_KEY (so a new APP_KEY needs the webhook set again).
     */
    public function webhookSecret(): string
    {
        $configured = config('services.telegram.webhook_secret');

        return filled($configured) ? (string) $configured : hash_hmac('sha256', 'telegram-webhook', (string) config('app.key'));
    }

    public function webhookUrl(): string
    {
        return route('telegram.webhook');
    }

    /**
     * Why the webhook can't be set from here, or null when it can.
     */
    public function webhookProblem(): ?string
    {
        if (! $this->hasToken()) {
            return __('Set TELEGRAM_BOT_TOKEN in the .env file first.');
        }

        if (! str_starts_with($this->webhookUrl(), 'https://')) {
            return __('Telegram needs an https address, but this site’s address is :url.', ['url' => $this->webhookUrl()]);
        }

        return null;
    }

    /**
     * Point the bot at this site and refresh the cached username. Returns false when Telegram refused.
     */
    public function registerWebhook(): bool
    {
        if ($this->webhookProblem() !== null) {
            return false;
        }

        if ($this->setWebhook($this->webhookUrl(), $this->webhookSecret())?->json('ok') !== true) {
            return false;
        }

        $this->refreshBotUsername();

        return true;
    }

    /**
     * What Telegram knows about the webhook (url, pending_update_count, last_error_message…), or null.
     *
     * @return array<string, mixed>|null
     */
    public function webhookInfo(): ?array
    {
        if (! $this->hasToken()) {
            return null;
        }

        $result = $this->call('getWebhookInfo', [])?->json('result');

        return is_array($result) ? $result : null;
    }

    /**
     * Returns the response, or null when Telegram could not be reached. Callers
     * never depend on Telegram working: email and the app's own pages remain.
     */
    public function sendMessage(int $chatId, string $text): ?Response
    {
        return $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'link_preview_options' => ['is_disabled' => true],
        ]);
    }

    public function setWebhook(string $url, string $secret): ?Response
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
        ]);
    }

    /**
     * Checks an answer from Telegram's Login Widget: it is signed with a key made
     * from the bot token, so nobody else can produce it.
     *
     * @param  array<string, mixed>  $data
     */
    public function isValidLogin(array $data): bool
    {
        $token = config('services.telegram.token');
        $hash = $data['hash'] ?? null;

        if (blank($token) || ! is_string($hash) || ! isset($data['auth_date'], $data['id'])) {
            return false;
        }

        unset($data['hash']);
        ksort($data);

        $checkString = collect($data)
            ->map(fn (mixed $value, string $key): string => $key.'='.(is_scalar($value) ? $value : ''))
            ->implode("\n");

        $expected = hash_hmac('sha256', $checkString, hash('sha256', $token, true));

        if (! hash_equals($expected, $hash)) {
            return false;
        }

        $age = now()->timestamp - (int) $data['auth_date'];

        return $age >= -60 && $age <= self::LOGIN_MAX_AGE_SECONDS;
    }

    private function cacheKey(string $name): string
    {
        return 'telegram:'.$name.':'.substr(hash('sha256', (string) config('services.telegram.token')), 0, 16);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function call(string $method, array $payload): ?Response
    {
        $base = rtrim((string) config('services.telegram.url'), '/');

        try {
            $response = Http::timeout((int) config('services.telegram.timeout'))
                ->acceptJson()
                ->post($base.'/bot'.config('services.telegram.token').'/'.$method, $payload);
        } catch (ConnectionException) {
            Log::warning('Telegram could not be reached.', ['method' => $method]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Telegram refused a request.', ['method' => $method, 'status' => $response->status()]);
        }

        return $response;
    }
}
