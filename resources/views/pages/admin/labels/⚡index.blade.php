<?php

use App\Models\LabelTemplate;
use App\Support\Concerns\RequiresSuperAdmin;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use RequiresSuperAdmin;

    /**
     * @return Collection<int, LabelTemplate>
     */
    #[Computed]
    public function templates(): Collection
    {
        return LabelTemplate::query()->ordered()->get();
    }

    public function toggleActive(int $templateId): void
    {
        $template = LabelTemplate::findOrFail($templateId);
        $template->update(['is_active' => ! $template->is_active]);

        unset($this->templates);
    }

    public function duplicate(int $templateId): void
    {
        $copy = LabelTemplate::findOrFail($templateId)->replicate();
        $copy->name = __(':name (copy)', ['name' => $copy->name]);
        $copy->is_active = false;
        $copy->save();

        $this->redirectRoute('admin.labels.edit', $copy, navigate: true);
    }

    public function delete(int $templateId): void
    {
        LabelTemplate::whereKey($templateId)->delete();

        unset($this->templates);
        session()->flash('status', __('Label template deleted.'));
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title(__('Labels'));
    }
};
?>

<div>
    <x-flash class="mb-6" />

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-serif text-3xl font-semibold">{{ __('Label templates') }}</h1>
            <p class="mt-2 text-ink-soft">{{ __('Layouts that libraries will print on their books.') }}</p>
        </div>
        <a href="{{ route('admin.labels.create') }}" wire:navigate class="btn-primary w-auto">{{ __('New template') }}</a>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->templates as $template)
            <div wire:key="template-{{ $template->id }}" class="flex flex-col rounded-xl border border-ink/10 bg-white">
                <a href="{{ route('admin.labels.edit', $template) }}" wire:navigate
                   class="flex h-48 items-center justify-center overflow-hidden rounded-t-xl bg-paper p-4">
                    <x-label-preview :template="$template" :zoom="min(1, 60 / max($template->width_mm, $template->height_mm))" />
                </a>
                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <a href="{{ route('admin.labels.edit', $template) }}" wire:navigate class="font-semibold hover:text-accent">{{ $template->name }}</a>
                            <p class="text-sm text-ink-soft">{{ $template->sizeLabel() }} · {{ trans_choice(':count element|:count elements', count($template->elements)) }}</p>
                        </div>
                        <span @class([
                            'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-green-100 text-green-800' => $template->is_active,
                            'bg-ink/10 text-ink-soft' => ! $template->is_active,
                        ])>{{ $template->is_active ? __('Active') : __('Hidden') }}</span>
                    </div>
                    <div class="mt-auto flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <a href="{{ route('admin.labels.edit', $template) }}" wire:navigate class="link">{{ __('Edit') }}</a>
                        <button type="button" wire:click="duplicate({{ $template->id }})" class="link">{{ __('Duplicate') }}</button>
                        <button type="button" wire:click="toggleActive({{ $template->id }})" class="link">{{ $template->is_active ? __('Hide') : __('Show') }}</button>
                        <button type="button" wire:click="delete({{ $template->id }})" wire:confirm="{{ __('Delete this label template?') }}" class="font-medium text-red-700 hover:underline">{{ __('Delete') }}</button>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-ink-soft">{{ __('No label templates yet.') }}</p>
        @endforelse
    </div>
</div>
