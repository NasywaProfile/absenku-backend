<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Blokir perintah destruktif (migrate:fresh, migrate:refresh, migrate:reset, db:wipe) di server produksi
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Paksa skema HTTPS pada URL yang dihasilkan server di environment produksi / saat APP_FORCE_HTTPS=true
        if ($this->app->isProduction() || config('absenku.force_https')) {
            URL::forceScheme('https');
        }
    }
}
