@php
    $localeNames = ['uz' => 'O‘zbekcha', 'ru' => 'Русский', 'en' => 'English'];
@endphp

<nav {{ $attributes->merge(['class' => 'flex items-center gap-1 text-sm']) }} aria-label="{{ __('Language') }}">
    @foreach (config('app.supported_locales') as $locale)
        <form method="POST" action="{{ route('locale.update', $locale) }}">
            @csrf
            <button
                type="submit"
                lang="{{ $locale }}"
                @class([
                    'rounded-md px-2 py-1 transition',
                    'bg-ink/10 font-semibold text-ink' => app()->getLocale() === $locale,
                    'text-ink-soft hover:text-ink' => app()->getLocale() !== $locale,
                ])
                @if (app()->getLocale() === $locale) aria-current="true" @endif
            >{{ $localeNames[$locale] }}</button>
        </form>
    @endforeach
</nav>
