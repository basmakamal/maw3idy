<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureModels();
        $this->configureDates();
        $this->configureSecurity();
    }

    /**
     * Fail loudly outside production: lazy loads, missing attributes and
     * silently discarded fills all throw, so they are caught by the test suite.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Immutable dates everywhere. Availability math does a lot of arithmetic on
     * date objects; immutability removes an entire class of aliasing bugs.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    private function configureSecurity(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
