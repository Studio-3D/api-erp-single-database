<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            'App\Repositories\V1\Contracts\SocieteRepository',
            'App\Repositories\V1\SocieteRepositoryDefault'
        );
        $this->app->bind(
            'App\Services\V1\Contracts\SocieteService',
            'App\Services\V1\SocieteServiceDefault'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        if (env('PASSPORT_PRIVATE_KEY_BASE64')) {
            file_put_contents(
                storage_path('oauth-private.key'),
                base64_decode(env('PASSPORT_PRIVATE_KEY_BASE64'))
            );

            file_put_contents(
                storage_path('oauth-public.key'),
                base64_decode(env('PASSPORT_PUBLIC_KEY_BASE64'))
            );
        }
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
