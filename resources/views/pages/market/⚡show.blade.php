<?php

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Support\Concerns\RequiresMarket;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use RequiresMarket;

    public Listing $listing;

    public string $body = '';

    public function mount(Listing $listing): void
    {
        $this->authorize('view', $listing);
    }

    #[Computed]
    public function existingConversation(): ?Conversation
    {
        return $this->listing->conversations()->where('buyer_id', auth()->id())->first();
    }

    #[Computed]
    public function isSeller(): bool
    {
        return $this->listing->seller_id === auth()->id();
    }

    public function ask(): void
    {
        $this->authorize('contact', $this->listing);

        $this->validate(['body' => ['required', 'string', 'max:'.Message::MAX_LENGTH]]);

        $user = auth()->user();
        $minuteKey = 'messages:'.$user->id;
        $hourKey = 'new-conversations:'.$user->id;
        $isNew = $this->existingConversation === null;

        if (RateLimiter::tooManyAttempts($minuteKey, Message::MAX_PER_MINUTE)
            || ($isNew && RateLimiter::tooManyAttempts($hourKey, Message::MAX_NEW_CONVERSATIONS_PER_HOUR))) {
            $this->addError('body', __('Too many messages. Please try again later.'));

            return;
        }

        RateLimiter::hit($minuteKey, 60);

        if ($isNew) {
            RateLimiter::hit($hourKey, 3600);
        }

        $conversation = $this->listing->conversations()->firstOrCreate(
            ['buyer_id' => $user->id],
            ['seller_id' => $this->listing->seller_id],
        );

        $conversation->send($user, trim($this->body));

        $this->redirectRoute('messages.show', $conversation, navigate: true);
    }

    public function closeListing(): void
    {
        $this->authorize('close', $this->listing);

        $this->listing->close();

        session()->flash('status', __('The listing is closed.'));

        $this->redirectRoute('market.index', navigate: true);
    }

    public function blockSeller(): void
    {
        $this->authorize('blockSeller', $this->listing);

        auth()->user()->block($this->listing->seller);

        session()->flash('status', __('Blocked. You can undo this on your profile page.'));

        $this->redirectRoute('market.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->title($this->listing->title);
    }
};
?>

<div class="max-w-2xl">
    <a href="{{ route('market.index') }}" wire:navigate class="link text-sm">← {{ __('Market') }}</a>

    <x-flash class="mt-4" />

    @if ($this->isSeller && $listing->isHidden())
        <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('A moderator has hidden this listing.') }}</p>
    @elseif (! $listing->isActive())
        <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('This listing is closed.') }}</p>
    @endif

    <section class="mt-4 flex gap-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        @if ($listing->copy)
            <div class="w-24 shrink-0 self-start">
                <x-book-cover :edition="$listing->copy->edition" class="w-full" />
            </div>
        @endif
        <div class="min-w-0">
            <h1 class="font-serif text-2xl font-semibold leading-tight">{{ $listing->title }}</h1>
            @if ($listing->copy?->edition->authors->isNotEmpty())
                <p class="mt-1">{{ $listing->copy->edition->authors->pluck('name')->join(', ') }}</p>
            @endif
            <p class="mt-2 font-medium text-accent">{{ $listing->priceLabel() ?? $listing->kind->label() }}</p>
            @if ($listing->copy?->condition)
                <p class="mt-1 text-sm text-ink-soft">{{ __('Condition') }}: {{ $listing->copy->condition->label() }}</p>
            @endif
            @if (filled($listing->note))
                <p class="mt-3 whitespace-pre-line text-sm">{{ $listing->note }}</p>
            @endif
            <p class="mt-3 text-sm text-ink-soft">
                @php($sellerProfile = $listing->seller->collectorProfile)
                @if ($sellerProfile?->isShared())
                    <a href="{{ $sellerProfile->shareUrl() }}" target="_blank" rel="noopener" class="link">{{ $sellerProfile->display_name }}</a>
                @else
                    {{ $listing->seller->marketName() }}
                @endif
            </p>
        </div>
    </section>

    @can('close', $listing)
        @if ($listing->isActive())
            <button type="button" wire:click="closeListing" wire:confirm="{{ __('Close this listing?') }}" class="mt-4 block text-sm font-medium text-red-700 hover:underline">{{ __('Close the listing') }}</button>
        @endif
    @endcan

    @unless ($this->isSeller)
        @if ($this->existingConversation)
            <a href="{{ route('messages.show', $this->existingConversation) }}" wire:navigate class="btn-primary mt-4 sm:w-auto">{{ __('Open your conversation') }}</a>
        @elseif (auth()->user()->can('contact', $listing))
            <form wire:submit="ask" class="mt-4 space-y-3 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
                <div>
                    <label for="body" class="form-label">{{ __('Ask about this book') }}</label>
                    <textarea wire:model="body" id="body" rows="3" maxlength="1000" required class="form-input"></textarea>
                    @error('body') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <p class="text-xs text-ink-soft">{{ __('The seller sees your profile name and chooses whether to reply. See the book before you pay.') }}</p>
                <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled">{{ __('Send') }}</button>
            </form>
        @elseif (auth()->user()->isSuspended())
            <p class="mt-4 text-sm text-ink-soft">{{ __('Your account cannot use the market right now.') }}</p>
        @else
            <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                {{ __('First choose the name other members will see.') }}
                <a href="{{ route('profile.edit') }}" wire:navigate class="font-medium underline">{{ __('Collector profile') }}</a>
            </p>
        @endif

        <div class="mt-6 flex flex-wrap items-start gap-x-6 gap-y-2 border-t border-ink/10 pt-4">
            <livewire:report-form type="listing" :reportable-id="$listing->id" :key="'report-listing-'.$listing->id" />
            <button type="button" wire:click="blockSeller" wire:confirm="{{ __('Block this person? You will no longer see each other’s books or messages.') }}" class="text-xs text-ink-soft underline hover:text-ink">{{ __('Block') }}</button>
        </div>
    @endunless
</div>
