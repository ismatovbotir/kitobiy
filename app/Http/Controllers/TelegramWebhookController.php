<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Telegram;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Traits\Localizable;

class TelegramWebhookController extends Controller
{
    use Localizable;

    /**
     * Messages sent to the bot. Telegram proves it is Telegram with the secret it
     * was given when the webhook was set. Only private chats count, and a 200 is
     * always returned for them, or Telegram would keep resending the message.
     */
    public function __invoke(Request $request, Telegram $telegram): Response
    {
        abort_unless($telegram->hasToken() && hash_equals($telegram->webhookSecret(), (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $chatId = $request->integer('message.chat.id');
        $text = trim((string) $request->input('message.text'));

        if ($chatId === 0 || $request->input('message.chat.type') !== 'private') {
            return response()->noContent();
        }

        $user = User::query()->where('telegram_chat_id', $chatId)->first();

        $reply = match (true) {
            str_starts_with($text, '/start ') => $this->connect(trim(substr($text, 7)), $chatId),
            $text === '/stop' => $this->disconnect($user),
            default => $this->withLocaleOf($user, fn (): string => $user
                ? __('Your Telegram is connected to Kitobiy. Send /stop to disconnect it.')
                : __('To connect, open your profile on Kitobiy and press “Connect Telegram”.')),
        };

        $telegram->sendMessage($chatId, $reply);

        return response()->noContent();
    }

    private function connect(string $token, int $chatId): string
    {
        $user = User::connectTelegramByToken($token, $chatId);

        return $this->withLocaleOf($user, fn (): string => match (true) {
            $user === null => __('This link has expired. Go back to Kitobiy and get a new link.'),
            $user->wasChanged('confirmed_at') => __('The Kitobiy account of :name is confirmed. You can go back to the site. If this is not you, send /stop.', ['name' => $user->name]),
            default => __('Connected to the Kitobiy account of :name. If this is not you, send /stop.', ['name' => $user->name]),
        });
    }

    private function disconnect(?User $user): string
    {
        $user?->disconnectTelegram();

        return $this->withLocaleOf($user, fn (): string => __('Disconnected. You will no longer get messages here.'));
    }

    private function withLocaleOf(?User $user, callable $callback): string
    {
        return $this->withLocale($user?->preferredLocale() ?? App::getLocale(), $callback);
    }
}
