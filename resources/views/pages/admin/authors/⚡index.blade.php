<?php

use App\Models\Author;
use App\Support\Concerns\RequiresSuperAdmin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use RequiresSuperAdmin, WithPagination;

    public const int PER_PAGE = 25;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** 'all', 'unverified' or 'verified'. */
    #[Url(except: 'all')]
    public string $status = 'all';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Author>
     */
    #[Computed]
    public function authors(): LengthAwarePaginator
    {
        return Author::query()
            ->when(trim($this->search) !== '', fn ($query) => $query->nameOrAliasContains($this->search))
            ->when($this->status === 'unverified', fn ($query) => $query->where('is_verified', false))
            ->when($this->status === 'verified', fn ($query) => $query->where('is_verified', true))
            ->with('aliases')
            ->withCount('editions')
            ->orderBy('name')
            ->paginate(self::PER_PAGE);
    }

    public function verify(int $authorId): void
    {
        Author::whereKey($authorId)->update(['is_verified' => true]);

        unset($this->authors);
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title(__('People'));
    }
};
?>

<div>
    <x-flash class="mb-6" />

    <h1 class="font-serif text-3xl font-semibold">{{ __('People') }}</h1>
    <p class="mt-2 text-ink-soft">{{ __('Authors, translators and other contributors shared by every library.') }}</p>

    <x-admin-filters class="mt-6" :placeholder="__('Search by name or other spelling')" />

    <x-admin-table class="mt-4" :paginator="$this->authors" :empty="__('No people found.')">
        <x-slot:head>
            <th class="px-4 py-2">{{ __('Name') }}</th>
            <th class="px-4 py-2">{{ __('Years') }}</th>
            <th class="px-4 py-2">{{ __('Country') }}</th>
            <th class="px-4 py-2 text-right">{{ __('Books') }}</th>
            <th class="px-4 py-2">{{ __('Status') }}</th>
        </x-slot:head>

        @foreach ($this->authors as $author)
            <tr wire:key="author-{{ $author->id }}" class="border-t border-ink/10">
                <td class="px-4 py-2">
                    <a href="{{ route('admin.authors.edit', $author) }}" wire:navigate class="font-medium hover:text-accent">{{ $author->name }}</a>
                    @if ($author->aliases->isNotEmpty())
                        <span class="block text-xs text-ink-soft">{{ $author->aliases->pluck('name')->join(', ') }}</span>
                    @endif
                </td>
                <td class="px-4 py-2 text-ink-soft">{{ collect([$author->birth_year, $author->death_year])->filter()->join('–') ?: '—' }}</td>
                <td class="px-4 py-2 text-ink-soft">{{ $author->country ?? '—' }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ $author->editions_count }}</td>
                <td class="px-4 py-2">
                    <x-verified-badge :record="$author" />
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</div>
