@if (session('status'))
    <p {{ $attributes->merge(['class' => 'rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 ring-1 ring-green-200']) }} role="status">{{ session('status') }}</p>
@endif
