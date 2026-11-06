<?php

use App\Models\Conversation;
use App\Support\Concerns\RequiresMarket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use RequiresMarket, WithPagination;

    public const int PER_PAGE = 30;

    #[Computed]
    public function conversations(): LengthAwarePaginator
    {
        $user = auth()->user();

        return Conversation::query()
            ->involving($user)
            ->with(['listing', 'buyer.collectorProfile', 'seller.collectorProfile'])
            ->withCount(['messages as unread_count' => fn (Builder $message) => $message
                ->whereNull('read_at')
                ->whereNull('hidden_at')
                ->where('sender_id', '!=', $user->id)])
            ->latest('last_message_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    public function render()
    {
        return $this->view()->title(__('Messages'));
    }
};
?>

<div class="max-w-2xl">
    <h1 class="font-serif text-2xl font-semibold">{{ __('Messages') }}</h1>

    <x-flash class="mt-4" />

    @if ($this->conversations->isEmpty())
        <p class="mt-6 rounded-xl border border-ink/10 bg-white px-4 py-6 text-center text-ink-soft">{{ __('No messages yet. They start when someone asks about a book on the market.') }}</p>
    @else
        <ul class="mt-4 divide-y divide-ink/10 rounded-xl border border-ink/10 bg-white">
            @foreach ($this->conversations as $conversation)
                <li wire:key="conversation-{{ $conversation->id }}">
                    <a href="{{ route('messages.show', $conversation) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-paper">
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $conversation->listing->title }}</span>
                            <span class="block truncate text-sm text-ink-soft">{{ $conversation->otherParty(auth()->user())->marketName() }}</span>
                        </span>
                        @if ($conversation->unread_count > 0)
                            <span class="shrink-0 rounded-full bg-accent px-2 text-xs font-semibold text-white">{{ $conversation->unread_count }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $this->conversations->links() }}</div>
    @endif
</div>
