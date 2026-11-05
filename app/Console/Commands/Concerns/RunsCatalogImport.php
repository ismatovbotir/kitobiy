<?php

namespace App\Console\Commands\Concerns;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\ImportRun;
use App\Services\CatalogImporter;
use InvalidArgumentException;

/**
 * Shared body of the catalog:import-* commands.
 */
trait RunsCatalogImport
{
    protected function runImport(ImportType $type): int
    {
        if (ImportRun::isRunning($type)) {
            $this->components->warn("A {$type->value} import is already running.");

            return self::FAILURE;
        }

        try {
            $run = app(CatalogImporter::class)->run($type, array_values($this->option('country')));
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($run->status === ImportStatus::Failed) {
            $this->components->error("Import failed: {$run->error}");

            return self::FAILURE;
        }

        $this->components->info("Imported {$type->value}: {$run->created_count} new, {$run->updated_count} updated.");

        return self::SUCCESS;
    }
}
