<?php

namespace App\Models;

use App\Models\Concerns\HasSiteText;
use Database\Factories\BookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A book event on the public site (fair, author meeting, presentation), added
 * by a super admin. Called BookEvent so it is never mixed up with Laravel events.
 */
#[Fillable([
    'title_uz', 'title_ru', 'body_uz', 'body_ru', 'starts_at', 'ends_at', 'place', 'city',
    'online_url', 'info_url', 'is_published', 'publisher_id', 'author_id', 'edition_id', 'created_by',
])]
class BookEvent extends Model
{
    /** @use HasFactory<BookEventFactory> */
    use HasFactory, HasSiteText;

    public const int PER_PAGE = 12;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  Builder<BookEvent>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Not over yet: it ends (or, without an end, starts) now or later.
     *
     * @param  Builder<BookEvent>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->where('ends_at', '>=', now())
            ->orWhere(fn (Builder $noEnd) => $noEnd->whereNull('ends_at')->where('starts_at', '>=', self::startOfToday())));
    }

    /**
     * @param  Builder<BookEvent>  $query
     */
    #[Scope]
    protected function past(Builder $query): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->where('ends_at', '<', now())
            ->orWhere(fn (Builder $noEnd) => $noEnd->whereNull('ends_at')->where('starts_at', '<', self::startOfToday())));
    }

    /**
     * Midnight in the site's time zone, so an event without an end time counts
     * as upcoming for the whole of its day.
     */
    private static function startOfToday(): Carbon
    {
        return now(config('app.timezone'))->startOfDay();
    }

    public function isOver(): bool
    {
        return $this->ends_at !== null
            ? $this->ends_at->isPast()
            : $this->starts_at->lt(self::startOfToday());
    }

    /**
     * Date and time in the site's time zone, e.g. "12 noyabr 2026, 15:00 – 17:00"
     * or "12 – 14 noyabr 2026" for several days.
     */
    public function dateLabel(): string
    {
        $zone = config('app.timezone');
        $start = $this->starts_at->timezone($zone);
        $end = $this->ends_at?->timezone($zone);

        if ($end === null) {
            return $start->translatedFormat('j F Y, H:i');
        }

        return $start->isSameDay($end)
            ? $start->translatedFormat('j F Y, H:i').' – '.$end->format('H:i')
            : $start->translatedFormat('j F Y').' – '.$end->translatedFormat('j F Y');
    }

    /**
     * The venue and city, or "Online".
     */
    public function placeLabel(): string
    {
        $place = collect([$this->place, $this->city])->filter()->join(', ');

        return match (true) {
            $place !== '' && $this->online_url !== null => $place.' · '.__('and online'),
            $place !== '' => $place,
            default => __('Online'),
        };
    }

    public function startsAtLocal(): Carbon
    {
        return $this->starts_at->timezone(config('app.timezone'));
    }

    public function publicUrl(): string
    {
        return route('events.show', $this);
    }
}
