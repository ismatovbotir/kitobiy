<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Notifications\Concerns\AlsoSendsToTelegram;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone a message is waiting. The message text stays inside Kitobiy.
 */
class NewMessageReceived extends Notification
{
    use AlsoSendsToTelegram;

    public function __construct(public Conversation $conversation) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New message on Kitobiy'))
            ->line($this->summary())
            ->action(__('Read the message'), route('messages.show', $this->conversation));
    }

    public function toTelegram(object $notifiable): string
    {
        return $this->summary()."\n".route('messages.show', $this->conversation);
    }

    private function summary(): string
    {
        return __('You have a new message about “:title”.', ['title' => $this->conversation->listing->title]);
    }
}
