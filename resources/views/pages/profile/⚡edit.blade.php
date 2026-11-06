<?php

use App\Models\CollectorProfile;
use App\Models\User;
use App\Rules\NoContactDetails;
use App\Services\Telegram;
use App\Support\Country;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The user's own collector profile. The name is typed here on purpose instead
 * of starting from the account name, so nobody publishes their full name by accident.
 */
new class extends Component
{
    public string $displayName = '';

    public string $bio = '';

    public string $countryCode = '';

    public string $region = '';

    public string $city = '';

    public string $birthDate = '';

    #[Locked]
    public string $telegramLink = '';

    public function mount(): void
    {
        $this->displayName = (string) $this->profile?->display_name;
        $this->bio = (string) $this->profile?->bio;

        $user = auth()->user();
        $this->countryCode = (string) $user->country_code;
        $this->region = (string) $user->region;
        $this->city = (string) $user->city;
        $this->birthDate = (string) $user->birth_date?->format('Y-m-d');
    }

    public function updatedCountryCode(): void
    {
        $this->region = '';
        $this->city = '';
    }

    /**
     * A city suggested for one Uzbek region makes no sense under another.
     */
    public function updatedRegion(): void
    {
        if ($this->countryCode === Country::UZBEKISTAN) {
            $this->city = '';
        }
    }

    /**
     * Private details, all optional. They are never shown to anyone else.
     */
    public function saveDetails(): void
    {
        $validated = $this->validate([
            'countryCode' => ['nullable', 'string', Rule::in(Country::CODES)],
            'region' => [
                'nullable', 'string', 'max:100',
                ...($this->countryCode === Country::UZBEKISTAN ? [Rule::in(array_keys(Country::UZBEK_REGIONS))] : []),
            ],
            'city' => ['nullable', 'string', 'max:100'],
            'birthDate' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
        ], [
            'birthDate.before' => __('Date of birth must be in the past.'),
            'birthDate.after' => __('Please check the year of birth.'),
        ]);

        $hasCountry = filled($validated['countryCode']);

        auth()->user()->update([
            'country_code' => $hasCountry ? $validated['countryCode'] : null,
            'region' => $hasCountry ? (trim((string) $validated['region']) ?: null) : null,
            'city' => $hasCountry ? (trim((string) $validated['city']) ?: null) : null,
            'birth_date' => filled($validated['birthDate']) ? $validated['birthDate'] : null,
        ]);

        session()->flash('status', __('Changes saved.'));
    }

    #[Computed]
    public function profile(): ?CollectorProfile
    {
        return auth()->user()->collectorProfile()->first();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'displayName' => ['required', 'string', 'max:60', new NoContactDetails],
            'bio' => ['nullable', 'string', 'max:500', new NoContactDetails],
        ]);

        auth()->user()->collectorProfile()->updateOrCreate([], [
            'display_name' => trim($validated['displayName']),
            'bio' => trim((string) $validated['bio']) ?: null,
        ]);

        unset($this->profile);

        session()->flash('status', __('Changes saved.'));
    }

    public function turnOn(): void
    {
        if ($this->profile === null) {
            $this->addError('displayName', __('Save your profile first.'));

            return;
        }

        $this->profile->shareWithNewLink();

        unset($this->profile);

        session()->flash('status', __('Profile is on. Copy the link and send it to people you choose.'));
    }

    public function turnOff(): void
    {
        $this->profile?->stopSharing();

        unset($this->profile);

        session()->flash('status', __('Profile is off. The link no longer works.'));
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function blockedUsers(): Collection
    {
        return auth()->user()->blockedUsers()->with('collectorProfile')->get();
    }

    public function unblock(int $userId): void
    {
        auth()->user()->blockedUsers()->detach($userId);

        unset($this->blockedUsers);
    }

    #[Computed]
    public function telegramAvailable(): bool
    {
        return app(Telegram::class)->isConfigured();
    }

    public function connectTelegram(): void
    {
        abort_unless($this->telegramAvailable, 404);

        $this->telegramLink = auth()->user()->newTelegramLink(app(Telegram::class)->botUsername());
    }

    public function disconnectTelegram(): void
    {
        auth()->user()->disconnectTelegram();

        $this->telegramLink = '';

        session()->flash('status', __('Telegram disconnected.'));
    }

    public function render()
    {
        return $this->view()->title(__('Collector profile'));
    }
};
?>

