<?php

namespace App\Jobs;

use App\Enums\ImportType;
use App\Models\User;
use App\Services\CatalogImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs a Wikidata import. The admin "Run now" button dispatches it after the
 * response, so the page does not wait for Wikidata (QUEUE_CONNECTION=sync).
 */
class ImportCatalog implements ShouldQueue
{
    use Queueable;

    /** Wikidata queries for all countries can take several minutes. */
    public int $timeout = 1800;

    /**
     * @param  list<string>  $countries
     */
    public function __construct(public ImportType $type, public array $countries = [], public ?int $triggeredBy = null) {}

    /**
     * Execute the job.
     */
    public function handle(CatalogImporter $importer): void
    {
        @set_time_limit($this->timeout);

        $importer->run($this->type, $this->countries, $this->triggeredBy ? User::find($this->triggeredBy) : null);
    }
}
