<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\AuthorAlias;
use App\Models\Edition;
use App\Models\Publisher;
use App\Models\PublisherAlias;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

#[Signature('kitobiy:rebuild-search-keys')]
#[Description('Recompute the folded search keys of titles, people and publishers (run after changing App\Support\SearchKey)')]
class RebuildSearchKeys extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sources = [
            'editions' => [Edition::class, ['title', 'subtitle']],
            'authors' => [Author::class, ['name']],
            'author aliases' => [AuthorAlias::class, ['name']],
            'publishers' => [Publisher::class, ['name']],
            'publisher aliases' => [PublisherAlias::class, ['name']],
        ];

        foreach ($sources as $label => [$modelClass, $attributes]) {
            $changed = 0;

            $modelClass::query()->lazyById(500)->each(function (Model $model) use ($attributes, &$changed): void {
                foreach ($attributes as $attribute) {
                    $model->{$attribute} = $model->{$attribute};
                }

                if ($model->isDirty()) {
                    $model->timestamps = false;
                    $model->saveQuietly();
                    $changed++;
                }
            });

            $this->components->info("{$label}: {$changed} updated.");
        }

        return self::SUCCESS;
    }
}
