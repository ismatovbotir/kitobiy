@props(['record', 'type'])

{{-- Super admins only: hide this record from the public catalog pages or show it again. --}}
@can('super-admin')
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-3 rounded-lg border border-dashed border-accent/40 bg-white px-3 py-2 text-sm']) }}>
        <span class="font-medium text-accent">{{ __('Admin') }}</span>
        @if ($record->hidden_from_catalog)
            <span class="text-red-700">{{ __('Hidden from the public catalog.') }}</span>
        @elseif (! $record->isPubliclyListed())
            <span class="text-ink-soft">{{ __('Not public: only super admins see this page.') }}</span>
        @endif
        <form method="POST" action="{{ route('admin.catalog.visibility', [$type, $record->id]) }}">
            @csrf
            <button type="submit" class="link">{{ $record->hidden_from_catalog ? __('Show in the catalog') : __('Hide from the catalog') }}</button>
        </form>
    </div>
@endcan
