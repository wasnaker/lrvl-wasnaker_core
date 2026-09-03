<?php

declare(strict_types=1);

namespace Modules\Events\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Events\Listeners\LogEventActivity;

class EventsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // HOOK — entity lifecycle generic (HasLifecycleHooks):
        // EntityCreated/Updated/Deleted untuk Event (entity modul ini).
        Event::listen(\Spine\Events\EntityCreated::class, LogEventActivity::class . '@created');
        Event::listen(\Spine\Events\EntityUpdated::class, LogEventActivity::class . '@updated');
        Event::listen(\Spine\Events\EntityDeleted::class, LogEventActivity::class . '@deleted');
    }
}
