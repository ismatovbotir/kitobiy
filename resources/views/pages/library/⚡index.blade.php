<?php

use App\Models\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $newLibraryName = '';

    /**
     * All the user's libraries, the one they work in first.
     *
     * @return Collection<int, Library>
     */
    #[Computed]
    public function libraries(): Collection
    {
        $currentId = $this->currentLibrary->id;

        return auth()->user()->libraries()
            ->withCount(['members', 'copies'])
            ->orderBy('library_user.id')
            ->get()
            ->sortBy(fn (Library $library): int => $library->id === $currentId ? 0 : 1)
            ->values();
    }

    #[Computed]
    public function currentLibrary(): Library
    {
        return auth()->user()->libraryOrCreateDefault();
    }

    /**
     * Create another library owned by the user and start working in it.
     */
    public function create(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $validated = $this->validate([
            'newLibraryName' => ['required', 'string', 'max:120'],
        ]);

        if (! $user->canCreateLibrary()) {
            throw ValidationException::withMessages([
                'newLibraryName' => __('You can own up to :count libraries.', ['count' => User::MAX_OWNED_LIBRARIES]),
            ]);
        }

        $library = $user->createLibrary(trim($validated['newLibraryName']));
        $user->switchToLibrary($library);

        session()->flash('status', __('Library “:library” created. You are working in it now.', ['library' => $library->name]));

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('My libraries'));
    }
};
?>

<div class="max-w-2xl space-y-10">
    <div>
        <a href="{{ route('dashboard') }}" wire:navigate class="link text-sm">← {{ $this->currentLibrary->name }}</a>
        <h1 class="mt-2 font-serif text-3xl font-semibold">{{ __('My libraries') }}</h1>
        <p class="mt-1 text-sm text-ink-soft">{{ __('For example, your home library and your grandparents’ library. You work in one at a time.') }}</p>

        <x-flash class="mt-4" />
    </div>

    <section>
        <ul class="divide-y divide-ink/10 rounded-lg border border-ink/10 bg-white">
            @foreach ($this->libraries as $library)
                @php($isCurrent = $library->id === $this->currentLibrary->id)
                <li wire:key="library-{{ $library->id }}" class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $library->name }}</p>
                        <p class="text-sm text-ink-soft">
                            {{ $library->membership->role->label() }}
                            · {{ trans_choice(':count book|:count books', $library->copies_count) }}
                            · {{ trans_choice(':count member|:count members', $library->members_count) }}
                        </p>
                    </div>

                    @if ($isCurrent)
                        <span class="shrink-0 rounded-full bg-accent/10 px-3 py-1 text-sm font-medium text-accent">{{ __('Current') }}</span>
                    @else
                        <form method="POST" action="{{ route('libraries.switch', $library) }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="link text-sm">{{ __('Open') }}</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    <section>
        <h2 class="font-serif text-xl font-semibold">{{ __('New library') }}</h2>
        <form wire:submit="create" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
            <div class="flex-1">
                <label for="newLibraryName" class="sr-only">{{ __('Library name') }}</label>
                <input wire:model="newLibraryName" id="newLibraryName" type="text" required maxlength="120"
                       placeholder="{{ __('Library name') }}" class="form-input">
                @error('newLibraryName') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled">{{ __('Create') }}</button>
        </form>
    </section>
</div>
