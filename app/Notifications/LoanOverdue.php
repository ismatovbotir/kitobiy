<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Notifications\Concerns\AlsoSendsToTelegram;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One reminder that a book's return date has passed. The lender hears who has it;
 * the borrowing library hears only the book and the date. No phone numbers.
 */
class LoanOverdue extends Notification
{
    use AlsoSendsToTelegram;

    public function __construct(public Loan $loan, public bool $forBorrower) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('A book is overdue'))
            ->line($this->summary())
            ->action(__('Open'), $this->link());
    }

    public function toTelegram(object $notifiable): string
    {
        return $this->summary()."\n".$this->link();
    }

    private function summary(): string
    {
        $replacements = [
            'title' => $this->loan->copy->edition->title,
            'date' => $this->loan->due_on->toDateString(),
            'name' => $this->loan->borrower_name,
        ];

        return $this->forBorrower
            ? __('“:title” was due back on :date. Please return it to its owner.', $replacements)
            : __('“:title”, lent to :name, was due back on :date.', $replacements);
    }

    private function link(): string
    {
        return $this->forBorrower ? route('library.loans') : route('books.show', $this->loan->copy);
    }
}
