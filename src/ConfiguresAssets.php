<?php

namespace Laravel\Vapor;

use Illuminate\Support\Facades\Config;

trait ConfiguresAssets
{
    /**
     * Ensure the asset path is properly configured.
     *
     * @return void
     */
    protected function ensureAssetPathsAreConfigured()
    {
        // Ensure we are running on Vapor...
        if (! isset($_ENV['VAPOR_SSM_PATH'])) {
            return;
        }

        if (! Config::get('app.asset_url')) {
            Config::set('app.asset_url', $_ENV['ASSET_URL'] ?? '/');
        }

        if (! Config::get('app.mix_url')) {
            Config::set('app.mix_url', $_ENV['MIX_URL'] ?? '/');
        }
    }
}
