<?php

use App\Models\Publisher;
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
     * @return LengthAwarePaginator<int, Publisher>
     */
    #[Computed]
    public function publishers(): LengthAwarePaginator
    {
        return Publisher::query()
            ->when(trim($this->search) !== '', fn ($query) => $query->nameOrAliasContains($this->search))
            ->when($this->status === 'unverified', fn ($query) => $query->where('is_verified', false))
            ->when($this->status === 'verified', fn ($query) => $query->where('is_verified', true))
            ->with('aliases')
            ->withCount('editions')
            ->orderBy('name')
            ->paginate(self::PER_PAGE);
    }

    public function verify(int $publisherId): void
    {
        Publisher::whereKey($publisherId)->update(['is_verified' => true]);

        unset($this->publishers);
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title(__('Publishers'));
    }
};
?>

<div>
    <x-flash class="mb-6" />

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-serif text-3xl font-semibold">{{ __('Publishers') }}</h1>
            <p class="mt-2 text-ink-soft">{{ __('Publishing houses shared by every library.') }}</p>
        </div>
        <a href="{{ route('admin.publishers.create') }}" wire:navigate class="btn-primary w-auto">{{ __('New publisher') }}</a>
    </div>

    <x-admin-filters class="mt-6" :placeholder="__('Search by name or other spelling')" />

    <x-admin-table class="mt-4" :paginator="$this->publishers" :empty="__('No publishers found.')">
        <x-slot:head>
            <th class="px-4 py-2">{{ __('Name') }}</th>
            <th class="px-4 py-2">{{ __('City') }}</th>
            <th class="px-4 py-2">{{ __('Country') }}</th>
            <th class="px-4 py-2 text-right">{{ __('Books') }}</th>
            <th class="px-4 py-2">{{ __('Status') }}</th>
        </x-slot:head>

        @foreach ($this->publishers as $publisher)
            <tr wire:key="publisher-{{ $publisher->id }}" class="border-t border-ink/10">
                <td class="px-4 py-2">
                    <a href="{{ route('admin.publishers.edit', $publisher) }}" wire:navigate class="font-medium hover:text-accent">{{ $publisher->name }}</a>
                    @if ($publisher->aliases->isNotEmpty())
                        <span class="block text-xs text-ink-soft">{{ $publisher->aliases->pluck('name')->join(', ') }}</span>
                    @endif
                </td>
                <td class="px-4 py-2 text-ink-soft">{{ $publisher->city ?: '—' }}</td>
                <td class="px-4 py-2 text-ink-soft">{{ $publisher->country ?? '—' }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ $publisher->editions_count }}</td>
                <td class="px-4 py-2">
                    <x-verified-badge :record="$publisher" />
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</div>
