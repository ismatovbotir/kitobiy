<?php

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Jobs\ImportCatalog;
use App\Models\Author;
use App\Models\ImportRun;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Genre;
use App\Models\LabelTemplate;
use App\Models\Library;
use App\Models\Publisher;
use App\Models\User;
use App\Support\Concerns\RequiresSuperAdmin;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use RequiresSuperAdmin;

    /**
     * Headline numbers for the whole site.
     *
     * @return list<array{label: string, value: int, note: string|null, route: string|null}>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            ['label' => __('Users'), 'value' => User::count(), 'note' => null, 'route' => null],
            ['label' => __('Libraries'), 'value' => Library::count(), 'note' => null, 'route' => null],
            ['label' => __('Books in libraries'), 'value' => Copy::count(), 'note' => null, 'route' => null],
            ['label' => __('Catalog editions'), 'value' => Edition::count(), 'note' => null, 'route' => null],
            [
                'label' => __('People'),
                'value' => Author::count(),
                'note' => __(':count unverified', ['count' => Author::where('is_verified', false)->count()]),
                'route' => 'admin.authors.index',
            ],
            [
                'label' => __('Publishers'),
                'value' => Publisher::count(),
                'note' => __(':count unverified', ['count' => Publisher::where('is_verified', false)->count()]),
                'route' => 'admin.publishers.index',
            ],
            [
                'label' => __('Genres'),
                'value' => Genre::count(),
                'note' => __(':count active', ['count' => Genre::where('is_active', true)->count()]),
                'route' => 'admin.genres.index',
            ],
            [
                'label' => __('Label templates'),
                'value' => LabelTemplate::count(),
                'note' => __(':count active', ['count' => LabelTemplate::where('is_active', true)->count()]),
                'route' => 'admin.labels.index',
            ],
        ];
    }

    /**
     * The latest Wikidata import runs.
     *
     * @return Collection<int, ImportRun>
     */
    #[Computed]
    public function importRuns(): Collection
    {
        return ImportRun::query()->with('triggeredBy')->latest('started_at')->latest('id')->limit(10)->get();
    }

    /**
     * Start a Wikidata import now. It runs after the response is sent,
     * so the page does not wait; refresh to see the result.
     */
    public function runImport(string $type): void
    {
        $importType = ImportType::from($type);

        if (ImportRun::isRunning($importType)) {
            session()->flash('status', __('This import is already running.'));

            return;
        }

        ImportCatalog::dispatchAfterResponse($importType, [], auth()->id());

        session()->flash('status', __('Import started. It can take a few minutes; refresh the page to see the result.'));
    }

    public function render()
    {
        return $this->view()->layout('layouts::admin')->title(__('Admin'));
    }
};
?>

<div>
    <x-flash class="mb-6" />

    <h1 class="font-serif text-3xl font-semibold">{{ __('Overview') }}</h1>
    <p class="mt-2 text-ink-soft">{{ __('Shared catalog data for every library.') }}</p>

    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->stats as $stat)
            @php($href = $stat['route'] && Route::has($stat['route']) ? route($stat['route']) : null)
            <{{ $href ? 'a' : 'div' }}
                wire:key="stat-{{ $loop->index }}"
                @if ($href) href="{{ $href }}" wire:navigate @endif
                @class([
                    'rounded-xl border border-ink/10 bg-white px-4 py-3',
                    'transition hover:border-accent/40' => $href,
                ])>
                <span class="block font-serif text-2xl font-semibold">{{ number_format($stat['value'], 0, '.', ' ') }}</span>
                <span class="text-sm text-ink-soft">{{ $stat['label'] }}</span>
                @if ($stat['note'])
                    <span class="mt-1 block text-xs text-ink-soft">{{ $stat['note'] }}</span>
                @endif
            </{{ $href ? 'a' : 'div' }}>
        @endforeach
    </div>

    <section class="mt-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-serif text-2xl font-semibold">{{ __('Wikidata import') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Publishers and famous writers of Uzbekistan and the CIS. Runs every Monday night.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach (ImportType::cases() as $type)
                    <button type="button" wire:key="run-{{ $type->value }}" wire:click="runImport('{{ $type->value }}')" wire:loading.attr="disabled"
                            class="rounded-lg border border-ink/20 bg-white px-3 py-2 text-sm font-medium transition hover:border-accent/50">
                        {{ __('Import now: :type', ['type' => $type->label()]) }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($this->importRuns->isEmpty())
            <p class="mt-4 rounded-xl border border-ink/10 bg-white px-4 py-6 text-center text-ink-soft">{{ __('No imports yet.') }}</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-xl border border-ink/10 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-paper text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th class="px-4 py-2">{{ __('Started') }}</th>
                            <th class="px-4 py-2">{{ __('Type') }}</th>
                            <th class="px-4 py-2">{{ __('Status') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('New') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Updated') }}</th>
                            <th class="px-4 py-2">{{ __('Started by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->importRuns as $run)
                            <tr wire:key="run-{{ $run->id }}" class="border-t border-ink/10 align-top">
                                <td class="px-4 py-2 whitespace-nowrap">{{ $run->started_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-2">{{ $run->type->label() }} <span class="text-xs text-ink-soft">{{ implode(', ', $run->countries) }}</span></td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-green-100 text-green-800' => $run->status === ImportStatus::Succeeded,
                                        'bg-amber-100 text-amber-800' => $run->status === ImportStatus::Running,
                                        'bg-red-100 text-red-800' => $run->status === ImportStatus::Failed,
                                    ])>{{ $run->status->label() }}</span>
                                    @if ($run->error)
                                        <span class="mt-1 block max-w-md text-xs break-words text-red-700">{{ \Illuminate\Support\Str::limit($run->error, 200) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $run->created_count }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $run->updated_count }}</td>
                                <td class="px-4 py-2 text-ink-soft">{{ $run->triggeredBy?->name ?? __('Automatic') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
