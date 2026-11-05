<?php

use App\Enums\LibraryRole;
use App\Models\Library;
use App\Models\LibraryInvitation;
use App\Models\User;
use App\Notifications\LibraryInvitationSent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

new class extends Component {
    /** Owners may hold this many open invitations at once, to limit spam. */
    public const int MAX_PENDING_INVITATIONS = 10;

    public string $libraryName = '';

    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    public function mount(): void
    {
        $library = $this->library;

        $this->authorize('view', $library);
        $this->libraryName = $library->name;
    }

    #[Computed]
    public function library(): Library
    {
        return auth()->user()->libraryOrCreateDefault();
    }

    /**
     * How many different books are kept off the gift page.
     */
    #[Computed]
    public function hiddenBooksCount(): int
    {
        return $this->library->copiesHiddenFromGiftPage()->distinct()->count('edition_id');
    }

    #[Computed]
    public function canManage(): bool
    {
        return auth()->user()->can('manageMembers', $this->library);
    }

    #[Computed]
    public function myRole(): ?LibraryRole
    {
        return $this->library->roleOf(auth()->user());
    }

    /**
     * Roles the signed-in user may give: owners give admin/member/viewer, admins member/viewer.
     *
     * @return list<LibraryRole>
     */
    #[Computed]
    public function assignableRoles(): array
    {
        return $this->myRole?->assignableRoles() ?? [];
    }

    /**
     * Whether the signed-in user may remove or re-role this member (never themselves).
     */
    public function canManageMember(User $member): bool
    {
        return $member->id !== auth()->id()
            && ($this->myRole?->canManage($member->membership->role) ?? false);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function members(): Collection
    {
        return $this->library->members()->orderBy('library_user.id')->get();
    }

    /**
     * @return Collection<int, LibraryInvitation>
     */
    #[Computed]
    public function pendingInvitations(): Collection
    {
        return $this->library->invitations()->pending()->latest('id')->get();
    }

    public function rename(): void
    {
        $this->authorize('update', $this->library);

        $validated = $this->validate([
            'libraryName' => ['required', 'string', 'max:120'],
        ]);

        $this->library->update(['name' => trim($validated['libraryName'])]);

        session()->flash('status', __('Library renamed.'));
    }

    /**
     * Create a single-use invitation. Without an email it is a link the owner
     * shares by hand (e.g. Telegram); with one, we also email the link.
     */
    public function invite(): void
    {
        $this->authorize('manageMembers', $this->library);

        $this->inviteEmail = mb_strtolower(trim($this->inviteEmail));

        $validated = $this->validate([
            'inviteEmail' => ['nullable', 'string', 'email', 'max:255'],
            'inviteRole' => ['required', Rule::in(array_map(fn (LibraryRole $role): string => $role->value, $this->assignableRoles))],
        ]);

        if ($this->pendingInvitations->count() >= self::MAX_PENDING_INVITATIONS) {
            throw ValidationException::withMessages([
                'inviteEmail' => __('You have too many open invitations. Revoke some first.'),
            ]);
        }

        $invitation = $this->library->invitations()->create([
            'invited_by' => auth()->id(),
            'email' => $validated['inviteEmail'] ?: null,
            'role' => LibraryRole::from($validated['inviteRole']),
        ]);

        $mailFailed = false;

        if ($invitation->email !== null) {
            try {
                Notification::route('mail', $invitation->email)
                    ->notify((new LibraryInvitationSent($invitation))->locale(app()->getLocale()));
            } catch (TransportExceptionInterface $exception) {
                report($exception);
                $mailFailed = true;
            }
        }

        $this->reset('inviteEmail', 'inviteRole');
        unset($this->pendingInvitations);

        session()->flash('status', match (true) {
            $invitation->email === null => __('Invitation link created. Copy it and send it to your family member.'),
            $mailFailed => __('Invitation created, but the email could not be sent. Copy the link and send it yourself.'),
            default => __('Invitation sent to :email.', ['email' => $invitation->email]),
        });
    }

    public function revoke(int $invitationId): void
    {
        $this->authorize('manageMembers', $this->library);

        $this->library->invitations()->pending()->whereKey($invitationId)->update(['revoked_at' => now()]);

        unset($this->pendingInvitations);
    }

    /**
     * Remove a member below the signed-in user's role. Owners cannot be
     * removed, so the library always keeps one.
     */
    public function removeMember(int $userId): void
    {
        $member = $this->manageableMember($userId);

        $this->library->members()->detach($member);

        unset($this->members);
    }

    /**
     * Give a member another role below the signed-in user's own.
     */
    public function changeRole(int $userId, string $role): void
    {
        $member = $this->manageableMember($userId);
        $newRole = LibraryRole::tryFrom($role);

        abort_unless($newRole !== null && in_array($newRole, $this->assignableRoles, true), 403);

        $this->library->members()->updateExistingPivot($member->id, ['role' => $newRole->value]);

        unset($this->members);

        session()->flash('status', __(':name is now :role.', ['name' => $member->name, 'role' => mb_strtolower($newRole->label())]));
    }

    private function manageableMember(int $userId): User
    {
        $this->authorize('manageMembers', $this->library);

        $member = $this->members->firstWhere('id', $userId);

        abort_if($member === null || ! $this->canManageMember($member), 403);

        return $member;
    }

    /**
     * Turn the gift page on, or give it a new link so the old one stops working.
     */
    public function shareWithNewLink(): void
    {
        $this->authorize('share', $this->library);

        $wasShared = $this->library->isShared();
        $this->library->shareWithNewLink();

        session()->flash('status', $wasShared
            ? __('New gift page link created. The old link no longer works.')
            : __('Gift page is on. Copy the link and send it to friends.'));
    }

    public function stopSharing(): void
    {
        $this->authorize('share', $this->library);

        $this->library->stopSharing();

        session()->flash('status', __('Gift page is off. The link no longer works.'));
    }

    public function leave(): void
    {
        $this->authorize('leave', $this->library);

        $this->library->members()->detach(auth()->user());

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return $this->view()->title(__('Members and invitations'));
    }
};
?>

<div class="max-w-2xl space-y-10">
    <div>
        <a href="{{ route('dashboard') }}" wire:navigate class="link text-sm">← {{ $this->library->name }}</a>
        <h1 class="mt-2 font-serif text-3xl font-semibold">{{ __('Members and invitations') }}</h1>

        <x-flash class="mt-4" />
    </div>

    @can('update', $this->library)
        <section>
            <h2 class="font-serif text-xl font-semibold">{{ __('Library name') }}</h2>
            <form wire:submit="rename" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                <div class="flex-1">
                    <label for="libraryName" class="sr-only">{{ __('Library name') }}</label>
                    <input wire:model="libraryName" id="libraryName" type="text" required maxlength="120" class="form-input">
                    @error('libraryName') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary sm:w-auto">{{ __('Save') }}</button>
            </form>
        </section>
    @endcan

    <section>
        <h2 class="font-serif text-xl font-semibold">{{ __('Members') }}</h2>
        <ul class="mt-3 divide-y divide-ink/10 rounded-lg border border-ink/10 bg-white">
            @foreach ($this->members as $member)
                <li wire:key="member-{{ $member->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <p class="font-medium">{{ $member->name }}</p>
                        <p class="text-sm text-ink-soft">{{ $member->email }}</p>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        @if ($this->canManageMember($member))
                            <label for="role-{{ $member->id }}" class="sr-only">{{ __('Role of :name', ['name' => $member->name]) }}</label>
                            <select id="role-{{ $member->id }}" class="rounded-lg border-ink/20 py-1 text-sm"
                                    wire:change="changeRole({{ $member->id }}, $event.target.value)">
                                @foreach ($this->assignableRoles as $role)
                                    <option value="{{ $role->value }}" @selected($member->membership->role === $role)>{{ $role->label() }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="removeMember({{ $member->id }})"
                                    wire:confirm="{{ __('Remove :name from the library?', ['name' => $member->name]) }}"
                                    class="text-red-700 hover:underline">{{ __('Remove') }}</button>
                        @else
                            <span class="text-ink-soft">{{ $member->membership->role->label() }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($this->canManage)
        <section>
            <h2 class="font-serif text-xl font-semibold">{{ __('Invite family') }}</h2>
            <p class="mt-1 text-sm text-ink-soft">{{ __('Each invitation works once and expires in 7 days. Leave the email empty to get a link you can send via Telegram.') }}</p>

            <form wire:submit="invite" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                <div class="flex-1">
                    <label for="inviteEmail" class="sr-only">{{ __('Email (optional)') }}</label>
                    <input wire:model="inviteEmail" id="inviteEmail" type="email" placeholder="{{ __('Email (optional)') }}" class="form-input">
                    @error('inviteEmail') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="inviteRole" class="sr-only">{{ __('Role') }}</label>
                    <select wire:model="inviteRole" id="inviteRole" class="form-input sm:w-auto">
                        @foreach ($this->assignableRoles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @error('inviteRole') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled">{{ __('Create invitation') }}</button>
            </form>

            <ul class="mt-3 space-y-1 text-xs text-ink-soft">
                @foreach ($this->assignableRoles as $role)
                    <li><span class="font-medium text-ink">{{ $role->label() }}</span> — {{ $role->description() }}</li>
                @endforeach
            </ul>

            @if ($this->pendingInvitations->isNotEmpty())
                <ul class="mt-4 space-y-3">
                    @foreach ($this->pendingInvitations as $invitation)
                        @php($invitationUrl = route('invitations.show', $invitation->token))
                        <li wire:key="invitation-{{ $invitation->id }}" class="rounded-lg border border-ink/10 bg-white px-4 py-3"
                            x-data="{ copied: false }">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium">{{ $invitation->email ?? __('Invitation link') }} · {{ $invitation->role->label() }}</span>
                                <span class="text-ink-soft">{{ __('Expires :date', ['date' => $invitation->expires_at->toDateString()]) }}</span>
                            </div>
                            <div class="mt-2 flex gap-2">
                                <input type="text" readonly value="{{ $invitationUrl }}" class="form-input text-sm" x-on:focus="$el.select()">
                                <button type="button" class="shrink-0 text-sm link"
                                        x-on:click="navigator.clipboard.writeText(@js($invitationUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                                    <span x-show="! copied">{{ __('Copy') }}</span>
                                    <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                                </button>
                                <button type="button" wire:click="revoke({{ $invitation->id }})" class="shrink-0 text-sm text-red-700 hover:underline">{{ __('Revoke') }}</button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @php($isShared = $this->library->isShared())
    <section id="gift-page" class="scroll-mt-6 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <div class="flex items-start gap-3 sm:gap-4">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent/10 text-accent" aria-hidden="true">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 11h16v9H4zM3 7h18v4H3zM12 7v13M12 7C10 3 6 3.5 6 5.5S9 7 12 7ZM12 7c2-4 6-3.5 6-1.5S15 7 12 7Z" />
                </svg>
            </span>
            <div class="min-w-0 grow">
                <h2 id="gift-page-title" class="font-serif text-xl font-semibold">{{ __('Gift page for friends') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">
                    {{ $isShared ? __('On. Anyone with the link can see your book list.') : __('Off. Only members of this library can see your books.') }}
                </p>
            </div>
            @can('share', $this->library)
                <button type="button" role="switch" aria-checked="{{ $isShared ? 'true' : 'false' }}" aria-labelledby="gift-page-title"
                        wire:click="{{ $isShared ? 'stopSharing' : 'shareWithNewLink' }}"
                        @if ($isShared) wire:confirm="{{ __('Turn off the gift page? The link will stop working, and turning it on again creates a new link.') }}" @endif
                        wire:loading.attr="disabled"
                        class="group flex h-11 shrink-0 cursor-pointer items-center rounded-full outline-none disabled:opacity-60">
                    <span @class([
                        'flex h-7 w-12 items-center rounded-full p-0.5 transition-colors group-focus-visible:ring-2 group-focus-visible:ring-accent group-focus-visible:ring-offset-2',
                        'justify-end bg-accent' => $isShared,
                        'justify-start bg-ink/30' => ! $isShared,
                    ])>
                        <span class="size-6 rounded-full bg-white shadow-sm"></span>
                    </span>
                </button>
            @endcan
        </div>

        @if ($isShared)
            @php($shareUrl = $this->library->shareUrl())
            <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2" x-data="{ copied: false }">
                <input type="text" readonly value="{{ $shareUrl }}" class="form-input min-w-0 basis-full text-sm sm:flex-1 sm:basis-0" x-on:focus="$el.select()" aria-label="{{ __('Gift page link') }}">
                <button type="button" class="link shrink-0 text-sm"
                        x-on:click="navigator.clipboard.writeText(@js($shareUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-show="! copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                </button>
                <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="link shrink-0 text-sm">{{ __('Open') }}</a>
                @can('share', $this->library)
                    <button type="button" wire:click="shareWithNewLink"
                            wire:confirm="{{ __('Create a new link? The old link will stop working.') }}"
                            class="link shrink-0 text-sm">{{ __('New link') }}</button>
                @endcan
            </div>
        @else
            <ul class="mt-5 grid gap-4 sm:grid-cols-3">
                <li>
                    <h3 class="font-medium">{{ __('No repeat gifts') }}</h3>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Friends see what you already own, so they don’t buy it twice.') }}</p>
                </li>
                <li>
                    <h3 class="font-medium">{{ __('A quick check in the shop') }}</h3>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Standing in a bookshop, they can search your list to see if you have a book.') }}</p>
                </li>
                <li>
                    <h3 class="font-medium">{{ __('You stay in control') }}</h3>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Turn it off or change the link at any time. The old link then stops working.') }}</p>
                </li>
            </ul>
            @cannot('share', $this->library)
                <p class="mt-4 text-sm text-ink-soft">{{ __('The gift page is off. The owner or an administrator can turn it on.') }}</p>
            @endcannot
        @endif

        <dl class="mt-5 grid gap-3 rounded-lg bg-paper p-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-medium">{{ __('Friends will see') }}</dt>
                <dd class="mt-1 text-ink-soft">{{ __('Library name, your wishlist, and each book’s cover, title, author, year and language') }}</dd>
            </div>
            <div>
                <dt class="font-medium">{{ __('Friends will never see') }}</dt>
                <dd class="mt-1 text-ink-soft">{{ __('Notes, tags, library members or books you hide') }}</dd>
            </div>
        </dl>
        <p class="mt-3 text-sm text-ink-soft">
            {{ __('To keep a book off the page, open it and choose “Hide this book”.') }}
            @if ($this->hiddenBooksCount > 0)
                <a href="{{ route('books.index', ['hidden' => 1]) }}" wire:navigate class="link">{{ trans_choice(':count book hidden|:count books hidden', $this->hiddenBooksCount) }}</a>
            @endif
        </p>
        <p class="mt-3 text-xs text-ink-soft">{{ __('Anyone who has the link can open the page, so send it only to people you trust. We ask search engines not to list it.') }}</p>
    </section>

    @can('leave', $this->library)
        <section>
            <button type="button" wire:click="leave" wire:confirm="{{ __('Leave this library?') }}"
                    class="text-sm text-red-700 hover:underline">{{ __('Leave library') }}</button>
        </section>
    @endcan
</div>
