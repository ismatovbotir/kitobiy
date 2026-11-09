<?php

use App\Models\Post;
use App\Models\PublisherMember;
use App\Notifications\PublisherClaimApproved;
use App\Support\Concerns\RequiresSuperAdmin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use RequiresSuperAdmin, WithPagination;

    public const int PER_PAGE = 25;

    /** 'waiting' or 'approved'. */
    #[Url(except: 'waiting')]
    public string $status = 'waiting';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, PublisherMember>
     */
    #[Computed]
    public function members(): LengthAwarePaginator
    {
        return PublisherMember::query()
            ->when($this->status === 'approved', fn ($query) => $query->whereNotNull('approved_at')->latest('approved_at'))
            ->when($this->status !== 'approved', fn ($query) => $query->whereNull('approved_at')->oldest())
            ->with(['publisher' => fn ($query) => $query->withCount('editions'), 'user'])
            ->paginate(self::PER_PAGE);
    }

    #[Computed]
    public function waitingCount(): int
    {
        return PublisherMember::query()->whereNull('approved_at')->count();
    }

    /**
     * Let the person keep the book list. The publisher counts as checked from now on.
     */
    public function approve(int $memberId): void
    {
        $member = PublisherMember::query()->whereNull('approved_at')->with('publisher', 'user')->findOrFail($memberId);

        $isFirstMember = ! PublisherMember::query()->where('publisher_id', $member->publisher_id)->whereNotNull('approved_at')->exists();

        DB::transaction(function () use ($member): void {
            $member->forceFill(['approved_at' => now(), 'approved_by' => auth()->id()])->save();
            $member->publisher->forceFill(['is_verified' => true])->save();
        });

        $member->user->notify(new PublisherClaimApproved($member->publisher));

        $this->refreshList();
        session()->flash('status', __(':name can now keep the book list of :publisher.', ['name' => $member->user->name, 'publisher' => $member->publisher->name]));

        // Only the first approved person makes a "joined" announcement.
        if ($isFirstMember && ! Post::query()->where('publisher_id', $member->publisher_id)->exists()) {
            $draft = Post::draftForPublisherJoining($member->publisher, auth()->user());
            session()->flash('news_draft', $draft->id);
        }
    }

    /**
     * Turn a claim down, or take an approved person off the publisher. Their books stay.
     */
    public function remove(int $memberId): void
    {
        PublisherMember::query()->findOrFail($memberId)->delete();

        $this->refreshList();
        session()->flash('status', __('Removed.'));
    }

    private function refreshList(): void
    {
        unset($this->members, $this->waitingCount);
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title(__('Publisher requests'));
    }
};
?>

<div>
    <x-flash class="mb-6" />
    @if (session('news_draft'))
        <p class="-mt-4 mb-6 rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-900 ring-1 ring-sky-200">
            {{ __('A news draft about it is ready. Ask the publisher, then publish it.') }}
            <a href="{{ route('admin.news.edit', session('news_draft')) }}" wire:navigate class="link">{{ __('Open the draft') }}</a>
        </p>
    @endif

    <a href="{{ route('admin.publishers.index') }}" wire:navigate class="link text-sm">← {{ __('Publishers') }}</a>
    <h1 class="mt-2 font-serif text-3xl font-semibold">{{ __('Publisher requests') }}</h1>
    <p class="mt-2 text-ink-soft">{{ __('People who keep a publisher’s book list. Call the number to check that the person works there before approving.') }}</p>

    <div class="mt-6 flex gap-2 text-sm">
        @foreach (['waiting' => __('Waiting (:count)', ['count' => $this->waitingCount]), 'approved' => __('Approved')] as $value => $label)
            <button type="button" wire:key="status-{{ $value }}" wire:click="$set('status', '{{ $value }}')"
                    @class([
                        'min-h-11 cursor-pointer rounded-full border px-4',
                        'border-accent bg-accent text-white' => $status === $value,
                        'border-ink/15 bg-white text-ink-soft hover:text-ink' => $status !== $value,
                    ])>{{ $label }}</button>
        @endforeach
    </div>

    <x-admin-table class="mt-4" :paginator="$this->members" :empty="$status === 'approved' ? __('Nobody yet.') : __('No requests waiting.')">
        <x-slot:head>
            <th class="px-4 py-2">{{ __('Publisher') }}</th>
            <th class="px-4 py-2">{{ __('Person') }}</th>
            <th class="px-4 py-2">{{ __('Phone') }}</th>
            <th class="px-4 py-2">{{ $status === 'approved' ? __('Approved') : __('Sent') }}</th>
            <th class="px-4 py-2"></th>
        </x-slot:head>

        @foreach ($this->members as $member)
            <tr wire:key="member-{{ $member->id }}" class="border-t border-ink/10 align-top">
                <td class="px-4 py-2">
                    <a href="{{ route('admin.publishers.edit', $member->publisher) }}" wire:navigate class="font-medium hover:text-accent">{{ $member->publisher->name }}</a>
                    <span class="block text-xs text-ink-soft">
                        {{ trans_choice(':count book|:count books', $member->publisher->editions_count) }}
                        @unless ($member->publisher->is_verified) · {{ __('Unverified') }} @endunless
                    </span>
                </td>
                <td class="px-4 py-2">
                    {{ $member->user->name }}
                    <span class="block text-xs text-ink-soft">{{ $member->position }} · {{ $member->user->email }}</span>
                </td>
                <td class="px-4 py-2 whitespace-nowrap"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $member->phone) }}" class="link">{{ $member->phone }}</a></td>
                <td class="px-4 py-2 whitespace-nowrap text-ink-soft">{{ ($member->approved_at ?? $member->created_at)->timezone(config('app.timezone'))->format('Y-m-d') }}</td>
                <td class="px-4 py-2 text-right whitespace-nowrap">
                    @if ($member->isApproved())
                        <button type="button" wire:click="remove({{ $member->id }})" wire:confirm="{{ __('Take this person off the publisher? The books stay.') }}" class="text-sm text-red-700 hover:underline">{{ __('Remove') }}</button>
                    @else
                        <button type="button" wire:click="approve({{ $member->id }})" class="btn-primary w-auto">{{ __('Approve') }}</button>
                        <button type="button" wire:click="remove({{ $member->id }})" class="ml-2 text-sm text-ink-soft hover:text-ink">{{ __('Decline') }}</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-admin-table>
</div>
