<?php

namespace App\Models;

use App\Enums\ReportOutcome;
use App\Enums\ReportReason;
use App\Services\Telegram;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Traits\Localizable;

/**
 * Someone's complaint about a listing or a message, for the super admin to review.
 */
#[Fillable(['reporter_id', 'reportable_type', 'reportable_id', 'reason', 'details'])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, Localizable;

    public const int MAX_PER_HOUR = 10;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'outcome' => ReportOutcome::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Report>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    /**
     * Tell the admin in Telegram, for the first open report about a piece of content
     * only, so one listing reported by many people sends one message. The reported
     * text and the people involved are left out.
     */
    public function notifyAdmin(): void
    {
        $alreadyReported = static::query()
            ->open()
            ->where('reportable_type', $this->reportable_type)
            ->where('reportable_id', $this->reportable_id)
            ->whereKeyNot($this->getKey())
            ->exists();

        if ($alreadyReported) {
            return;
        }

        $telegram = app(Telegram::class);

        if ($telegram->adminChatId() === null || ! $telegram->hasToken()) {
            return;
        }

        // In the admin's language: the account connected to that Telegram, else the site default.
        $locale = User::query()->where('telegram_chat_id', $telegram->adminChatId())->value('locale') ?? config('app.locale');

        $telegram->notifyAdmin($this->withLocale($locale, fn (): string => __('New report on Kitobiy: :what, reason: :reason. Review it: :url', [
            'what' => $this->reportable_type === 'message' ? __('a message') : __('a listing'),
            'reason' => $this->reason->label(),
            'url' => route('admin.reports.index'),
        ])));
    }

    /**
     * The person who wrote the reported listing or message.
     */
    public function author(): ?User
    {
        return match (true) {
            $this->reportable instanceof Listing => $this->reportable->seller,
            $this->reportable instanceof Message => $this->reportable->sender,
            default => null,
        };
    }

    /**
     * Close this report and every other open report about the same content, so
     * one decision never has to be made twice.
     */
    public function resolve(User $admin, ReportOutcome $outcome): void
    {
        static::query()
            ->open()
            ->where('reportable_type', $this->reportable_type)
            ->where('reportable_id', $this->reportable_id)
            ->update([
                'resolved_at' => now(),
                'resolved_by' => $admin->getKey(),
                'outcome' => $outcome->value,
            ]);
    }
}
