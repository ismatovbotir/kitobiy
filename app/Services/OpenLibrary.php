<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Looks up book data by ISBN on Open Library (Books API, jscmd=data).
 * Network or API failures return null, so the user can still add the book by hand.
 */
class OpenLibrary
{
    /**
     * @return array{title: string, subtitle: string|null, authors: list<string>, publisher: string|null, published_year: int|null, page_count: int|null, cover_url: string|null, source_id: string|null}|null
     */
    public function findByIsbn(string $isbn13): ?array
    {
        $cacheKey = "open-library:isbn:{$isbn13}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        try {
            $response = Http::baseUrl(config('services.open_library.url'))
                ->timeout(config('services.open_library.timeout'))
                ->withUserAgent(config('app.name').' ('.config('app.url').')')
                ->acceptJson()
                ->get('/api/books', [
                    'bibkeys' => "ISBN:{$isbn13}",
                    'format' => 'json',
                    'jscmd' => 'data',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Open Library lookup failed', ['isbn' => $isbn13, 'error' => $exception->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Open Library lookup failed', ['isbn' => $isbn13, 'status' => $response->status()]);

            return null;
        }

        $book = $this->mapBook($response->json("ISBN:{$isbn13}"));

        Cache::put($cacheKey, $book ?? false, $book === null ? now()->addHour() : now()->addWeek());

        return $book;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array{title: string, subtitle: string|null, authors: list<string>, publisher: string|null, published_year: int|null, page_count: int|null, cover_url: string|null, source_id: string|null}|null
     */
    private function mapBook(?array $data): ?array
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            return null;
        }

        preg_match('/\b(\d{4})\b/', (string) ($data['publish_date'] ?? ''), $year);

        return [
            'title' => mb_substr($title, 0, 255),
            'subtitle' => isset($data['subtitle']) ? mb_substr(trim((string) $data['subtitle']), 0, 255) : null,
            'authors' => collect($data['authors'] ?? [])
                ->map(fn (array $author): string => trim((string) ($author['name'] ?? '')))
                ->filter()
                ->values()
                ->all(),
            'publisher' => isset($data['publishers'][0]['name']) ? mb_substr(trim($data['publishers'][0]['name']), 0, 255) : null,
            'published_year' => isset($year[1]) ? (int) $year[1] : null,
            'page_count' => isset($data['number_of_pages']) ? (int) $data['number_of_pages'] : null,
            'cover_url' => $data['cover']['large'] ?? $data['cover']['medium'] ?? null,
            'source_id' => isset($data['key']) ? basename((string) $data['key']) : null,
        ];
    }
}
