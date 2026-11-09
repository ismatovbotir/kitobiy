<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\ContributorRole;
use App\Enums\EditionSource;
use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\BookEvent;
use App\Models\Edition;
use App\Models\Genre;
use App\Models\Post;
use App\Models\Publisher;
use App\Models\PublisherMember;
use App\Support\PublicCatalog;
use App\Support\SearchKey;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Public pages about books, people, publishers and genres. Super admins also
 * see hidden records, with a switch to show or hide them.
 */
class CatalogController extends Controller
{
    /**
     * The catalog home: search, genres, newest books from publishers.
     */
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        if ($search !== '') {
            $isbn = Edition::normalizeIsbn($search);

            return view('catalog.index', [
                'search' => $search,
                'books' => Edition::query()
                    ->publiclyListed()
                    ->where(fn (Builder $query) => $query
                        ->whereLike('search_text', SearchKey::containsPattern($search) ?? '')
                        ->orWhere('isbn_13', $isbn)
                        ->orWhere('isbn_10', $isbn))
                    ->with('authors')
                    ->orderBy('title')
                    ->paginate(PublicCatalog::PER_PAGE)
                    ->withQueryString(),
                'people' => Author::query()->publiclyListed()->nameOrAliasContains($search)->orderByDesc('is_verified')->orderBy('name')->limit(12)->get(),
                'publishers' => Publisher::query()->publiclyListed()->nameOrAliasContains($search)->orderByDesc('is_verified')->orderBy('name')->limit(12)->get(),
            ]);
        }

        return view('catalog.index', [
            'search' => '',
            'genres' => Genre::query()->active()->ordered()
                ->withCount(['editions' => fn (Builder $query) => $query->publiclyListed()])
                ->get(),
            'newFromPublishers' => Edition::query()->publiclyListed()
                ->where('source', EditionSource::Publisher)
                ->with('authors')
                ->latest('id')
                ->limit(12)
                ->get(),
            'publishers' => Publisher::query()->publiclyListed()
                ->withCount(['editions' => fn (Builder $query) => $query->publiclyListed()])
                ->orderByDesc('editions_count')
                ->limit(12)
                ->get(),
        ]);
    }

    public function book(Edition $edition, ?string $slug = null): View|RedirectResponse
    {
        $this->ensureVisible($edition->isPubliclyListed(), $edition);

        if ($slug !== PublicCatalog::slug($edition->title)) {
            return redirect($edition->publicUrl(), 301);
        }

        $edition->load(['contributors', 'publisher', 'genres']);
        $libraryCount = $edition->libraryCount();

        return view('catalog.book', [
            'edition' => $edition,
            'authors' => $edition->contributors->filter(fn (Author $person): bool => $person->pivot->role === ContributorRole::Author->value)->values(),
            'shelfCount' => $libraryCount >= PublicCatalog::MIN_SHELVES_SHOWN ? $libraryCount : null,
            'otherEditions' => $edition->work_id === null ? collect() : Edition::query()
                ->publiclyListed()
                ->where('work_id', $edition->work_id)
                ->whereKeyNot($edition->id)
                ->with('authors')
                ->orderBy('published_year')
                ->limit(12)
                ->get(),
            'indexable' => $edition->isIndexable(),
            'news' => $this->newsAbout('edition_id', $edition->id),
            'events' => $this->upcomingEventsAbout('edition_id', $edition->id),
        ]);
    }

    public function person(Author $author, ?string $slug = null): View|RedirectResponse
    {
        $this->ensureVisible($author->isPubliclyListed(), $author);

        if ($slug !== PublicCatalog::slug($author->name)) {
            return redirect($author->publicUrl(), 301);
        }

        return view('catalog.person', [
            'author' => $author->load('aliases'),
            'books' => $author->editions()
                ->publiclyListed()
                ->with('authors')
                ->orderByDesc('published_year')
                ->orderBy('title')
                ->paginate(PublicCatalog::PER_PAGE),
            'indexable' => ! $author->hidden_from_catalog && $author->isIndexable(),
            'news' => $this->newsAbout('author_id', $author->id),
            'events' => $this->upcomingEventsAbout('author_id', $author->id),
        ]);
    }

    public function publisher(Publisher $publisher, ?string $slug = null): View|RedirectResponse
    {
        $this->ensureVisible($publisher->isPubliclyListed(), $publisher);

        if ($slug !== PublicCatalog::slug($publisher->name)) {
            return redirect($publisher->publicUrl(), 301);
        }

        return view('catalog.publisher', [
            'publisher' => $publisher->load('aliases'),
            'keepsOwnList' => PublisherMember::query()->where('publisher_id', $publisher->id)->whereNotNull('approved_at')->exists(),
            'books' => $publisher->editions()
                ->publiclyListed()
                ->with('authors')
                ->orderByDesc('published_year')
                ->orderBy('title')
                ->paginate(PublicCatalog::PER_PAGE),
            'indexable' => ! $publisher->hidden_from_catalog && $publisher->isIndexable(),
            'news' => $this->newsAbout('publisher_id', $publisher->id),
            'events' => $this->upcomingEventsAbout('publisher_id', $publisher->id),
        ]);
    }

    public function genre(Genre $genre): View
    {
        abort_unless($genre->is_active, 404);

        return view('catalog.genre', [
            'genre' => $genre,
            'books' => $genre->editions()
                ->publiclyListed()
                ->with('authors')
                ->orderBy('title')
                ->paginate(PublicCatalog::PER_PAGE),
        ]);
    }

    /**
     * The latest published news about a book, person or publisher.
     *
     * @param  'edition_id'|'author_id'|'publisher_id'  $column
     * @return Collection<int, Post>
     */
    private function newsAbout(string $column, int $id): Collection
    {
        return Post::query()->published()->where($column, $id)->latest('published_at')->limit(3)->get();
    }

    /**
     * The next published events about a book, person or publisher.
     *
     * @param  'edition_id'|'author_id'|'publisher_id'  $column
     * @return Collection<int, BookEvent>
     */
    private function upcomingEventsAbout(string $column, int $id): Collection
    {
        return BookEvent::query()->published()->upcoming()->where($column, $id)->oldest('starts_at')->limit(4)->get();
    }

    /**
     * A hidden or private record is a 404 for everyone but super admins, who
     * may open it to show it again.
     */
    private function ensureVisible(bool $isPublic, Model $record): void
    {
        abort_unless($isPublic || (Gate::allows('super-admin') && $record->exists), 404);
    }
}
