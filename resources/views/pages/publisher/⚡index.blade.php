<?php

use App\Models\Publisher;
use App\Models\PublisherMember;
use App\Services\Telegram;
use App\Support\Concerns\SuggestsPublisherNames;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use SuggestsPublisherNames;

    public string $publisher = '';

    public string $position = '';

    public string $phone = '';

    /**
     * The publishers this person keeps or has asked to keep, approved ones first.
     *
     * @return Collection<int, Publisher>
     */
    #[Computed]
    public function publishers(): Collection
    {
        return auth()->user()->publishers()
            ->withCount('editions')
            ->orderByRaw('publisher_user.approved_at IS NULL')
            ->orderBy('name')
            ->get();
    }

    /**
     * Ask to keep a publisher's book list. A super admin checks the claim
     * (by phone if needed) and hears about it in Telegram.
     */
    public function claim(): void
    {
        $this->validate([
            'publisher' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,30}$/'],
        ]);

        $user = auth()->user();

        if ($user->publishers()->wherePivotNull('approved_at')->count() >= PublisherMember::MAX_PENDING_CLAIMS) {
            $this->addError('publisher', __('You already have :count requests waiting. Wait until they are checked.', ['count' => PublisherMember::MAX_PENDING_CLAIMS]));

            return;
        }

        $publisher = Publisher::findOrCreateByName($this->publisher);

        if ($user->publishers()->whereKey($publisher->getKey())->exists()) {
            $this->addError('publisher', __('You have already asked for this publisher.'));

            return;
        }

        $user->publishers()->attach($publisher, [
            'position' => trim($this->position),
            'phone' => trim($this->phone),
        ]);

        app(Telegram::class)->notifyAdmin(fn (): string => __('New publisher request on Kitobiy: :name wants to keep the book list of :publisher. Check it: :url', [
            'name' => $user->name,
            'publisher' => $publisher->name,
            'url' => route('admin.publishers.claims'),
        ]));

        $this->reset('publisher', 'position', 'phone');
        unset($this->publishers);
        session()->flash('status', __('Request sent. We will check it and write to you.'));
    }

    public function render()
    {
        return $this->view()->title(__('For publishers'));
    }
};
?>

<div class="max-w-2xl">
    <h1 class="font-serif text-3xl font-semibold">{{ __('For publishers') }}</h1>
    <p class="mt-2 text-ink-soft">{{ __('Add your books to Kitobiy once, and every reader who scans their barcode gets the right title, authors and cover at once.') }}</p>

    <x-flash class="mt-4" />

    @if ($this->publishers->isNotEmpty())
        <ul class="mt-6 divide-y divide-ink/10 rounded-xl border border-ink/10 bg-white">
            @foreach ($this->publishers as $item)
                <li wire:key="publisher-{{ $item->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                    <div>
                        <p class="font-medium">{{ $item->name }}</p>
                        <p class="text-sm text-ink-soft">{{ trans_choice(':count book|:count books', $item->editions_count) }}</p>
                    </div>
                    @if ($item->membership->approved_at)
                        <a href="{{ route('publisher.books', $item) }}" wire:navigate class="btn-primary w-auto">{{ __('Book list') }}</a>
                    @else
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Waiting for approval') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <section class="mt-8 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <h2 class="font-serif text-2xl font-semibold">{{ __('Work at a publisher?') }}</h2>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Ask to keep its book list. We call to check that you work there, then you can add the books.') }}</p>

        <form wire:submit="claim" class="mt-4 space-y-4">
            <div>
                <label for="publisher" class="form-label">{{ __('Publisher') }}</label>
                <x-publisher-input id="publisher" wire:model="publisher" />
                @error('publisher') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="position" class="form-label">{{ __('Your job there') }}</label>
                    <input wire:model="position" id="position" type="text" maxlength="100" placeholder="{{ __('e.g. Editor, Director') }}" class="form-input">
                    @error('position') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone" class="form-label">{{ __('Phone') }}</label>
                    <input wire:model="phone" id="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="+998 90 123 45 67" class="form-input">
                    @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-ink-soft">{{ __('Only the Kitobiy team sees your phone number.') }}</p>
            <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled">{{ __('Send request') }}</button>
        </form>
    </section>
</div>
