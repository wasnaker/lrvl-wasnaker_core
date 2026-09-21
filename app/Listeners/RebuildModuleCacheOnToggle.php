<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Spine\Events\ModuleActivated;
use Spine\Events\ModuleDeactivated;

/**
 * Rebuild provider cache when a module is enabled/disabled so Laravel stops
 * loading the module's Service Provider (routes, controllers, models,
 * migrations) when disabled.
 *
 * WHY
 * nwidart's FileActivator only flips modules_statuses.json. The provider list
 * in bootstrap/cache/modules.php is generated separately (by
 * ModuleManifest::build() reading every module.json) and is NOT rebuilt by
 * enable/disable. Without this listener a disabled module's routes are still
 * registered — we observed /api/v1/provinces returning 200 even when Region
 * was disabled.
 *
 * HOW
 * Deleting bootstrap/cache/modules.php makes ModuleManifest::getProviders()
 * rebuild on the next boot, applying the activator filter. ProviderRepository
 * then recompiles services.php automatically (shouldRecompile = true).
 *
 * Trigger: ModuleActivated / ModuleDeactivated (from ModuleService).
 */
class RebuildModuleCacheOnToggle
{
    /**
     * Create the listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $files = app(Filesystem::class);

        $paths = [
            base_path('bootstrap/cache/modules.php'),
            $this->cachedServicesPath(),
        ];

        foreach ($paths as $path) {
            if ($path === '' || ! $files->exists($path)) {
                continue;
            }

            try {
                @unlink($path);
            } catch (\Throwable) {
                // jangan biarkan listener ini membatalkan request
            }
        }
    }

    /**
     * Resolve the cached services path the same way Laravel does.
     */
    private function cachedServicesPath(): string
    {
        try {
            return app()->getCachedServicesPath();
        } catch (\Throwable) {
            return '';
        }
    }
}