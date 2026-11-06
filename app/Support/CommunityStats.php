<?php

namespace App\Support;

use App\Enums\LibraryRole;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Totals for the public landing page. Only counts leave this class, never a
 * person or a library, and small numbers stay hidden so nobody can be picked out.
 */
class CommunityStats
{
    /** Below this the page invites the first readers instead of showing small totals. */
    public const int MIN_READERS_TO_SHOW = 25;

    /** A book or a region is named only when at least this many readers share it. */
    public const int MIN_READERS_PER_ROW = 5;

    /** A list shorter than this looks empty, so it stays hidden. */
    public const int MIN_ROWS_IN_LIST = 3;

    public const int ROWS_IN_LIST = 5;

    public const int CACHE_MINUTES = 60;

    /**
     * @return array{
     *     isReady: bool,
     *     readers: int,
     *     libraries: int,
     *     books: int,
     *     editions: int,
     *     mostShelved: list<array{title: string, authors: string, readers: int}>,
     *     regions: list<array{code: string, readers: int}>
     * }
     */
    public static function current(): array
    {
        return Cache::remember('community-stats', now()->addMinutes(self::CACHE_MINUTES), function (): array {
            $readers = self::countedReaders()->count();

            return [
                'isReady' => $readers >= self::MIN_READERS_TO_SHOW,
                'readers' => $readers,
                'libraries' => Copy::query()->distinct()->count('library_id'),
                'books' => Copy::query()->count(),
                'editions' => Copy::query()->distinct()->count('edition_id'),
                'mostShelved' => self::listOrNothing(self::mostShelvedBooks()),
                'regions' => self::listOrNothing(self::regionsOfUzbekistan()),
            ];
        });
    }

    /**
     * @return Builder<User>
     */
    private static function countedReaders(): Builder
    {
        return User::query()->whereNotNull('email_verified_at')->whereNull('suspended_at');
    }

    /**
     * Counts different owners, not libraries, because one person may open several
     * libraries. Otherwise anyone could put any title on the public page alone.
     * Books hidden from the gift page are left out.
     *
     * @return list<array{title: string, authors: string, readers: int}>
     */
    private static function mostShelvedBooks(): array
    {
        $rows = Copy::query()
            ->join('library_user', 'library_user.library_id', '=', 'copies.library_id')
            ->join('users', 'users.id', '=', 'library_user.user_id')
            ->where('library_user.role', LibraryRole::Owner->value)
            ->where('copies.hidden_from_gift_page', false)
            ->whereNotNull('users.email_verified_at')
            ->whereNull('users.suspended_at')
            ->groupBy('copies.edition_id')
            ->havingRaw('count(distinct library_user.user_id) >= ?', [self::MIN_READERS_PER_ROW])
            ->orderByDesc('readers')
            ->orderBy('copies.edition_id')
            ->limit(self::ROWS_IN_LIST)
            ->selectRaw('copies.edition_id, count(distinct library_user.user_id) as readers')
            ->toBase()
            ->get();

        $editions = Edition::query()->with('authors')->findMany($rows->pluck('edition_id'))->keyBy('id');

        return $rows->map(fn (object $row): array => [
            'title' => $editions[$row->edition_id]->title,
            'authors' => $editions[$row->edition_id]->authors->pluck('name')->join(', '),
            'readers' => (int) $row->readers,
        ])->all();
    }

    /**
     * @return list<array{code: string, readers: int}>
     */
    private static function regionsOfUzbekistan(): array
    {
        return self::countedReaders()
            ->where('country_code', Country::UZBEKISTAN)
            ->whereIn('region', array_keys(Country::UZBEK_REGIONS))
            ->groupBy('region')
            ->havingRaw('count(*) >= ?', [self::MIN_READERS_PER_ROW])
            ->orderByDesc('readers')
            ->orderBy('region')
            ->limit(self::ROWS_IN_LIST)
            ->selectRaw('region, count(*) as readers')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => ['code' => $row->region, 'readers' => (int) $row->readers])
            ->all();
    }

    /**
     * @template TRow
     *
     * @param  list<TRow>  $rows
     * @return list<TRow>
     */
    private static function listOrNothing(array $rows): array
    {
        return count($rows) >= self::MIN_ROWS_IN_LIST ? $rows : [];
    }
}
