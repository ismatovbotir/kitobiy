<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Kitobiy news on the public site. Drafts and scheduled posts open only for
 * super admins, as a preview.
 */
class NewsController extends Controller
{
    public function index(): View
    {
        return view('news.index', [
            'posts' => Post::query()->published()->latest('published_at')->latest('id')->paginate(Post::PER_PAGE),
        ]);
    }

    public function show(Post $post): View
    {
        abort_unless($post->isPublished() || Gate::allows('super-admin'), 404);

        $post->load('publisher', 'author', 'edition');

        return view('news.show', [
            'post' => $post,
            'related' => array_filter([
                $post->publisher?->isPubliclyListed() ? ['label' => __('Publisher'), 'name' => $post->publisher->name, 'url' => $post->publisher->publicUrl()] : null,
                $post->author?->isPubliclyListed() ? ['label' => __('Author'), 'name' => $post->author->name, 'url' => $post->author->publicUrl()] : null,
                $post->edition?->isPubliclyListed() ? ['label' => __('Book'), 'name' => $post->edition->title, 'url' => $post->edition->publicUrl()] : null,
            ]),
            'morePosts' => Post::query()->published()->whereKeyNot($post->id)->latest('published_at')->limit(3)->get(),
        ]);
    }
}
