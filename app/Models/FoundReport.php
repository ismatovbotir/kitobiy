<?php

namespace App\Models;

use App\Enums\LibraryRole;
use App\Notifications\BookFoundReported;
use Database\Factories\FoundReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * A message from someone who found a book, sent from its public page. It goes
 * straight to the people who run the library; a super admin is not involved.
 * The finder's contact is optional and is only shown to those people.
 */
#[Fillable(['copy_id', 'body', 'finder_contact', 'read_at'])]
class FoundReport extends Model
{
    /** @use HasFactory<FoundReportFactory> */
    use HasFactory;

    /** How many messages one visitor may send in an hour. */
    public const int MAX_PER_VISITOR_PER_HOUR = 5;

    /** How many messages one book may receive in an hour, to limit spam. */
    public const int MAX_PER_BOOK_PER_HOUR = 10;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Copy, $this>
     */
    public function copy(): BelongsTo
    {
        return $this->belongsTo(Copy::class);
    }

    /**
     * Messages about books in the libraries this user runs as owner or admin.
     *
     * @param  Builder<FoundReport>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $libraryIds = $user->libraries()
            ->wherePivotIn('role', [LibraryRole::Owner->value, LibraryRole::Admin->value])
            ->pluck('libraries.id');

        $query->whereHas('copy', fn (Builder $copy) => $copy->whereIn('library_id', $libraryIds));
    }

    /**
     * Save a finder's message and email the owners, but only for the first unread
     * one about this book. The email holds neither the text nor the finder's
     * contact, and a mail failure never loses the message.
     */
    public static function send(Copy $copy, string $body, ?string $contact): self
    {
        $isFirstUnread = ! $copy->foundReports()->whereNull('read_at')->exists();

        $report = $copy->foundReports()->create(['body' => $body, 'finder_contact' => $contact]);

        if ($isFirstUnread) {
            try {
                Notification::send($copy->library->owners, new BookFoundReported($report));
            } catch (TransportExceptionInterface $exception) {
                report($exception);
            }
        }

        return $report;
    }
}
