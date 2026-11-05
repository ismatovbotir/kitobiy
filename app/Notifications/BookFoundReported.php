<?php

namespace App\Notifications;

use App\Models\FoundReport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an owner that someone wrote about their book. The text and the finder's
 * contact stay inside Kitobiy.
 */
class BookFoundReported extends Notification
{
    public function __construct(public FoundReport $report) {}

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
            ->subject(__('Someone wrote about your book'))
            ->line(__('Someone sent a message about “:title”, maybe they found it.', ['title' => $this->report->copy->edition->title]))
            ->action(__('Read the message'), route('library.found'));
    }
}
