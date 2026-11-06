<?php

namespace App\Notifications;

use App\Models\FoundReport;
use App\Notifications\Concerns\AlsoSendsToTelegram;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an owner that someone wrote about their book. The text and the finder's
 * contact stay inside Kitobiy.
 */
class BookFoundReported extends Notification
{
    use AlsoSendsToTelegram;

    public function __construct(public FoundReport $report) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Someone wrote about your book'))
            ->line($this->summary())
            ->action(__('Read the message'), route('library.found'));
    }

    public function toTelegram(object $notifiable): string
    {
        return $this->summary()."\n".route('library.found');
    }

    private function summary(): string
    {
        return __('Someone sent a message about “:title”, maybe they found it.', ['title' => $this->report->copy->edition->title]);
    }
}
