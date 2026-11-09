<?php

namespace App\Support\Concerns;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Publisher;
use Illuminate\Database\Eloquent\Model;

/**
 * For admin forms of site texts (news, events): the publisher, person and book
 * the text is about, typed as names and an ISBN and found in the catalog.
 * Pairs with <x-admin-related-fields />.
 */
trait PicksRelatedCatalogRecords
{
    use SuggestsContributorNames, SuggestsPublisherNames;

    public string $publisherName = '';

    public string $authorName = '';

    public string $bookIsbn = '';

    /**
     * Fill the fields from a saved record with publisher, author and edition relations.
     */
    protected function fillRelatedFrom(Model $record): void
    {
        $this->publisherName = (string) $record->publisher?->name;
        $this->authorName = (string) $record->author?->name;
        $this->bookIsbn = (string) ($record->edition?->isbn_13 ?? $record->edition?->isbn_10);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function relatedRules(): array
    {
        return [
            'publisherName' => ['nullable', 'string', 'max:255'],
            'authorName' => ['nullable', 'string', 'max:255'],
            'bookIsbn' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * The ids of what was typed. Adds an error and returns null when one of
     * them is not in the catalog.
     *
     * @return array{publisher_id: int|null, author_id: int|null, edition_id: int|null}|null
     */
    protected function relatedCatalogIds(): ?array
    {
        $publisher = trim($this->publisherName) !== '' ? Publisher::findByNameOrAlias($this->publisherName) : null;
        $author = trim($this->authorName) !== '' ? Author::findByNameOrAlias($this->authorName) : null;
        $edition = trim($this->bookIsbn) !== '' ? Edition::query()->whereIsbn($this->bookIsbn)->first() : null;

        foreach ([
            'publisherName' => [$this->publisherName, $publisher, __('There is no publisher with this name in the catalog.')],
            'authorName' => [$this->authorName, $author, __('There is no person with this name in the catalog.')],
            'bookIsbn' => [$this->bookIsbn, $edition, __('There is no book with this ISBN in the catalog.')],
        ] as $field => [$typed, $found, $error]) {
            if (trim($typed) !== '' && $found === null) {
                $this->addError($field, $error);
            }
        }

        if ($this->getErrorBag()->hasAny(['publisherName', 'authorName', 'bookIsbn'])) {
            return null;
        }

        return [
            'publisher_id' => $publisher?->id,
            'author_id' => $author?->id,
            'edition_id' => $edition?->id,
        ];
    }
}
