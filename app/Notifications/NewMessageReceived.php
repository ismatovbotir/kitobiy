<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone a message is waiting. The message text stays inside Kitobiy.
 */
class NewMessageReceived extends Notification
{
    public function __construct(public Conversation $conversation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New message on Kitobiy'))
            ->line(__('You have a new message about “:title”.', ['title' => $this->conversation->listing->title]))
            ->action(__('Read the message'), route('messages.show', $this->conversation));
    }
}
