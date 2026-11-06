<?php

namespace App\Support\Concerns;

/**
 * For Livewire market and message components: while the market is switched off,
 * every request (first render and each action) answers as if the page did not
 * exist, so a page left open in a browser can't keep posting either.
 */
trait RequiresMarket
{
    public function bootRequiresMarket(): void
    {
        abort_unless(config('market.enabled'), 404);
    }
}
