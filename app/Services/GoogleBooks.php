<?php

namespace App\Services;

use App\Support\Language;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Looks up book data by ISBN on Google Books (volumes API), the second source
 * after Open Library. Works without an API key, but a key (GOOGLE_BOOKS_KEY)
 * gives a quota of our own instead of the shared per-IP one.
 * Network or API failures return null, so the user can still add the book by hand.
 */
class GoogleBooks
{
    /**
     * @return array{title: string, subtitle: string|null, authors: list<string>, publisher: string|null, published_year: int|null, page_count: int|null, cover_url: string|null, source_id: string|null, language: string|null}|null
     */
    public function findByIsbn(string $isbn13): ?array
    {
        $cacheKey = "google-books:isbn:{$isbn13}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        try {
            $response = Http::baseUrl(config('services.google_books.url'))
                ->timeout(config('services.google_books.timeout'))
                ->withUserAgent(config('app.name').' ('.config('app.url').')')
                ->acceptJson()
                ->get('/books/v1/volumes', array_filter([
                    'q' => "isbn:{$isbn13}",
                    'maxResults' => 5,
                    'key' => config('services.google_books.key'),
                ]));
        } catch (ConnectionException $exception) {
            Log::warning('Google Books lookup failed', ['isbn' => $isbn13, 'error' => $exception->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Google Books lookup failed', ['isbn' => $isbn13, 'status' => $response->status()]);

            return null;
        }

        $volume = collect($response->json('items') ?? [])
            ->first(fn (array $item): bool => $this->hasIsbn($item, $isbn13));

        $book = $volume !== null ? $this->mapVolume($volume) : null;

        Cache::put($cacheKey, $book ?? false, $book === null ? now()->addHour() : now()->addWeek());

        return $book;
    }

    /**
     * Google's isbn: search is loose, so keep only a volume that really lists
     * this ISBN (as ISBN-13, or the matching ISBN-10).
     *
     * @param  array<string, mixed>  $item
     */
    private function hasIsbn(array $item, string $isbn13): bool
    {
        $isbn10 = str_starts_with($isbn13, '978') ? substr($isbn13, 3, 9) : null;

        foreach ($item['volumeInfo']['industryIdentifiers'] ?? [] as $identifier) {
            $value = preg_replace('/[^0-9Xx]/', '', (string) ($identifier['identifier'] ?? ''));

            if ($value === $isbn13 || ($isbn10 !== null && strlen($value) === 10 && str_starts_with($value, $isbn10))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $volume
     * @return array{title: string, subtitle: string|null, authors: list<string>, publisher: string|null, published_year: int|null, page_count: int|null, cover_url: string|null, source_id: string|null, language: string|null}|null
     */
    private function mapVolume(array $volume): ?array
    {
        $info = $volume['volumeInfo'] ?? [];
        $title = trim((string) ($info['title'] ?? ''));

        if ($title === '') {
            return null;
        }

        preg_match('/^(\d{4})/', (string) ($info['publishedDate'] ?? ''), $year);
        $language = isset($info['language']) ? strtolower((string) $info['language']) : null;
        $pageCount = (int) ($info['pageCount'] ?? 0);

        return [
            'title' => mb_substr($title, 0, 255),
            'subtitle' => isset($info['subtitle']) ? mb_substr(trim((string) $info['subtitle']), 0, 255) : null,
            'authors' => collect($info['authors'] ?? [])
                ->map(fn (mixed $name): string => trim((string) $name))
                ->filter()
                ->values()
                ->all(),
            'publisher' => isset($info['publisher']) ? mb_substr(trim((string) $info['publisher']), 0, 255) : null,
            'published_year' => isset($year[1]) ? (int) $year[1] : null,
            'page_count' => $pageCount > 0 ? $pageCount : null,
            'cover_url' => $this->coverUrl($info['imageLinks'] ?? []),
            'source_id' => isset($volume['id']) ? (string) $volume['id'] : null,
            'language' => Language::isValid($language) ? $language : null,
        ];
    }

    /**
     * The biggest thumbnail, over HTTPS and without the curled-page effect.
     *
     * @param  array<string, string>  $imageLinks
     */
    private function coverUrl(array $imageLinks): ?string
    {
        $url = $imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? null;

        if ($url === null) {
            return null;
        }

        return str_replace(['http://', '&edge=curl'], ['https://', ''], $url);
    }
}
