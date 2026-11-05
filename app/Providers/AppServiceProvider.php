<?php

namespace App\Providers;

use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceHttps($this->app->isProduction());

        // Links must never follow a Host header chosen by the visitor: they end up
        // in emails and in QR codes printed on paper, which can't be corrected.
        if ($this->app->isProduction()) {
            URL::forceRootUrl(config('app.url'));
        }

        Relation::morphMap([
            'listing' => Listing::class,
            'message' => Message::class,
        ]);

        // The /admin area: shared reference data (authors, publishers, genres) and imports.
        Gate::define('super-admin', fn (User $user): bool => $user->isSuperAdmin());

        // Livewire updates re-run the page's `verified` middleware too.
        Livewire::addPersistentMiddleware([EnsureEmailIsVerified::class]);
    }
}