<div class="max-w-2xl">
    <h1 class="font-serif text-2xl font-semibold">{{ __('Collector profile') }}</h1>
    <p class="mt-2 text-sm text-ink-soft">{{ __('A small page about you as a book collector. It is off until you turn it on, and only people with the link can open it. It never shows your email or your account name.') }}</p>

    <x-flash class="mt-4" />

    <form wire:submit="save" class="mt-4 space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <div>
            <label for="displayName" class="form-label">{{ __('Name to show') }}</label>
            <input wire:model="displayName" id="displayName" type="text" required maxlength="60" class="form-input">
            @error('displayName') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="bio" class="form-label">{{ __('About you and your books') }}</label>
            <textarea wire:model="bio" id="bio" rows="3" maxlength="500" placeholder="{{ __('e.g. I collect Uzbek poetry and old dictionaries') }}" class="form-input"></textarea>
            @error('bio') <p class="form-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled" wire:target="save">{{ __('Save') }}</button>
    </form>

    <section class="mt-6 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        @if ($this->profile?->isShared())
            @php($profileUrl = $this->profile->shareUrl())
            <p class="text-sm">{{ __('On. Anyone with the link can see your name and text above.') }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2" x-data="{ copied: false }">
                <input type="text" readonly value="{{ $profileUrl }}" aria-label="{{ __('Collector profile') }}" class="form-input min-w-0 basis-full text-sm sm:flex-1 sm:basis-0" x-on:focus="$el.select()">
                <button type="button" class="link shrink-0 text-sm"
                        x-on:click="navigator.clipboard.writeText(@js($profileUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-show="! copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                </button>
                <a href="{{ $profileUrl }}" target="_blank" rel="noopener" class="link shrink-0 text-sm">{{ __('Open') }}</a>
            </div>
            <button type="button" wire:click="turnOff" wire:loading.attr="disabled"
                    wire:confirm="{{ __('Turn off your profile? The link will stop working, and turning it on again creates a new link.') }}"
                    class="mt-4 text-sm font-medium text-red-700 hover:underline">{{ __('Turn off') }}</button>
        @else
            <p class="text-sm">{{ __('Off. Nobody can see your profile.') }}</p>
            <button type="button" wire:click="turnOn" wire:loading.attr="disabled" @disabled($this->profile === null)
                    class="mt-3 min-h-11 cursor-pointer rounded-lg border border-accent px-3 text-sm font-medium text-accent hover:bg-accent hover:text-white disabled:cursor-not-allowed disabled:opacity-50">
                {{ __('Turn on') }}
            </button>
            @if ($this->profile === null)
                <p class="mt-2 text-xs text-ink-soft">{{ __('Save your profile first.') }}</p>
            @endif
        @endif
    </section>

    <form wire:submit="saveDetails" class="mt-6 space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <div>
            <h2 class="font-serif text-xl font-semibold">{{ __('About you') }}</h2>
            <p class="mt-1 text-sm text-ink-soft">{{ __('All of this is optional. Other members and visitors never see it.') }}</p>
        </div>

        <div>
            <label for="countryCode" class="form-label">{{ __('Country') }}</label>
            <select wire:model.live="countryCode" id="countryCode" class="form-input">
                <option value="">{{ __('Choose a country') }}</option>
                @php($countryOptions = Country::options())
                @foreach ($countryOptions['popular'] as $code => $countryName)
                    <option value="{{ $code }}" wire:key="country-{{ $code }}">{{ $countryName }}</option>
                @endforeach
                <optgroup label="{{ __('Other countries') }}">
                    @foreach ($countryOptions['others'] as $code => $countryName)
                        <option value="{{ $code }}" wire:key="country-{{ $code }}">{{ $countryName }}</option>
                    @endforeach
                </optgroup>
            </select>
            @error('countryCode') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        @if ($countryCode !== '')
            @php($regionOptions = Country::regionOptions($countryCode))
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="region" class="form-label">{{ __('Region') }}</label>
                    @if ($regionOptions !== [])
                        <select wire:model.live="region" id="region" class="form-input">
                            <option value="">{{ __('Choose a region') }}</option>
                            @foreach ($regionOptions as $code => $regionName)
                                <option value="{{ $code }}" wire:key="region-{{ $code }}">{{ $regionName }}</option>
                            @endforeach
                        </select>
                    @else
                        <input wire:model="region" id="region" type="text" maxlength="100" class="form-input">
                    @endif
                    @error('region') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="city" class="form-label">{{ __('City') }}</label>
                    <input wire:model="city" id="city" type="text" maxlength="100" list="city-suggestions" autocomplete="off" class="form-input">
                    <datalist id="city-suggestions">
                        @foreach (Country::citySuggestions($region) as $cityName)
                            <option value="{{ $cityName }}"></option>
                        @endforeach
                    </datalist>
                    @error('city') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        @endif

        <div class="sm:max-w-xs">
            <label for="birthDate" class="form-label">{{ __('Date of birth') }}</label>
            <input wire:model="birthDate" id="birthDate" type="date" min="1900-01-02" max="{{ now()->subDay()->format('Y-m-d') }}" autocomplete="bday" class="form-input">
            @error('birthDate') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled" wire:target="saveDetails">{{ __('Save') }}</button>
    </form>

    @if ($this->telegramAvailable)
        <section class="mt-6 rounded-xl border border-ink/10 bg-white p-4 sm:p-6" @if ($telegramLink !== '' && auth()->user()->telegram_chat_id === null) wire:poll.5s @endif>
            <h2 class="font-serif text-xl font-semibold">{{ __('Telegram') }}</h2>
            @if (auth()->user()->telegram_chat_id !== null)
                <p class="mt-1 text-sm">{{ __('Connected. You get notices about messages, found books and overdue loans there, and you can log in with Telegram.') }}</p>
                <button type="button" wire:click="disconnectTelegram" class="mt-3 text-sm font-medium text-red-700 hover:underline">{{ __('Disconnect Telegram') }}</button>
            @elseif ($telegramLink !== '')
                <p class="mt-1 text-sm">{{ __('Open the bot and press Start. The link works for 15 minutes.') }}</p>
                <a href="{{ $telegramLink }}" target="_blank" rel="noopener" class="btn-primary mt-3 sm:w-auto">{{ __('Open the bot') }}</a>
            @else
                <p class="mt-1 text-sm text-ink-soft">{{ __('Get notices about messages, found books and overdue loans in Telegram, and log in with it.') }}</p>
                <button type="button" wire:click="connectTelegram" wire:loading.attr="disabled"
                        class="mt-3 min-h-11 cursor-pointer rounded-lg border border-accent px-3 text-sm font-medium text-accent hover:bg-accent hover:text-white">{{ __('Connect Telegram') }}</button>
            @endif
        </section>
    @endif

    @if ($this->blockedUsers->isNotEmpty())
        <section class="mt-6 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
            <h2 class="font-serif text-xl font-semibold">{{ __('People you blocked') }}</h2>
            <ul class="mt-2 divide-y divide-ink/10">
                @foreach ($this->blockedUsers as $blockedUser)
                    <li wire:key="blocked-{{ $blockedUser->id }}" class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span class="min-w-0 truncate">{{ $blockedUser->marketName() }}</span>
                        <button type="button" wire:click="unblock({{ $blockedUser->id }})" class="link shrink-0">{{ __('Unblock') }}</button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
