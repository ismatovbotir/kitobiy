@props(['placeholder'])

{{-- Search box + verification filter for admin lists (binds `search` and `status`). --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-3']) }}>
    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ $placeholder }}" aria-label="{{ __('Search') }}" class="form-input max-w-sm">
    <div class="inline-flex overflow-hidden rounded-lg border border-ink/20 text-sm" role="group" aria-label="{{ __('Status') }}">
        @foreach (['all' => __('All'), 'unverified' => __('Unverified'), 'verified' => __('Verified')] as $value => $label)
            <button type="button" wire:click="$set('status', '{{ $value }}')" wire:key="status-{{ $value }}"
                    @class(['px-3 py-2', 'bg-ink text-paper' => $this->status === $value, 'bg-white hover:bg-ink/5' => $this->status !== $value])
                    @if ($this->status === $value) aria-pressed="true" @endif>{{ $label }}</button>
        @endforeach
    </div>
</div>
