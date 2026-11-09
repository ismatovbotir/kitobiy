<?php

namespace App\Models;

use App\Models\Concerns\HasSiteText;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A news post on the public site, written by a super admin. Shown in Russian
 * when it has a Russian text and the reader uses Russian, else in Uzbek.
 */
#[Fillable(['title_uz', 'title_ru', 'body_uz', 'body_ru', 'published_at', 'publisher_id', 'author_id', 'edition_id', 'created_by'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasSiteText;

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

    public function publicUrl(): string
    {
        return route('news.show', $this);
    }
}
