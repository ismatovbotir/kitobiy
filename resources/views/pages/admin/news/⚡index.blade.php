<?php

use App\Models\Post;
use App\Support\Concerns\RequiresSuperAdmin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use RequiresSuperAdmin, WithPagination;

    public const int PER_PAGE = 25;

    /**
     * Drafts first, then scheduled and published posts, newest first.
     *
     * @return LengthAwarePaginator<int, Post>
     */
    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        return Post::query()
            ->orderByRaw('published_at IS NOT NULL')
            ->latest('published_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title(__('News'));
    }
};
?>

<div>
    <x-flash class="mb-6" />

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-serif text-3xl font-semibold">{{ __('News') }}</h1>
            <p class="mt-2 text-ink-soft">{{ __('Posts on the public news page. Share their links on LinkedIn and Telegram.') }}</p>
        </div>
        <a href="{{ route('admin.news.create') }}" wire:navigate class="btn-primary w-auto">{{ __('New post') }}</a>
    </div>

    <x-admin-table class="mt-6" :paginator="$this->posts" :empty="__('No news yet.')">
        <x-slot:head>
            <th class="px-4 py-2">{{ __('Title') }}</th>
            <th class="px-4 py-2">{{ __('Status') }}</th>
            <th class="px-4 py-2"></th>
        </x-slot:head>

        @foreach ($this->posts as $post)
            <tr wire:key="post-{{ $post->id }}" class="border-t border-ink/10">
                <td class="px-4 py-2">
                    <a href="{{ route('admin.news.edit', $post) }}" wire:navigate class="font-medium hover:text-accent">{{ $post->title_uz }}</a>
                    @if (blank($post->title_ru))
                        <span class="block text-xs text-ink-soft">{{ __('No Russian text') }}</span>
                    @endif
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                    @if ($post->isPublished())
                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">{{ __('Published') }}</span>
                    @elseif ($post->published_at)
                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800">{{ __('Scheduled') }}</span>
                    @else
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Draft') }}</span>
                    @endif
                    @if ($post->published_at)
                        <span class="ml-1 text-xs text-ink-soft">{{ $post->published_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                    @endif
                </td>
                <td class="px-4 py-2 text-right">
                    <a href="{{ $post->publicUrl() }}" class="link text-sm">{{ $post->isPublished() ? __('Open') : __('Preview') }}</a>
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</div>
