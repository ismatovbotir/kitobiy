<?php

use App\Models\User;
use App\Services\Telegram;
use App\Support\Concerns\RequiresSuperAdmin;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use RequiresSuperAdmin;

    /**
     * What Telegram knows about the webhook, asked again on every page update.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function webhookInfo(): ?array
    {
        return app(Telegram::class)->webhookInfo();
    }

    /**
     * "here" when Telegram sends updates to this site, "elsewhere" for another address, else "none".
     */
    #[Computed]
    public function webhookState(): string
    {
        $url = $this->webhookInfo['url'] ?? '';

        return match (true) {
            $url === '' => 'none',
            $url === app(Telegram::class)->webhookUrl() => 'here',
            default => 'elsewhere',
        };
    }

    #[Computed]
    public function connectedUsers(): int
    {
        return User::query()->whereNotNull('telegram_chat_id')->count();
    }

    /**
     * Point the bot at this site. Safe to press again, e.g. after APP_KEY or the domain changed.
     */
    public function setWebhook(): void
    {
        $telegram = app(Telegram::class);

        if (($problem = $telegram->webhookProblem()) !== null) {
            $this->addError('webhook', $problem);

            return;
        }

        if (! $telegram->registerWebhook()) {
            $this->addError('webhook', __('Telegram did not accept the webhook. Check the token and see the log.'));

            return;
        }

        unset($this->webhookInfo, $this->webhookState);
        session()->flash('status', __('Webhook set. The bot now answers on this site.'));
    }

    /**
     * Send a message to TELEGRAM_ADMIN_ID, to see that the bot can write.
     */
    public function sendTestMessage(): void
    {
        $telegram = app(Telegram::class);
        $chatId = $telegram->adminChatId();

        if (! $telegram->hasToken() || $chatId === null) {
            $this->addError('test', __('Set TELEGRAM_BOT_TOKEN and TELEGRAM_ADMIN_ID in the .env file first.'));

            return;
        }

        $response = $telegram->sendMessage($chatId, __('Kitobiy bot works. This test message was sent from the admin panel.'));

        if ($response?->json('ok') !== true) {
            $this->addError('test', __('Telegram did not deliver it. Open the bot from the admin account and press Start first, then try again.'));

            return;
        }

        session()->flash('status', __('Test message sent.'));
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title(__('Telegram'));
    }
};
?>

@php($telegram = app(\App\Services\Telegram::class))

<div>
    <x-flash class="mb-6" />

    <h1 class="font-serif text-3xl font-semibold">{{ __('Telegram') }}</h1>
    <p class="mt-2 text-ink-soft">{{ __('The bot confirms new accounts, sends notices and logs people in. The token and the admin id are kept in the .env file only.') }}</p>

    <dl class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-ink/10 bg-white px-4 py-3">
            <dt class="text-sm text-ink-soft">{{ __('Bot token') }}</dt>
            <dd class="mt-1 font-medium">{{ $telegram->hasToken() ? __('Set') : __('Missing') }}</dd>
        </div>
        <div class="rounded-xl border border-ink/10 bg-white px-4 py-3">
            <dt class="text-sm text-ink-soft">{{ __('Bot') }}</dt>
            <dd class="mt-1 font-medium break-all">{{ $telegram->botUsername() ? '@'.$telegram->botUsername() : __('Unknown') }}</dd>
        </div>
        <div class="rounded-xl border border-ink/10 bg-white px-4 py-3">
            <dt class="text-sm text-ink-soft">{{ __('Admin id') }}</dt>
            <dd class="mt-1 font-medium">{{ $telegram->adminChatId() !== null ? __('Set') : __('Missing') }}</dd>
        </div>
        <div class="rounded-xl border border-ink/10 bg-white px-4 py-3">
            <dt class="text-sm text-ink-soft">{{ __('Connected accounts') }}</dt>
            <dd class="mt-1 font-medium tabular-nums">{{ $this->connectedUsers }}</dd>
        </div>
    </dl>

    <section class="mt-10 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <h2 class="font-serif text-2xl font-semibold">{{ __('Webhook') }}</h2>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Telegram sends messages for the bot to this address:') }} <code class="break-all">{{ $telegram->webhookUrl() }}</code></p>

        <p class="mt-3 text-sm">
            @switch($this->webhookState)
                @case('here')
                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">{{ __('Set to this site') }}</span>
                    @break
                @case('elsewhere')
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Set to another address') }}</span>
                    <code class="ml-1 break-all text-xs">{{ $this->webhookInfo['url'] }}</code>
                    @break
                @default
                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Not set') }}</span>
            @endswitch
        </p>

        @if (filled($this->webhookInfo['last_error_message'] ?? null))
            <p class="mt-2 text-sm text-red-700">
                {{ __('Last error from Telegram:') }} {{ $this->webhookInfo['last_error_message'] }}
                @if (isset($this->webhookInfo['last_error_date']))
                    <span class="text-ink-soft">({{ \Illuminate\Support\Carbon::createFromTimestamp($this->webhookInfo['last_error_date'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }})</span>
                @endif
            </p>
        @endif
        @if (($this->webhookInfo['pending_update_count'] ?? 0) > 0)
            <p class="mt-1 text-sm text-ink-soft">{{ __('Messages waiting: :count', ['count' => $this->webhookInfo['pending_update_count']]) }}</p>
        @endif

        @error('webhook') <p class="form-error mt-3">{{ $message }}</p> @enderror

        <button type="button" wire:click="setWebhook" wire:loading.attr="disabled" class="btn-primary mt-4 sm:w-auto">{{ __('Set webhook') }}</button>
        <p class="mt-3 text-xs text-ink-soft">{{ __('Press it again after changing the domain or APP_KEY. For the login button, also send /setdomain to @BotFather.') }}</p>
    </section>

    <section class="mt-6 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
        <h2 class="font-serif text-2xl font-semibold">{{ __('Test message') }}</h2>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Sends a short message to the admin id, to check that the bot can write.') }}</p>

        @error('test') <p class="form-error mt-3">{{ $message }}</p> @enderror

        <button type="button" wire:click="sendTestMessage" wire:loading.attr="disabled"
                class="mt-4 min-h-11 cursor-pointer rounded-lg border border-accent px-3 text-sm font-medium text-accent hover:bg-accent hover:text-white">{{ __('Send test message') }}</button>
    </section>
</div>
