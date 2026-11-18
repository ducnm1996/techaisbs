<?php

namespace App\Providers;

use App\Services\PrivateUploads;
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
    public function boot(PrivateUploads $uploads): void
    {
        $uploads->migrateLegacy();
    }
}
