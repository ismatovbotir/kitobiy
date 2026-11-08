<?php

namespace App\Notifications;

use App\Models\Publisher;
use App\Notifications\Concerns\AlsoSendsToTelegram;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that a super admin approved their claim, so they can fill in
 * the publisher's book list.
 */
class PublisherClaimApproved extends Notification
{
    use AlsoSendsToTelegram;

    public function __construct(public Publisher $publisher) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You can now keep the book list of :publisher', ['publisher' => $this->publisher->name]))
            ->line($this->summary())
            ->action(__('Open the book list'), $this->link());
    }

    public function toTelegram(object $notifiable): string
    {
        return $this->summary()."\n".$this->link();
    }

    private function summary(): string
    {
        return __('Your request was approved. You can now add the books of :publisher to Kitobiy, so readers find them by scanning the barcode.', ['publisher' => $this->publisher->name]);
    }

    private function link(): string
    {
        return route('publisher.books', $this->publisher);
    }
}
