<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the Telegram Bot API. The bot token is part of every API address, so
 * failures are logged without the request URL: the token must never reach a log.
 */
class Telegram
{
    /** How old a Login Widget answer may be, so a captured link stops working soon. */
    public const int LOGIN_MAX_AGE_SECONDS = 300;

    public function isConfigured(): bool
    {
        return filled(config('services.telegram.token')) && filled(config('services.telegram.bot_username'));
    }

    public function botUsername(): ?string
    {
        return config('services.telegram.bot_username');
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
