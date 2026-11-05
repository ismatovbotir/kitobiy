<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Reads publishers and famous writers of a country from the Wikidata SPARQL endpoint.
 * Failures throw (Illuminate\Http\Client\RequestException / ConnectionException);
 * the importer records them on the import run.
 *
 * Labels come back keyed by our alias language tags: uz-Latn, uz-Cyrl, ru, en.
 */
class Wikidata
{
    /**
     * @return list<array{wikidata_id: string, labels: array<string, string>, city: string|null, website: string|null}>
     */
    public function publishers(string $countryQid): array
    {
        $sparql = <<<SPARQL
            SELECT ?item ?label_uz ?label_uzc ?label_ru ?label_en ?website ?hqLabel WHERE {
              ?item wdt:P31/wdt:P279* wd:Q2085381 ;
                    wdt:P17 wd:{$countryQid} .
              {$this->labelClauses()}
              OPTIONAL { ?item wdt:P856 ?website . }
              OPTIONAL { ?item wdt:P159 ?hq . }
              SERVICE wikibase:label { bd:serviceParam wikibase:language "uz,ru,en" . ?hq rdfs:label ?hqLabel . }
            }
            SPARQL;

        return $this->groupByItem($this->select($sparql))
            ->map(fn (array $rows, string $qid): array => [
                'wikidata_id' => $qid,
                'labels' => $this->labels($rows[0]),
                'city' => $this->cleanCity($rows[0]['hqLabel'] ?? null),
                'website' => $rows[0]['website'] ?? null,
            ])
            ->filter(fn (array $publisher): bool => $publisher['labels'] !== [])
            ->values()
            ->all();
    }

    /**
     * Writers (writer, poet, novelist, playwright) with this citizenship or who wrote
     * in this language, and an article in at least $minSitelinks Wikipedias.
     *
     * @param  list<string>  $occupationQids
     * @return list<array{wikidata_id: string, labels: array<string, string>, birth_year: int|null, death_year: int|null}>
     */
    public function writers(string $countryQid, ?string $languageQid, int $minSitelinks, array $occupationQids): array
    {
        $occupations = implode(' ', array_map(fn (string $qid): string => "wd:{$qid}", $occupationQids));
        $belongs = $languageQid === null
            ? "?item wdt:P27 wd:{$countryQid} ."
            : "{ ?item wdt:P27 wd:{$countryQid} . } UNION { ?item wdt:P1412 wd:{$languageQid} . }";

        $sparql = <<<SPARQL
            SELECT ?item ?label_uz ?label_uzc ?label_ru ?label_en ?birth ?death WHERE {
              {
                SELECT DISTINCT ?item WHERE {
                  VALUES ?occupation { {$occupations} }
                  ?item wdt:P106 ?occupation .
                  {$belongs}
                  ?item wikibase:sitelinks ?sitelinks .
                  FILTER(?sitelinks >= {$minSitelinks})
                }
              }
              {$this->labelClauses()}
              OPTIONAL { ?item wdt:P569 ?birth . }
              OPTIONAL { ?item wdt:P570 ?death . }
            }
            SPARQL;

        return $this->groupByItem($this->select($sparql))
            ->map(fn (array $rows, string $qid): array => [
                'wikidata_id' => $qid,
                'labels' => $this->labels($rows[0]),
                'birth_year' => $this->year($rows[0]['birth'] ?? null),
                'death_year' => $this->year($rows[0]['death'] ?? null),
            ])
            ->filter(fn (array $writer): bool => $writer['labels'] !== [])
            ->values()
            ->all();
    }

    /**
     * Run a SELECT query; each result row is a flat array of variable => value.
     *
     * @return list<array<string, string>>
     */
    private function select(string $sparql): array
    {
        $response = Http::timeout(config('services.wikidata.timeout'))
            ->withUserAgent(config('app.name').'/1.0 ('.config('app.url').') catalog import')
            ->accept('application/sparql-results+json')
            ->asForm()
            ->post(config('services.wikidata.url'), ['query' => $sparql])
            ->throw();

        return collect($response->json('results.bindings', []))
            ->map(fn (array $binding): array => array_map(fn (array $cell): string => (string) $cell['value'], $binding))
            ->all();
    }

    private function labelClauses(): string
    {
        return <<<'SPARQL'
            OPTIONAL { ?item rdfs:label ?label_uz . FILTER(LANG(?label_uz) = "uz") }
              OPTIONAL { ?item rdfs:label ?label_uzc . FILTER(LANG(?label_uzc) = "uz-cyrl") }
              OPTIONAL { ?item rdfs:label ?label_ru . FILTER(LANG(?label_ru) = "ru") }
              OPTIONAL { ?item rdfs:label ?label_en . FILTER(LANG(?label_en) = "en") }
            SPARQL;
    }

    /**
     * Rows grouped by item QID (an item repeats when it has several websites, dates…).
     *
     * @param  list<array<string, string>>  $rows
     * @return Collection<string, list<array<string, string>>>
     */
    private function groupByItem(array $rows): Collection
    {
        return collect($rows)
            ->filter(fn (array $row): bool => isset($row['item']))
            ->groupBy(fn (array $row): string => basename($row['item']))
            ->map(fn (Collection $group): array => $group->values()->all());
    }

    /**
     * Labels keyed by alias language. Wikidata's "uz" label is sometimes written
     * in Cyrillic, so the script decides the tag.
     *
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function labels(array $row): array
    {
        $labels = [];

        foreach (['label_uz', 'label_uzc'] as $key) {
            $label = trim($row[$key] ?? '');

            if ($label !== '') {
                $labels[preg_match('/\p{Cyrillic}/u', $label) ? 'uz-Cyrl' : 'uz-Latn'] ??= $this->naturalOrder($label);
            }
        }

        foreach (['ru', 'en'] as $language) {
            $label = trim($row["label_{$language}"] ?? '');

            if ($label !== '') {
                $labels[$language] = $this->naturalOrder($label);
            }
        }

        return $labels;
    }

    /**
     * Library-style "Surname, Name" labels become "Name Surname".
     */
    private function naturalOrder(string $label): string
    {
        if (preg_match('/^([^,\d()]+),\s*([^,\d()]+)$/u', $label, $matches)) {
            return trim($matches[2]).' '.trim($matches[1]);
        }

        return $label;
    }

    private function year(?string $dateTime): ?int
    {
        if ($dateTime === null || ! preg_match('/^(\d{1,4})-/', $dateTime, $matches)) {
            return null;
        }

        $year = (int) $matches[1];

        return $year >= 1 && $year <= (int) now()->year ? $year : null;
    }

    /**
     * The label service returns the QID itself when a place has no label.
     */
    private function cleanCity(?string $city): ?string
    {
        $city = trim((string) $city);

        return $city === '' || preg_match('/^Q\d+$/', $city) ? null : mb_substr($city, 0, 255);
    }
}
