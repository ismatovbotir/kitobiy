<?php

use App\Models\Copy;
use App\Models\LabelTemplate;
use App\Models\Library;
use App\Support\LabelSheet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Choose books and a label size, then print A4 sheets from the browser, which can
 * also save them as a PDF. No PDF library or server work is needed: labels are
 * drawn in real millimetres.
 */
new class extends Component
{
    use WithPagination;

    /** QR codes are made while the page renders, so one print job stays small. */
    public const int MAX_LABELS = 200;

    public const int PER_PAGE = 30;

    #[Url(as: 'book', except: 0)]
    public int $preselectedCopyId = 0;

    public string $search = '';

    /** @var list<string> */
    public array $selectedIds = [];

    public string $templateId = '';

    public int $skip = 0;

    public bool $showSheets = false;

    public function mount(): void
    {
        $this->authorize('addBooks', $this->library);

        $this->templateId = (string) $this->templates->first()?->id;

        if ($this->preselectedCopyId !== 0 && $this->library->copies()->whereKey($this->preselectedCopyId)->exists()) {
            $this->selectedIds = [(string) $this->preselectedCopyId];
        }
    }

    #[Computed]
    public function library(): Library
    {
        return auth()->user()->libraryOrCreateDefault();
    }

    /**
     * @return Collection<int, LabelTemplate>
     */
    #[Computed]
    public function templates(): Collection
    {
        return LabelTemplate::query()->active()->ordered()->get();
    }

    #[Computed]
    public function template(): ?LabelTemplate
    {
        return $this->templates->firstWhere('id', (int) $this->templateId);
    }

    #[Computed]
    public function copies(): LengthAwarePaginator
    {
        return $this->library->copies()
            ->matching($this->search)
            ->with('edition.authors')
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    public function selectAll(): void
    {
        $this->selectedIds = $this->library->copies()
            ->matching($this->search)
            ->latest('id')
            ->limit(self::MAX_LABELS)
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
        $this->showSheets = false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function prepare(): void
    {
        $this->authorize('addBooks', $this->library);

        $this->validate([
            'templateId' => ['required', Rule::in($this->templates->pluck('id')->map(fn (int $id): string => (string) $id)->all())],
            'selectedIds' => ['required', 'array', 'max:'.self::MAX_LABELS],
            'selectedIds.*' => ['integer', Rule::exists('copies', 'id')->where('library_id', $this->library->id)],
            'skip' => ['integer', 'min:0', 'max:'.($this->sheet->labelsPerPage() - 1)],
        ]);

        $this->showSheets = true;
    }

    #[Computed]
    public function sheet(): LabelSheet
    {
        return new LabelSheet($this->template?->width_mm ?? 38.1, $this->template?->height_mm ?? 21.2);
    }

    /**
     * @return list<list<array{x: float, y: float, item: Copy}>>
     */
    #[Computed]
    public function pages(): array
    {
        if (! $this->showSheets || $this->template === null) {
            return [];
        }

        $copies = $this->library->copies()
            ->whereKey(array_map('intval', $this->selectedIds))
            ->with(['edition.authors', 'edition.genres', 'library'])
            ->oldest('id')
            ->limit(self::MAX_LABELS)
            ->get();

        return $this->sheet->pages($copies, $this->skip);
    }

    public function render()
    {
        return $this->view()->title(__('Labels'));
    }
};
?>

<div>
    <style>
        @page { size: A4; margin: 0; }
        @media print {
            .label-page { break-after: page; box-shadow: none; outline: none; }
            .label-page:last-child { break-after: auto; }
            .label-page, .label-page * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>

    <div class="max-w-3xl print:hidden">
        <h1 class="font-serif text-2xl font-semibold">{{ __('Labels') }}</h1>
        <p class="mt-2 text-sm text-ink-soft">{{ __('Choose books, then print labels on A4 sticker sheets. The QR code on each label opens that book’s public page.') }}</p>

        @if ($this->templates->isEmpty())
            <p class="mt-4 rounded-xl border border-ink/10 bg-white px-4 py-6 text-center text-ink-soft">{{ __('No label sizes are available yet.') }}</p>
        @else
            <form wire:submit="prepare" class="mt-4 space-y-4 rounded-xl border border-ink/10 bg-white p-4 sm:p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="templateId" class="form-label">{{ __('Label size') }}</label>
                        <select wire:model.live="templateId" id="templateId" class="form-input">
                            @foreach ($this->templates as $labelTemplate)
                                <option wire:key="template-{{ $labelTemplate->id }}" value="{{ $labelTemplate->id }}">{{ $labelTemplate->name }}</option>
                            @endforeach
                        </select>
                        @error('templateId') <p class="form-error">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-ink-soft">{{ trans_choice(':count label per A4 sheet|:count labels per A4 sheet', $this->sheet->labelsPerPage()) }}</p>
                    </div>
                    <div>
                        <label for="skip" class="form-label">{{ __('Labels already used on the first sheet') }}</label>
                        <input wire:model="skip" id="skip" type="number" inputmode="numeric" min="0" max="{{ $this->sheet->labelsPerPage() - 1 }}" class="form-input">
                        @error('skip') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="search" class="form-label">{{ __('Books') }}</label>
                    <input wire:model.live.debounce.300ms="search" id="search" type="search" autocomplete="off" placeholder="{{ __('Search by title, author or ISBN') }}" class="form-input">
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                        <span class="font-medium">{{ trans_choice(':count book selected|:count books selected', count($selectedIds)) }}</span>
                        <button type="button" wire:click="selectAll" class="link">{{ __('Select all') }}</button>
                        @if ($selectedIds !== [])
                            <button type="button" wire:click="clearSelection" class="link">{{ __('Clear') }}</button>
                        @endif
                    </div>
                    @error('selectedIds') <p class="form-error">{{ $message }}</p> @enderror
                    @error('selectedIds.*') <p class="form-error">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-ink-soft">{{ __('Up to :count labels at a time.', ['count' => $this::MAX_LABELS]) }}</p>

                    <ul class="mt-2 max-h-80 divide-y divide-ink/10 overflow-y-auto rounded-lg border border-ink/10">
                        @forelse ($this->copies as $bookCopy)
                            <li wire:key="pick-{{ $bookCopy->id }}">
                                <label class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-paper">
                                    <input wire:model.live="selectedIds" type="checkbox" value="{{ $bookCopy->id }}" class="rounded border-ink/30 text-accent">
                                    <span class="min-w-0 grow truncate">
                                        {{ $bookCopy->edition->title }}
                                        <span class="text-ink-soft">{{ $bookCopy->edition->authors->pluck('name')->join(', ') }}</span>
                                    </span>
                                    <span class="shrink-0 font-mono text-xs text-ink-soft">{{ $bookCopy->shortBookId() }}</span>
                                </label>
                            </li>
                        @empty
                            <li class="px-3 py-4 text-center text-sm text-ink-soft">{{ __('No books match your search.') }}</li>
                        @endforelse
                    </ul>
                    <div class="mt-2">{{ $this->copies->links() }}</div>
                </div>

                <button type="submit" class="btn-primary sm:w-auto" wire:loading.attr="disabled" wire:target="prepare">{{ __('Show the sheets') }}</button>
            </form>
        @endif
    </div>

    @if ($this->pages !== [])
        <div class="mt-6 max-w-3xl print:hidden">
            <button type="button" x-on:click="window.print()" class="btn-primary sm:w-auto">{{ __('Print or save as PDF') }}</button>
            <p class="mt-2 text-sm text-ink-soft">{{ __('In the print window choose A4, scale 100% and no margins. Print one test sheet on plain paper first and hold it against a sticker sheet.') }}</p>
            <p class="mt-1 text-sm text-ink-soft">{{ __('Each QR code holds this site’s address, so print from the live site, not from a test copy.') }}</p>
        </div>

        <div class="mt-4 space-y-6 overflow-x-auto print:mt-0 print:block print:space-y-0 print:overflow-visible">
            @foreach ($this->pages as $page)
                <div wire:key="sheet-{{ $loop->index }}" class="label-page relative overflow-hidden bg-white shadow-sm ring-1 ring-ink/15 print:ring-0"
                     style="width: {{ LabelSheet::PAGE_WIDTH_MM }}mm; height: {{ LabelSheet::PAGE_HEIGHT_MM }}mm;">
                    @foreach ($page as $placed)
                        <div wire:key="placed-{{ $placed['item']->id }}" class="absolute" style="left: {{ $placed['x'] }}mm; top: {{ $placed['y'] }}mm;">
                            <x-label-preview :template="$this->template" :copy="$placed['item']" />
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>
