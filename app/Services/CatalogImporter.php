<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\Author;
use App\Models\ImportRun;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Brings publishers and famous writers from Wikidata into the shared catalog.
 *
 * Records are matched by wikidata_id, else by an unverified record with the same
 * name or alias (so a name a user typed gets linked instead of duplicated).
 * New records are created verified. Existing records keep their name and any
 * value a super admin set: the import only fills empty fields and adds spellings.
 */
class CatalogImporter
{
    public function __construct(private Wikidata $wikidata) {}

    /**
     * @param  list<string>  $countries  ISO-2 codes from config('catalog.import_countries'); empty = all
     */
    public function run(ImportType $type, array $countries = [], ?User $triggeredBy = null): ImportRun
    {
        $configured = config('catalog.import_countries');
        $countries = $countries === [] ? array_keys($configured) : array_map('strtoupper', $countries);

        $unknown = array_diff($countries, array_keys($configured));

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown import countries: '.implode(', ', $unknown));
        }

        $run = ImportRun::create([
            'type' => $type,
            'status' => ImportStatus::Running,
            'countries' => array_values($countries),
            'triggered_by' => $triggeredBy?->id,
            'started_at' => now(),
        ]);

        try {
            foreach ($countries as $country) {
                $settings = $configured[$country];

                $records = $type === ImportType::Publishers
                    ? $this->wikidata->publishers($settings['qid'])
                    : $this->wikidata->writers($settings['qid'], $settings['language_qid'], $settings['min_sitelinks'], config('catalog.writer_occupations'));

                foreach ($records as $record) {
                    $result = DB::transaction(fn (): string => $type === ImportType::Publishers
                        ? $this->upsertPublisher($record, $country)
                        : $this->upsertAuthor($record, $country));

                    if ($result !== 'unchanged') {
                        $run->increment("{$result}_count");
                    }
                }
            }

            $run->update(['status' => ImportStatus::Succeeded, 'finished_at' => now()]);
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Catalog import failed', ['run' => $run->id, 'error' => $exception->getMessage()]);

            $run->update([
                'status' => ImportStatus::Failed,
                'error' => mb_substr($exception->getMessage(), 0, 2000),
                'finished_at' => now(),
            ]);
        }

        return $run->refresh();
    }

    /**
     * @param  array{wikidata_id: string, labels: array<string, string>, city: string|null, website: string|null}  $record
     * @return 'created'|'updated'|'unchanged'
     */
    private function upsertPublisher(array $record, string $country): string
    {
        $publisher = $this->findExisting(Publisher::class, $record);

        if ($publisher === null) {
            $publisher = Publisher::create([
                'name' => $this->mainName($record['labels']),
                'country' => $country,
                'city' => $record['city'],
                'website' => $this->cleanWebsite($record['website']),
                'wikidata_id' => $record['wikidata_id'],
                'is_verified' => true,
            ]);
            $this->addSpellings($publisher, $record['labels']);

            return 'created';
        }

        $this->fillBlank($publisher, [
            'country' => $country,
            'city' => $record['city'],
            'website' => $this->cleanWebsite($record['website']),
            'wikidata_id' => $record['wikidata_id'],
        ]);

        return $this->saveAndAddSpellings($publisher, $record['labels']);
    }

    /**
     * @param  array{wikidata_id: string, labels: array<string, string>, birth_year: int|null, death_year: int|null}  $record
     * @return 'created'|'updated'|'unchanged'
     */
    private function upsertAuthor(array $record, string $country): string
    {
        $author = $this->findExisting(Author::class, $record);

        if ($author === null) {
            $author = Author::create([
                'name' => $this->mainName($record['labels']),
                'birth_year' => $record['birth_year'],
                'death_year' => $record['death_year'],
                'country' => $country,
                'wikidata_id' => $record['wikidata_id'],
                'is_verified' => true,
            ]);
            $this->addSpellings($author, $record['labels']);

            return 'created';
        }

        $this->fillBlank($author, [
            'birth_year' => $record['birth_year'],
            'death_year' => $record['death_year'],
            'country' => $country,
            'wikidata_id' => $record['wikidata_id'],
        ]);

        return $this->saveAndAddSpellings($author, $record['labels']);
    }

    /**
     * @template TModel of Author|Publisher
     *
     * @param  class-string<TModel>  $model
     * @param  array{wikidata_id: string, labels: array<string, string>}  $record
     * @return TModel|null
     */
    private function findExisting(string $model, array $record): ?Model
    {
        $byWikidata = $model::where('wikidata_id', $record['wikidata_id'])->first();

        if ($byWikidata !== null) {
            return $byWikidata;
        }

        foreach ($record['labels'] as $label) {
            $match = $model::findByNameOrAlias($label);

            if ($match !== null && $match->wikidata_id === null && ! $match->is_verified) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function fillBlank(Model $record, array $values): void
    {
        foreach ($values as $attribute => $value) {
            if (blank($record->{$attribute}) && filled($value)) {
                $record->{$attribute} = $value;
            }
        }

        $record->is_verified = true;
    }

    /**
     * @param  array<string, string>  $labels
     * @return 'updated'|'unchanged'
     */
    private function saveAndAddSpellings(Author|Publisher $record, array $labels): string
    {
        $changed = $record->isDirty();
        $record->save();

        $changed = $this->addSpellings($record, $labels) || $changed;

        return $changed ? 'updated' : 'unchanged';
    }

    /**
     * Add the labels that are not yet the name or an alias. Returns whether any was added.
     *
     * @param  array<string, string>  $labels
     */
    private function addSpellings(Author|Publisher $record, array $labels): bool
    {
        $existing = $record->aliases()->get(['name', 'language'])
            ->map(fn (Model $alias): array => ['name' => $alias->name, 'language' => $alias->language])
            ->all();

        $known = collect($existing)->pluck('name')->push($record->name)->map(fn (string $name): string => mb_strtolower($name));

        $new = collect($this->spellings($labels))
            ->reject(fn (array $alias): bool => $known->contains(mb_strtolower($alias['name'])))
            ->values()
            ->all();

        if ($new === []) {
            return false;
        }

        $record->syncAliases([...$existing, ...$new]);

        return true;
    }

    /**
     * The labels as alias rows, plus a plain-apostrophe variant of an Uzbek Latin
     * label: Wikidata writes "Choʻlpon" (ʻ), people type "Cho'lpon".
     *
     * @param  array<string, string>  $labels
     * @return list<array{name: string, language: string}>
     */
    private function spellings(array $labels): array
    {
        $spellings = [];

        foreach ($labels as $language => $name) {
            $spellings[] = ['name' => $name, 'language' => $language];

            if ($language === 'uz-Latn') {
                $plain = preg_replace('/[ʻʼ‘’`´]/u', "'", $name);

                if ($plain !== $name) {
                    $spellings[] = ['name' => $plain, 'language' => $language];
                }
            }
        }

        return $spellings;
    }

    /**
     * The main name: Uzbek (Latin) first, then Russian, English, Uzbek (Cyrillic).
     *
     * @param  array<string, string>  $labels
     */
    private function mainName(array $labels): string
    {
        return mb_substr($labels['uz-Latn'] ?? $labels['ru'] ?? $labels['en'] ?? $labels['uz-Cyrl'] ?? reset($labels), 0, 255);
    }

    private function cleanWebsite(?string $website): ?string
    {
        return $website !== null && preg_match('#^https?://#i', $website) && mb_strlen($website) <= 2048 ? $website : null;
    }
}
