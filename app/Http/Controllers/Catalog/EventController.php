<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\BookEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Book events on the public site: upcoming first, past ones below. Drafts open
 * only for super admins, as a preview.
 */
class EventController extends Controller
{
    public function index(): View
    {
        return view('events.index', [
            'upcoming' => BookEvent::query()->published()->upcoming()->oldest('starts_at')->get(),
            'past' => BookEvent::query()->published()->past()->latest('starts_at')->paginate(BookEvent::PER_PAGE),
        ]);
    }

    public function show(BookEvent $bookEvent): View
    {
        abort_unless($bookEvent->is_published || Gate::allows('super-admin'), 404);

        $bookEvent->load('publisher', 'author', 'edition');

        return view('events.show', [
            'event' => $bookEvent,
            'related' => $bookEvent->relatedLinks(),
        ]);
    }
}
