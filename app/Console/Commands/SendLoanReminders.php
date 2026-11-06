<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\User;
use App\Notifications\LoanOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendLoanReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'kitobiy:send-loan-reminders';

    /**
     * @var string
     */
    protected $description = 'Send one reminder for each loan whose return date has passed';

    public function handle(): int
    {
        $sent = 0;

        Loan::query()
            ->active()
            ->whereNull('reminded_at')
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', now(Loan::TIMEZONE)->toDateString())
            ->with(['copy.edition', 'copy.library.owners', 'lentBy', 'borrowerLibrary.owners'])
            ->chunkById(100, function (Collection $loans) use (&$sent): void {
                foreach ($loans as $loan) {
                    $this->remind($loan);
                    $sent++;
                }
            });

        $this->info("Reminders sent for {$sent} loans.");

        return self::SUCCESS;
    }

    /**
     * Marked first: a mail failure must not make everyone get the reminder again
     * tomorrow, or every day until mail works.
     */
    private function remind(Loan $loan): void
    {
        $loan->forceFill(['reminded_at' => now()])->save();

        $lenders = $loan->lentBy && $loan->copy->library->hasMember($loan->lentBy)
            ? collect([$loan->lentBy])
            : $loan->copy->library->owners;

        foreach ($lenders as $lender) {
            $this->notifyOne($lender, new LoanOverdue($loan, forBorrower: false));
        }

        foreach ($loan->borrowerLibrary?->owners ?? [] as $owner) {
            $this->notifyOne($owner, new LoanOverdue($loan, forBorrower: true));
        }
    }

    private function notifyOne(User $user, LoanOverdue $notification): void
    {
        try {
            $user->notify($notification);
        } catch (TransportExceptionInterface $exception) {
            report($exception);
        }
    }
}
