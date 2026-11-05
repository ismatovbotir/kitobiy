<?php

namespace Tests\Feature;

use App\Services\GoogleBooks;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBooksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_maps_a_found_volume(): void
    {
        Http::fake(['www.googleapis.com/books/v1/volumes*' => Http::response(['items' => [[
            'id' => 'abc123',
            'volumeInfo' => [
                'title' => 'Crime and Punishment',
                'subtitle' => 'A Novel',
                'authors' => ['Fyodor Dostoyevsky', ' '],
                'publisher' => 'Penguin',
                'publishedDate' => '2003-01-30',
                'pageCount' => 671,
                'language' => 'en',
                'imageLinks' => ['thumbnail' => 'http://books.google.com/books/content?id=abc123&printsec=frontcover&img=1&zoom=1&edge=curl'],
                'industryIdentifiers' => [
                    ['type' => 'ISBN_10', 'identifier' => '0140449132'],
                    ['type' => 'ISBN_13', 'identifier' => '9780140449136'],
                ],
            ],
        ]]])]);

        $this->assertSame([
            'title' => 'Crime and Punishment',
            'subtitle' => 'A Novel',
            'authors' => ['Fyodor Dostoyevsky'],
            'publisher' => 'Penguin',
            'published_year' => 2003,
            'page_count' => 671,
            'cover_url' => 'https://books.google.com/books/content?id=abc123&printsec=frontcover&img=1&zoom=1',
            'source_id' => 'abc123',
            'language' => 'en',
        ], app(GoogleBooks::class)->findByIsbn('9780140449136'));

        Http::assertSent(fn (Request $request): bool => $request['q'] === 'isbn:9780140449136'
            && ! isset($request['key'])
            && str_contains($request->header('User-Agent')[0], config('app.name')));
    }

    public function test_a_volume_found_by_isbn_10_only_matches(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [[
            'id' => 'abc123',
            'volumeInfo' => [
                'title' => 'Crime and Punishment',
                'industryIdentifiers' => [['type' => 'ISBN_10', 'identifier' => '0140449132']],
            ],
        ]]])]);

        $this->assertSame('abc123', app(GoogleBooks::class)->findByIsbn('9780140449136')['source_id']);
    }

    public function test_a_volume_with_another_isbn_is_ignored(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [[
            'id' => 'other',
            'volumeInfo' => [
                'title' => 'Some other book',
                'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => '9780306406157']],
            ],
        ]]])]);

        $this->assertNull(app(GoogleBooks::class)->findByIsbn('9780140449136'));
    }

    public function test_an_unknown_language_code_is_dropped(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [[
            'id' => 'abc123',
            'volumeInfo' => [
                'title' => 'Crime and Punishment',
                'language' => 'zz',
                'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => '9780140449136']],
            ],
        ]]])]);

        $this->assertNull(app(GoogleBooks::class)->findByIsbn('9780140449136')['language']);
    }

    public function test_the_api_key_is_sent_when_configured(): void
    {
        config(['services.google_books.key' => 'secret-key']);
        Http::fake(['www.googleapis.com/*' => Http::response(['totalItems' => 0])]);

        app(GoogleBooks::class)->findByIsbn('9780140449136');

        Http::assertSent(fn (Request $request): bool => $request['key'] === 'secret-key');
    }

    public function test_unknown_isbn_returns_null_and_is_cached(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['totalItems' => 0])]);

        $this->assertNull(app(GoogleBooks::class)->findByIsbn('9780306406157'));
        $this->assertNull(app(GoogleBooks::class)->findByIsbn('9780306406157'));

        Http::assertSentCount(1);
    }

    public function test_network_and_server_errors_return_null(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::sequence()
            ->pushFailedConnection()
            ->push(['error' => ['code' => 429]], 429)]);

        $this->assertNull(app(GoogleBooks::class)->findByIsbn('9780140449136'));
        $this->assertNull(app(GoogleBooks::class)->findByIsbn('9780140449136'));
    }
}
