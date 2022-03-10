<?php

namespace Laravel\Vapor;

use Illuminate\Support\Facades\Route;

trait DefinesRoutes
{
    /**
     * Ensure that Vapor's internal routes are defined.
     *
     * @return void
     */
    public function ensureRoutesAreDefined()
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        if (config('vapor.signed_storage.enabled', true)) {
            Route::post(
                config('vapor.signed_storage.url', '/vapor/signed-storage-url'),
                Contracts\SignedStorageUrlController::class.'@store'
            )->middleware(config('vapor.middleware', 'web'));
        }
    }
}
