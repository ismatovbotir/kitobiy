<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RunsCatalogImport;
use App\Enums\ImportType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:import-authors {--country=* : ISO-2 country codes (default: all in config/catalog.php)}')]
#[Description('Import famous writers from Wikidata into the shared catalog')]
class ImportAuthors extends Command
{
    use RunsCatalogImport;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        return $this->runImport(ImportType::Authors);
    }
}
