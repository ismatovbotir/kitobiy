<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Publisher;
use Illuminate\Http\RedirectResponse;

/**
 * Super admins hide a book, person or publisher from the public catalog pages, or show it again.
 */
class CatalogVisibilityController extends Controller
{
    public function __invoke(string $type, int $id): RedirectResponse
    {
        $record = match ($type) {
            'books' => Edition::query()->findOrFail($id),
            'people' => Author::query()->findOrFail($id),
            'publishers' => Publisher::query()->findOrFail($id),
        };

        $record->forceFill(['hidden_from_catalog' => ! $record->hidden_from_catalog])->save();

        return back()->with('status', $record->hidden_from_catalog
            ? __('Hidden from the public catalog.')
            : __('Shown in the public catalog again.'));
    }
}
