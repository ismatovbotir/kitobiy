<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\TelegramChannel;

trait AlsoSendsToTelegram
{
    /**
     * Telegram goes first: if email fails, the Telegram message has already gone.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->telegram_chat_id !== null ? [TelegramChannel::class, 'mail'] : ['mail'];
    }
}
