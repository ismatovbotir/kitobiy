<?php

namespace App\Http\Controllers;

use App\Models\Library;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SwitchLibraryController extends Controller
{
    /**
     * Work in another of the user's libraries. Pages are about one library, so
     * this goes to its home page rather than back.
     */
    public function __invoke(Request $request, Library $library): RedirectResponse
    {
        Gate::authorize('view', $library);

        $request->user()->switchToLibrary($library);

        return redirect()->route('dashboard')
            ->with('status', __('You are now in :library.', ['library' => $library->name]));
    }
}
