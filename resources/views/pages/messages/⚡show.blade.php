<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Support\Concerns\RequiresMarket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use RequiresMarket;

    /** Older messages are kept but not shown, so a long chat stays quick. */
    public const int MESSAGES_SHOWN = 100;

    public Conversation $conversation;

    public string $body = '';

    public function mount(Conversation $conversation): void
    {
        $this->authorize('view', $conversation);

        $conversation->markReadBy(auth()->user());
    }

    /**
     * Runs on each poll, so a message that arrives while the page is open counts as read.
     */
    public function refreshThread(): void
    {
        $this->authorize('view', $this->conversation);

        $this->conversation->markReadBy(auth()->user());
    }

    /**
     * @return Collection<int, Message>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->conversation->messages()
            ->latest('id')
            ->limit(self::MESSAGES_SHOWN)
            ->get()
            ->reverse()
            ->values();
    }

    #[Computed]
    public function canReply(): bool
    {
        return auth()->user()->can('reply', $this->conversation);
    }

    public function reply(): void
    {
        $this->authorize('reply', $this->conversation);

        $this->validate(['body' => ['required', 'string', 'max:'.Message::MAX_LENGTH]]);

        $limiterKey = 'messages:'.auth()->id();

        if (RateLimiter::tooManyAttempts($limiterKey, Message::MAX_PER_MINUTE)) {
            $this->addError('body', __('Too many messages. Please try again later.'));

            return;
        }

        RateLimiter::hit($limiterKey, 60);

        $this->conversation->send(auth()->user(), trim($this->body));

        $this->reset('body');
        unset($this->messages);
    }

    public function blockOtherParty(): void
    {
        $this->authorize('view', $this->conversation);

        auth()->user()->block($this->conversation->otherParty(auth()->user()));

        session()->flash('status', __('Blocked. You can undo this on your profile page.'));

        $this->redirectRoute('messages.index', navigate: true);
    }

    public function render()
    {
        return $this->view()->title($this->conversation->listing->title);
    }
};
?>

<div class="max-w-2xl" wire:poll.30s="refreshThread">
    <a href="{{ route('messages.index') }}" wire:navigate class="link text-sm">← {{ __('Messages') }}</a>

    <h1 class="mt-4 font-serif text-2xl font-semibold leading-tight">
        @can('view', $conversation->listing)
            <a href="{{ route('market.show', $conversation->listing) }}" wire:navigate class="hover:underline">{{ $conversation->listing->title }}</a>
        @else
            {{ $conversation->listing->title }}
        @endcan
    </h1>
    <p class="mt-1 text-sm text-ink-soft">{{ $conversation->otherParty(auth()->user())->marketName() }}</p>

    <ul class="mt-4 space-y-3">
        @foreach ($this->messages as $message)
            @php($isMine = $message->sender_id === auth()->id())
            <li wire:key="message-{{ $message->id }}" @class(['flex flex-col', 'items-end' => $isMine, 'items-start' => ! $isMine])>
                <div @class([
                    'max-w-[85%] rounded-xl px-3 py-2 text-sm',
                    'bg-accent/10' => $isMine,
                    'border border-ink/10 bg-white' => ! $isMine,
                ])>
                    @if ($message->isHidden())
                        <p class="text-ink-soft italic">{{ __('Hidden by a moderator.') }}</p>
                    @else
                        <p class="break-words whitespace-pre-line">{{ $message->body }}</p>
                    @endif
                    <p class="mt-1 text-xs text-ink-soft">{{ $message->sentAtLabel() }}</p>
                </div>
                @if (! $isMine && ! $message->isHidden())
                    <div class="mt-1 max-w-[85%]">
                        <livewire:report-form type="message" :reportable-id="$message->id" :key="'report-message-'.$message->id" />
                    </div>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($this->canReply)
        <form wire:submit="reply" class="mt-6 space-y-3">
            <div>
                <label for="body" class="form-label">{{ __('Your message') }}</label>
                <textarea wire:model="body" id="body" rows="3" maxlength="1000" required class="form-input"></textarea>
                @error('body') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled" wire:target="reply">{{ __('Send') }}</button>
        </form>
    @else
        <p class="mt-6 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('You cannot send messages in this conversation.') }}</p>
    @endif

    <div class="mt-6 border-t border-ink/10 pt-4">
        <button type="button" wire:click="blockOtherParty" wire:confirm="{{ __('Block this person? You will no longer see each other’s books or messages.') }}" class="text-xs text-ink-soft underline hover:text-ink">{{ __('Block') }}</button>
    </div>
</div>
