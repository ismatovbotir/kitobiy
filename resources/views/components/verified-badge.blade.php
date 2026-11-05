@props(['record'])

{{-- Verified / unverified badge; unverified ones get a quick "Verify" button (calls verify(id)). --}}
<div class="flex items-center gap-2">
    @if ($record->is_verified)
        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">{{ __('Verified') }}</span>
    @else
        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Unverified') }}</span>
        <button type="button" wire:click="verify({{ $record->id }})" class="link text-xs">{{ __('Verify') }}</button>
    @endif
    @if ($record->wikidata_id)
        <a href="https://www.wikidata.org/wiki/{{ $record->wikidata_id }}" target="_blank" rel="noopener" class="text-xs text-ink-soft hover:text-ink" title="Wikidata">{{ $record->wikidata_id }}</a>
    @endif
</div>
