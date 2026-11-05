<?php

use App\Models\FoundReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Messages from people who found a book, across every library the user runs.
 * Finders' contact details are someone's personal data, so they can be deleted.
 */
new class extends Component
{
    use WithPagination;

    public const int PER_PAGE = 20;

    public function mount(): void
    {
        FoundReport::query()
            ->visibleTo(auth()->user())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    #[Computed]
    public function reports(): LengthAwarePaginator
    {
        return FoundReport::query()
            ->visibleTo(auth()->user())
            ->with(['copy.edition', 'copy.library'])
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    public function deleteReport(int $reportId): void
    {
        FoundReport::query()->visibleTo(auth()->user())->findOrFail($reportId)->delete();

        unset($this->reports);
    }

    public function render()
    {
        return $this->view()->title(__('Messages from finders'));
    }
};
?>

<div class="max-w-2xl">
    <h1 class="font-serif text-2xl font-semibold">{{ __('Messages from finders') }}</h1>
    <p class="mt-2 text-sm text-ink-soft">{{ __('People who found one of your books can write to you from its public page.') }}</p>

    @if ($this->reports->isEmpty())
        <p class="mt-4 rounded-xl border border-ink/10 bg-white px-4 py-6 text-center text-ink-soft">{{ __('No messages yet.') }}</p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($this->reports as $report)
                <li wire:key="found-{{ $report->id }}" class="rounded-xl border border-ink/10 bg-white p-4">
                    <a href="{{ route('books.show', $report->copy) }}" wire:navigate class="link font-medium">{{ $report->copy->edition->title }}</a>
                    <p class="text-xs text-ink-soft">{{ $report->copy->library->name }} · {{ $report->created_at->timezone('Asia/Tashkent')->format('Y-m-d H:i') }}</p>
                    <p class="mt-2 break-words whitespace-pre-line text-sm">{{ $report->body }}</p>
                    @if ($report->finder_contact)
                        <p class="mt-2 break-words text-sm"><span class="text-ink-soft">{{ __('Contact') }}:</span> {{ $report->finder_contact }}</p>
                    @endif
                    <button type="button" wire:click="deleteReport({{ $report->id }})" class="mt-2 text-xs text-red-700 hover:underline">{{ __('Delete') }}</button>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $this->reports->links() }}</div>
    @endif
</div>
