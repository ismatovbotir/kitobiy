<?php

namespace App\Models\Concerns;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * For texts super admins write for the public site (news, events): a slug
 * address, an Uzbek title and Markdown text with an optional Russian version,
 * a picture, and the publisher, person and book it is about.
 */
trait HasSiteText
{
    /**
     * A free address made from the Uzbek title, e.g. "sharq-kitobiyga-qoshildi-2".
     */
    public static function freeSlug(string $title, ?int $exceptId = null): string
    {
        $base = Str::limit(Str::slug($title), 100, '') ?: 'item';
        $slug = $base;

        for ($number = 2; static::query()->where('slug', $slug)->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))->exists(); $number++) {
            $slug = $base.'-'.$number;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return Attribute<string, never>
     */
    protected function title(): Attribute
    {
        return Attribute::get(fn (): string => $this->inReaderLanguage('title'));
    }

    /**
     * The text as HTML. Markdown links and emphasis work; raw HTML is escaped.
     */
    public function bodyHtml(): HtmlString
    {
        return new HtmlString(Str::markdown($this->inReaderLanguage('body'), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]));
    }

    /**
     * The first sentences of the text without Markdown, for lists and page descriptions.
     */
    public function excerpt(int $length = 180): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($this->bodyHtml()->toHtml()))), $length);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path !== null
            ? Storage::disk(config('catalog.cover_disk'))->url($this->image_path)
            : null);
    }

    /**
     * Point the record at a newly stored picture (or none) and delete the previous one.
     */
    public function setImage(?string $path): void
    {
        $previousPath = $this->image_path;

        $this->forceFill(['image_path' => $path])->save();

        if ($previousPath !== null) {
            Storage::disk(config('catalog.cover_disk'))->delete($previousPath);
        }
    }

    /**
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Links to the public pages of what this is about, for those that are public.
     *
     * @return list<array{label: string, name: string, url: string}>
     */
    public function relatedLinks(): array
    {
        return array_values(array_filter([
            $this->publisher?->isPubliclyListed() ? ['label' => __('Publisher'), 'name' => $this->publisher->name, 'url' => $this->publisher->publicUrl()] : null,
            $this->author?->isPubliclyListed() ? ['label' => __('Author'), 'name' => $this->author->name, 'url' => $this->author->publicUrl()] : null,
            $this->edition?->isPubliclyListed() ? ['label' => __('Book'), 'name' => $this->edition->title, 'url' => $this->edition->publicUrl()] : null,
        ]));
    }

    /**
     * Russian for Russian readers when there is a Russian text, else Uzbek.
     */
    private function inReaderLanguage(string $field): string
    {
        return app()->getLocale() === 'ru' && filled($this->{$field.'_ru'})
            ? $this->{$field.'_ru'}
            : $this->{$field.'_uz'};
    }
}
