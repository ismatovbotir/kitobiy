<?php

use App\Enums\ReportReason;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Report;
use App\Support\Concerns\RequiresMarket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A small "Report" form for a listing or a message. The reporter must be able to
 * see the content, and the moderator sees only that one item, never the whole chat.
 */
new class extends Component
{
    use RequiresMarket;

    #[Locked]
    public string $type = 'listing';

    #[Locked]
    public int $reportableId = 0;

    public string $reason = '';

    public string $details = '';

    public bool $sent = false;

    public function send(): void
    {
        $reportable = $this->reportable();

        $validated = $this->validate([
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:500'],
        ]);

        $limiterKey = 'reports:'.auth()->id();

        if (RateLimiter::tooManyAttempts($limiterKey, Report::MAX_PER_HOUR)) {
            $this->addError('reason', __('Too many reports. Please try again later.'));

            return;
        }

        RateLimiter::hit($limiterKey, 3600);

        Report::query()->firstOrCreate(
            [
                'reporter_id' => auth()->id(),
                'reportable_type' => $reportable->getMorphClass(),
                'reportable_id' => $reportable->getKey(),
            ],
            [
                'reason' => $validated['reason'],
                'details' => trim((string) $validated['details']) ?: null,
            ],
        );

        $this->sent = true;
    }

    private function reportable(): Model
    {
        $user = auth()->user();

        if ($this->type === 'message') {
            $message = Message::query()->with('conversation')->findOrFail($this->reportableId);

            abort_unless($message->conversation->involves($user) && $message->sender_id !== $user->getKey(), 403);

            return $message;
        }

        $listing = Listing::query()->findOrFail($this->reportableId);

        abort_unless($listing->seller_id !== $user->getKey() && $user->can('view', $listing), 403);

        return $listing;
    }
};
?>

<div>
    @if ($sent)
        <p class="text-xs text-ink-soft" role="status">{{ __('Thank you. Your report was sent.') }}</p>
    @else
        <details>
            <summary class="cursor-pointer text-xs text-ink-soft underline hover:text-ink">{{ __('Report') }}</summary>
            <form wire:submit="send" class="mt-2 space-y-2 rounded-lg border border-ink/10 bg-paper p-3 text-sm">
                @if ($type === 'message')
                    <p class="text-xs text-ink-soft">{{ __('The moderator will read this one message to check your report.') }}</p>
                @endif
                <div>
                    <label for="reason-{{ $type }}-{{ $reportableId }}" class="form-label">{{ __('What is wrong?') }}</label>
                    <select wire:model="reason" id="reason-{{ $type }}-{{ $reportableId }}" class="form-input">
                        <option value="">{{ __('Choose') }}</option>
                        @foreach (ReportReason::cases() as $reportReason)
                            <option wire:key="reason-{{ $reportReason->value }}" value="{{ $reportReason->value }}">{{ $reportReason->label() }}</option>
                        @endforeach
                    </select>
                    @error('reason') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="details-{{ $type }}-{{ $reportableId }}" class="form-label">{{ __('More details (optional)') }}</label>
                    <textarea wire:model="details" id="details-{{ $type }}-{{ $reportableId }}" rows="2" maxlength="500" class="form-input"></textarea>
                    @error('details') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled">{{ __('Send report') }}</button>
            </form>
        </details>
    @endif
</div>
