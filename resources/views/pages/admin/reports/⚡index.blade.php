<?php

use App\Enums\ReportOutcome;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use App\Support\Concerns\RequiresSuperAdmin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Open reports. A reported message is shown alone: the moderator never sees the
 * rest of the conversation.
 */
new class extends Component
{
    use RequiresSuperAdmin, WithPagination;

    public const int PER_PAGE = 25;

    #[Computed]
    public function reports(): LengthAwarePaginator
    {
        return Report::query()
            ->open()
            ->with([
                'reporter.collectorProfile',
                'reportable' => fn (MorphTo $morph) => $morph->morphWith([
                    Listing::class => ['seller.collectorProfile'],
                    Message::class => ['sender.collectorProfile'],
                ]),
            ])
            ->oldest('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function suspendedUsers(): Collection
    {
        return User::query()->whereNotNull('suspended_at')->with('collectorProfile')->orderBy('name')->get();
    }

    public function dismiss(int $reportId): void
    {
        Report::query()->findOrFail($reportId)->resolve(auth()->user(), ReportOutcome::Dismissed);

        $this->refreshLists();

        session()->flash('status', __('Report dismissed.'));
    }

    public function hideContent(int $reportId): void
    {
        $report = Report::query()->findOrFail($reportId);

        $report->reportable?->hideByModerator();
        $report->resolve(auth()->user(), ReportOutcome::ContentHidden);

        $this->refreshLists();

        session()->flash('status', __('Content hidden.'));
    }

    public function suspendAuthor(int $reportId): void
    {
        $report = Report::query()->findOrFail($reportId);
        $author = $report->author();

        if ($author === null || $author->isSuperAdmin()) {
            session()->flash('status', __('This account cannot be suspended.'));

            return;
        }

        $author->suspend();
        $report->resolve(auth()->user(), ReportOutcome::UserSuspended);

        $this->refreshLists();

        session()->flash('status', __('Account suspended.'));
    }

    public function restoreUser(int $userId): void
    {
        User::query()->findOrFail($userId)->restoreFromSuspension();

        $this->refreshLists();

        session()->flash('status', __('Account restored.'));
    }

    private function refreshLists(): void
    {
        unset($this->reports, $this->suspendedUsers);
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title(__('Reports'));
    }
};
?>

<div>
    <h1 class="font-serif text-2xl font-semibold">{{ __('Reports') }}</h1>

    <x-flash class="mt-4" />

    @if ($this->reports->isEmpty())
        <p class="mt-4 rounded-xl border border-ink/10 bg-white px-4 py-6 text-center text-ink-soft">{{ __('No open reports.') }}</p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($this->reports as $report)
                @php($reportable = $report->reportable)
                <li wire:key="report-{{ $report->id }}" class="rounded-xl border border-ink/10 bg-white p-4">
                    <p class="text-sm font-medium">{{ $report->reason->label() }}</p>
                    <p class="text-xs text-ink-soft">{{ __('From :name, :date', ['name' => $report->reporter->marketName(), 'date' => $report->created_at->toDateString()]) }}</p>
                    @if (filled($report->details))
                        <p class="mt-2 whitespace-pre-line text-sm">{{ $report->details }}</p>
                    @endif

                    <div class="mt-3 rounded-lg bg-paper p-3 text-sm">
                        @if ($reportable instanceof Listing)
                            <p class="text-xs text-ink-soft">{{ __('Listing by :name', ['name' => $reportable->seller->marketName()]) }}</p>
                            <p class="mt-1 font-medium">{{ $reportable->title }}</p>
                            @if (filled($reportable->note))
                                <p class="mt-1 whitespace-pre-line">{{ $reportable->note }}</p>
                            @endif
                        @elseif ($reportable instanceof Message)
                            <p class="text-xs text-ink-soft">{{ __('Message from :name', ['name' => $reportable->sender->marketName()]) }}</p>
                            <p class="mt-1 break-words whitespace-pre-line">{{ $reportable->body }}</p>
                        @else
                            <p class="text-ink-soft">{{ __('This content no longer exists.') }}</p>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm">
                        <button type="button" wire:click="dismiss({{ $report->id }})" class="link">{{ __('Dismiss') }}</button>
                        @if ($reportable)
                            <button type="button" wire:click="hideContent({{ $report->id }})" class="font-medium text-red-700 hover:underline">{{ __('Hide content') }}</button>
                            <button type="button" wire:click="suspendAuthor({{ $report->id }})" wire:confirm="{{ __('Suspend this account? They keep their library but lose the market and messages.') }}" class="font-medium text-red-700 hover:underline">{{ __('Suspend account') }}</button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $this->reports->links() }}</div>
    @endif

    @if ($this->suspendedUsers->isNotEmpty())
        <section class="mt-8">
            <h2 class="font-serif text-xl font-semibold">{{ __('Suspended accounts') }}</h2>
            <ul class="mt-2 divide-y divide-ink/10 rounded-xl border border-ink/10 bg-white">
                @foreach ($this->suspendedUsers as $suspendedUser)
                    <li wire:key="suspended-{{ $suspendedUser->id }}" class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                        <span class="min-w-0 truncate">{{ $suspendedUser->marketName() }}</span>
                        <button type="button" wire:click="restoreUser({{ $suspendedUser->id }})" class="link shrink-0">{{ __('Restore') }}</button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
