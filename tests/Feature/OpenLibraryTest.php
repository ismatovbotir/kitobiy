<?php

namespace Tests\Feature;

use App\Services\OpenLibrary;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenLibraryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_maps_a_found_book(): void
    {
        Http::fake(['openlibrary.org/api/books*' => Http::response([
            'ISBN:9780140449136' => [
                'key' => '/books/OL23140636M',
                'title' => 'Crime and punishment',
                'authors' => [['name' => 'Fyodor Dostoyevsky'], ['name' => 'David McDuff']],
                'publishers' => [['name' => 'Penguin']],
                'publish_date' => 'March 2003',
                'number_of_pages' => 671,
                'cover' => ['medium' => 'https://covers.openlibrary.org/b/id/1-M.jpg', 'large' => 'https://covers.openlibrary.org/b/id/1-L.jpg'],
            ],
        ])]);

        $book = app(OpenLibrary::class)->findByIsbn('9780140449136');

        $this->assertSame([
            'title' => 'Crime and punishment',
            'subtitle' => null,
            'authors' => ['Fyodor Dostoyevsky', 'David McDuff'],
            'publisher' => 'Penguin',
            'published_year' => 2003,
            'page_count' => 671,
            'cover_url' => 'https://covers.openlibrary.org/b/id/1-L.jpg',
            'source_id' => 'OL23140636M',
        ], $book);

        Http::assertSent(fn (Request $request): bool => $request['bibkeys'] === 'ISBN:9780140449136'
            && $request['jscmd'] === 'data'
            && str_contains($request->header('User-Agent')[0], config('app.name')));
    }

    public function test_unknown_isbn_returns_null_and_is_cached(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response([])]);

        $this->assertNull(app(OpenLibrary::class)->findByIsbn('9780306406157'));
        $this->assertNull(app(OpenLibrary::class)->findByIsbn('9780306406157'));

        Http::assertSentCount(1);
    }

    public function test_found_book_is_cached(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response(['ISBN:9780306406157' => ['title' => 'Cached']])]);

        app(OpenLibrary::class)->findByIsbn('9780306406157');
        $book = app(OpenLibrary::class)->findByIsbn('9780306406157');

        $this->assertSame('Cached', $book['title']);
        Http::assertSentCount(1);
    }

    public function test_server_error_returns_null_without_caching(): void
    {
        Http::fake(['openlibrary.org/*' => Http::sequence()
            ->push('oops', 503)
            ->push(['ISBN:9780306406157' => ['title' => 'Back up']])]);

        $this->assertNull(app(OpenLibrary::class)->findByIsbn('9780306406157'));
        $this->assertSame('Back up', app(OpenLibrary::class)->findByIsbn('9780306406157')['title']);
    }

    public function test_connection_failure_returns_null(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertNull(app(OpenLibrary::class)->findByIsbn('9780306406157'));
    }
}
