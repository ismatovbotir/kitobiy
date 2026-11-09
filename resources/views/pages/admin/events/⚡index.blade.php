<?php

use App\Models\BookEvent;
use App\Support\Concerns\RequiresSuperAdmin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use RequiresSuperAdmin, WithPagination;

    public const int PER_PAGE = 25;

    /**
     * Newest dates first, so the next events are near the top.
     *
     * @return LengthAwarePaginator<int, BookEvent>
     */
    #[Computed]
    public function events(): LengthAwarePaginator
    {
        return BookEvent::query()->latest('starts_at')->latest('id')->paginate(self::PER_PAGE);
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title(__('Book events'));
    }
};
?>

<div>
    <x-flash class="mb-6" />

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-serif text-3xl font-semibold">{{ __('Book events') }}</h1>
            <p class="mt-2 text-ink-soft">{{ __('Fairs, author meetings and presentations on the public events page.') }}</p>
        </div>
        <a href="{{ route('admin.events.create') }}" wire:navigate class="btn-primary w-auto">{{ __('New event') }}</a>
    </div>

    <x-admin-table class="mt-6" :paginator="$this->events" :empty="__('No events yet.')">
        <x-slot:head>
            <th class="px-4 py-2">{{ __('Title') }}</th>
            <th class="px-4 py-2">{{ __('When') }}</th>
            <th class="px-4 py-2">{{ __('Status') }}</th>
            <th class="px-4 py-2"></th>
        </x-slot:head>

        @foreach ($this->events as $event)
            <tr wire:key="event-{{ $event->id }}" class="border-t border-ink/10">
                <td class="px-4 py-2">
                    <a href="{{ route('admin.events.edit', $event) }}" wire:navigate class="font-medium hover:text-accent">{{ $event->title_uz }}</a>
                    <span class="block text-xs text-ink-soft">{{ $event->placeLabel() }}</span>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-ink-soft">{{ $event->startsAtLocal()->format('Y-m-d H:i') }}</td>
                <td class="px-4 py-2 whitespace-nowrap">
                    @if (! $event->is_published)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Draft') }}</span>
                    @elseif ($event->isOver())
                        <span class="rounded-full bg-ink/5 px-2 py-0.5 text-xs font-medium text-ink-soft">{{ __('Over') }}</span>
                    @else
                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">{{ __('Published') }}</span>
                    @endif
                </td>
                <td class="px-4 py-2 text-right">
                    <a href="{{ $event->publicUrl() }}" class="link text-sm">{{ $event->is_published ? __('Open') : __('Preview') }}</a>
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</div>
