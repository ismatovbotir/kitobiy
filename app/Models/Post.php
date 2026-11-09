<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * A news post on the public site, written by a super admin. Shown in Russian
 * when it has a Russian text and the reader uses Russian, else in Uzbek.
 */
#[Fillable(['title_uz', 'title_ru', 'body_uz', 'body_ru', 'published_at', 'publisher_id', 'author_id', 'edition_id', 'created_by'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    public const int PER_PAGE = 12;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * A free address made from the Uzbek title, e.g. "sharq-kitobiyga-qoshildi-2".
     */
    public static function freeSlug(string $title, ?int $exceptId = null): string
    {
        $base = Str::limit(Str::slug($title), 100, '') ?: 'post';
        $slug = $base;

        for ($number = 2; static::query()->where('slug', $slug)->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))->exists(); $number++) {
            $slug = $base.'-'.$number;
        }

        return $slug;
    }

    /**
     * A draft announcing that a publisher now keeps its own book list on
     * Kitobiy, for a super admin to check (and ask the publisher) before publishing.
     */
    public static function draftForPublisherJoining(Publisher $publisher, ?User $creator = null): self
    {
        $text = fn (string $locale): array => [
            'title' => __(':publisher joined Kitobiy', ['publisher' => $publisher->name], $locale),
            'body' => __(':publisher now adds its books to Kitobiy itself. Readers who scan the barcode of its books get the right title, authors and cover at once, and the details stay correct because only the publisher can change them.', ['publisher' => $publisher->name], $locale)
                ."\n\n".__('See the books of :publisher in the catalog: :url', ['publisher' => $publisher->name, 'url' => $publisher->publicUrl()], $locale),
        ];

        $post = new self([
            'title_uz' => $text('uz')['title'],
            'title_ru' => $text('ru')['title'],
            'body_uz' => $text('uz')['body'],
            'body_ru' => $text('ru')['body'],
            'publisher_id' => $publisher->id,
            'created_by' => $creator?->id,
        ]);
        $post->slug = static::freeSlug($post->title_uz);
        $post->save();

        return $post;
    }

    /**
     * Posts readers can see: published, and the time has come.
     *
     * @param  Builder<Post>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
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
     * Point the post at a newly stored picture (or none) and delete the previous one.
     */
    public function setImage(?string $path): void
    {
        $previousPath = $this->image_path;

        $this->forceFill(['image_path' => $path])->save();

        if ($previousPath !== null) {
            Storage::disk(config('catalog.cover_disk'))->delete($previousPath);
        }
    }

    public function publicUrl(): string
    {
        return route('news.show', $this);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
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
     * Russian for Russian readers when there is a Russian text, else Uzbek.
     */
    private function inReaderLanguage(string $field): string
    {
        return app()->getLocale() === 'ru' && filled($this->{$field.'_ru'})
            ? $this->{$field.'_ru'}
            : $this->{$field.'_uz'};
    }
}
