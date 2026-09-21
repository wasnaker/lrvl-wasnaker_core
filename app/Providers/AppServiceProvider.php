<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spine\Events\ModuleActivated;
use Spine\Events\ModuleDeactivated;
use App\Listeners\PurgeVarnishOnModuleToggle;
use App\Listeners\RebuildModuleCacheOnToggle;
use App\Listeners\ReloadOctaneOnModuleToggle;

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
     * Bootstrap the event listeners for this package.
     */
    protected function bootEvents(): void
    {
        $this->app['events']->listen(ModuleActivated::class, PurgeVarnishOnModuleToggle::class);
        $this->app['events']->listen(ModuleDeactivated::class, PurgeVarnishOnModuleToggle::class);

        $this->app['events']->listen(ModuleActivated::class, RebuildModuleCacheOnToggle::class);
        $this->app['events']->listen(ModuleDeactivated::class, RebuildModuleCacheOnToggle::class);

        $this->app['events']->listen(ModuleActivated::class, ReloadOctaneOnModuleToggle::class);
        $this->app['events']->listen(ModuleDeactivated::class, ReloadOctaneOnModuleToggle::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootEvents();

        // Super-admin (role "admin") melewati semua permission check.
        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole(config('spine.auth.super_admin_role', 'admin'))) {
                return true;
            }

            return null;
        });
    }
}