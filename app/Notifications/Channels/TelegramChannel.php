<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Telegram;
use Illuminate\Notifications\Notification;

/**
 * Sends a notification's toTelegram() text. If the person has blocked the bot,
 * Telegram answers 403 and the connection is removed.
 */
class TelegramChannel
{
    public function __construct(private Telegram $telegram) {}

    public function send(User $notifiable, Notification $notification): void
    {
        if ($notifiable->telegram_chat_id === null || ! $this->telegram->isConfigured()) {
            return;
        }

        $response = $this->telegram->sendMessage($notifiable->telegram_chat_id, $notification->toTelegram($notifiable));

        if ($response?->status() === 403) {
            $notifiable->disconnectTelegram();
        }
    }
}
