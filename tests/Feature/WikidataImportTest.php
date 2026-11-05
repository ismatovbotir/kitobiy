<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Jobs\ImportCatalog;
use App\Models\Author;
use App\Models\ImportRun;
use App\Models\Publisher;
use App\Models\User;
use App\Services\CatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WikidataImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{results: array{bindings: list<array<string, array{type: string, value: string}>>}}
     */
    private function sparqlResult(array $rows): array
    {
        return ['results' => ['bindings' => array_map(
            fn (array $row): array => array_map(fn (string $value): array => ['type' => 'literal', 'value' => $value], $row),
            $rows,
        )]];
    }

    public function test_writers_are_imported_verified_with_their_spellings(): void
    {
        Http::fake(['query.wikidata.org/*' => Http::response($this->sparqlResult([
            ['item' => 'http://www.wikidata.org/entity/Q1394012', 'label_uz' => 'Abdulla Qodiriy', 'label_ru' => 'Абдулла Кадыри', 'label_en' => 'Abdulla Qodiriy', 'birth' => '1894-04-10T00:00:00Z', 'death' => '1938-10-05T00:00:00Z'],
            ['item' => 'http://www.wikidata.org/entity/Q1394012', 'label_uz' => 'Abdulla Qodiriy', 'birth' => '1894-01-01T00:00:00Z'],
            ['item' => 'http://www.wikidata.org/entity/Q555', 'label_uz' => 'Чўлпон', 'label_ru' => 'Чулпан'],
            ['item' => 'http://www.wikidata.org/entity/Q556', 'label_uz' => 'Holmirzaev, Shukur', 'label_ru' => 'Шукур Холмирзаев'],
            ['item' => 'http://www.wikidata.org/entity/Q557', 'label_uz' => 'Gʻafur Gʻulom'],
        ]))]);

        $run = app(CatalogImporter::class)->run(ImportType::Authors, ['UZ']);

        $this->assertSame(ImportStatus::Succeeded, $run->status);
        $this->assertSame([4, 0], [$run->created_count, $run->updated_count]);

        $qodiriy = Author::where('wikidata_id', 'Q1394012')->sole();
        $this->assertSame(['Abdulla Qodiriy', 1894, 1938, 'UZ', true], [$qodiriy->name, $qodiriy->birth_year, $qodiriy->death_year, $qodiriy->country, $qodiriy->is_verified]);
        $this->assertSame(['Абдулла Кадыри' => 'ru'], $qodiriy->aliases()->pluck('language', 'name')->all());

        // A Cyrillic "uz" label is tagged uz-Cyrl, and the Russian label becomes the main name.
        $cholpon = Author::where('wikidata_id', 'Q555')->sole();
        $this->assertSame('Чулпан', $cholpon->name);
        $this->assertSame(['Чўлпон' => 'uz-Cyrl'], $cholpon->aliases()->pluck('language', 'name')->all());

        $this->assertSame('Shukur Holmirzaev', Author::where('wikidata_id', 'Q556')->value('name'));

        // A name typed with a plain apostrophe finds the imported person.
        $this->assertSame('Q557', Author::findByNameOrAlias("G'afur G'ulom")?->wikidata_id);

        Http::assertSent(fn (Request $request): bool => str_contains($request['query'], 'wd:Q265')
            && str_contains($request['query'], 'wd:Q9264')
            && str_contains($request['query'], 'FILTER(?sitelinks >= 3)')
            && str_contains($request->header('User-Agent')[0], config('app.name')));
    }

    public function test_reimport_links_typed_names_and_keeps_admin_fixes(): void
    {
        $typed = Author::factory()->create(['name' => 'Abdulla Qodiriy']);
        $fixed = Author::factory()->verified()->create(['name' => 'Fixed Name', 'wikidata_id' => 'Q2', 'birth_year' => 1900]);

        Http::fake(['query.wikidata.org/*' => Http::response($this->sparqlResult([
            ['item' => 'http://www.wikidata.org/entity/Q1', 'label_uz' => 'Abdulla Qodiriy', 'birth' => '1894-04-10T00:00:00Z'],
            ['item' => 'http://www.wikidata.org/entity/Q2', 'label_uz' => 'Wikidata Name', 'birth' => '1901-01-01T00:00:00Z', 'death' => '1950-01-01T00:00:00Z'],
        ]))]);

        $run = app(CatalogImporter::class)->run(ImportType::Authors, ['UZ']);

        $this->assertSame([0, 2], [$run->created_count, $run->updated_count]);
        $this->assertSame(2, Author::count());

        $typed->refresh();
        $this->assertSame(['Q1', true, 1894], [$typed->wikidata_id, $typed->is_verified, $typed->birth_year]);

        $fixed->refresh();
        $this->assertSame(['Fixed Name', 1900, 1950], [$fixed->name, $fixed->birth_year, $fixed->death_year]);
        $this->assertSame(['Wikidata Name'], $fixed->aliases()->pluck('name')->all());

        $again = app(CatalogImporter::class)->run(ImportType::Authors, ['UZ']);
        $this->assertSame([0, 0], [$again->created_count, $again->updated_count]);
    }

    public function test_publishers_are_imported_with_city_and_website(): void
    {
        Http::fake(['query.wikidata.org/*' => Http::response($this->sparqlResult([
            ['item' => 'http://www.wikidata.org/entity/Q100', 'label_uz' => 'Sharq', 'label_ru' => 'Шарк', 'website' => 'https://sharq.uz', 'hqLabel' => 'Toshkent'],
            ['item' => 'http://www.wikidata.org/entity/Q101', 'label_en' => 'Some House', 'website' => 'ftp://bad', 'hqLabel' => 'Q999'],
        ]))]);

        $run = app(CatalogImporter::class)->run(ImportType::Publishers, ['UZ']);

        $this->assertSame(2, $run->created_count);
        $sharq = Publisher::where('wikidata_id', 'Q100')->sole();
        $this->assertSame(['Sharq', 'Toshkent', 'https://sharq.uz', 'UZ', true], [$sharq->name, $sharq->city, $sharq->website, $sharq->country, $sharq->is_verified]);

        $house = Publisher::where('wikidata_id', 'Q101')->sole();
        $this->assertNull($house->city);
        $this->assertNull($house->website);
    }

    public function test_a_wikidata_failure_is_recorded_on_the_run(): void
    {
        Http::fake(['query.wikidata.org/*' => Http::response('Too many requests', 429)]);

        $run = app(CatalogImporter::class)->run(ImportType::Publishers, ['UZ']);

        $this->assertSame(ImportStatus::Failed, $run->status);
        $this->assertNotNull($run->error);
        $this->assertNotNull($run->finished_at);
    }

    public function test_commands_import_and_reject_unknown_countries_or_overlapping_runs(): void
    {
        Http::fake(['query.wikidata.org/*' => Http::response($this->sparqlResult([
            ['item' => 'http://www.wikidata.org/entity/Q100', 'label_uz' => 'Sharq'],
        ]))]);

        $this->artisan('catalog:import-publishers', ['--country' => ['uz']])
            ->expectsOutputToContain('1 new, 0 updated')
            ->assertSuccessful();

        $this->artisan('catalog:import-authors', ['--country' => ['XX']])
            ->expectsOutputToContain('Unknown import countries: XX')
            ->assertFailed();

        ImportRun::factory()->running()->create(['type' => ImportType::Authors]);

        $this->artisan('catalog:import-authors')->expectsOutputToContain('already running')->assertFailed();
    }

    public function test_every_configured_country_is_queried(): void
    {
        Http::fake(['query.wikidata.org/*' => Http::response($this->sparqlResult([]))]);

        app(CatalogImporter::class)->run(ImportType::Publishers);

        Http::assertSentCount(count(config('catalog.import_countries')));
    }

    public function test_admin_overview_lists_runs_and_starts_an_import(): void
    {
        Bus::fake();
        $admin = User::factory()->superAdmin()->create();
        ImportRun::factory()->create(['type' => ImportType::Authors, 'created_count' => 17, 'error' => null]);

        Livewire::actingAs($admin)
            ->test('pages::admin.dashboard')
            ->assertSee(__('Wikidata import'))
            ->assertSee('17')
            ->call('runImport', 'publishers');

        Bus::assertDispatchedAfterResponse(ImportCatalog::class, fn (ImportCatalog $job): bool => $job->type === ImportType::Publishers && $job->triggeredBy === $admin->id);
    }
}
