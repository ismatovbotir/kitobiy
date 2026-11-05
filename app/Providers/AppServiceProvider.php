<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
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

        // The /admin area: shared reference data (authors, publishers, genres) and imports.
        Gate::define('super-admin', fn (User $user): bool => $user->isSuperAdmin());

        // Livewire updates re-run the page's `verified` middleware too.
        Livewire::addPersistentMiddleware([EnsureEmailIsVerified::class]);
    }
}
