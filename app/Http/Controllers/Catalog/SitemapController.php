<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Genre;
use App\Models\Publisher;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * The pages search engines may index: the landing and about pages, the catalog,
 * active genres, and the books, people and publishers that are indexable.
 * Cached for an hour; one file is enough while the catalog is below 50,000 pages.
 */
class SitemapController extends Controller
{
    public const int MAX_URLS = 50_000;

    public function __invoke(): Response
    {
        $xml = Cache::remember('catalog:sitemap', now()->addHour(), fn (): string => view('catalog.sitemap', [
            'urls' => $this->urls(),
        ])->render());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    private function urls(): array
    {
        $urls = [
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('about'), 'lastmod' => null],
            ['loc' => route('catalog.index'), 'lastmod' => null],
        ];

        foreach (Genre::query()->active()->ordered()->get() as $genre) {
            $urls[] = ['loc' => route('catalog.genres.show', $genre), 'lastmod' => null];
        }

        foreach ([Publisher::class, Author::class] as $model) {
            $model::query()->publiclyListed()->where('is_verified', true)->orderBy('id')
                ->each(function (Publisher|Author $record) use (&$urls): void {
                    $urls[] = ['loc' => $record->publicUrl(), 'lastmod' => $record->updated_at?->toDateString()];
                });
        }

        Edition::query()->indexable()
            ->orderBy('id')
            ->limit(self::MAX_URLS)
            ->each(function (Edition $edition) use (&$urls): void {
                $urls[] = ['loc' => $edition->publicUrl(), 'lastmod' => $edition->updated_at?->toDateString()];
            });

        return array_slice($urls, 0, self::MAX_URLS);
    }
}
