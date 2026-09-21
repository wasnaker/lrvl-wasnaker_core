<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Spine\Events\ModuleActivated;
use Spine\Events\ModuleDeactivated;

/**
 * Reload Octane (Swoole) workers after a module toggle so the route
 * collection (kept in worker memory) reflects the new module state.
 *
 * WHY
 * Octane (Swoole) keeps the route collection in each worker's memory.
 * Deleting bootstrap/cache/modules.php only takes effect on the next boot —
 * which never happens under Octane unless the workers are reloaded. Without
 * this, a module disabled via the API still serves its routes (we observed
 * /api/v1/provinces returning 200 right after disable).
 *
 * NOTE: Artisan::call('octane:reload') does NOT work inside an Octane
 * worker — the command is not registered in the worker's console kernel,
 * and the OctaneServiceProvider binding (ServerProcessInspector) is not
 * registered in this project's bootstrap/app.php either. We replicate what
 * the CLI does: read the master PID from the Octane state file and send it
 * SIGUSR1 (Swoole reload signal).
 *
 * Trigger: ModuleActivated / ModuleDeactivated (from ModuleService).
 */
class ReloadOctaneOnModuleToggle
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
        $stateFile = $this->stateFilePath();

        if ($stateFile === '' || ! is_file($stateFile)) {
            Log::warning('ReloadOctaneOnModuleToggle: Octane state file not found', [
                'event' => $event::class,
            ]);

            return;
        }

        $masterPid = $this->readMasterPid($stateFile);

        if ($masterPid === null || $masterPid <= 0) {
            Log::warning('ReloadOctaneOnModuleToggle: could not read master PID', [
                'event' => $event::class,
            ]);

            return;
        }

        if (! function_exists('posix_kill')) {
            Log::error('ReloadOctaneOnModuleToggle: posix_kill unavailable', [
                'event' => $event::class,
            ]);

            return;
        }

        $sent = @posix_kill($masterPid, SIGUSR1);

        if (! $sent) {
            Log::warning('ReloadOctaneOnModuleToggle: SIGUSR1 not sent', [
                'event'     => $event::class,
                'masterPid' => $masterPid,
            ]);
        }
    }

    /**
     * Resolve the Octane state file path.
     */
    private function stateFilePath(): string
    {
        try {
            $path = config('octane.server_state_file');

            if (is_string($path) && $path !== '') {
                return $path;
            }
        } catch (\Throwable) {
            // config not available (e.g. running outside HTTP context)
        }

        return base_path('storage/logs/octane-server-state.json');
    }

    /**
     * Read the master process PID from the Octane state file.
     */
    private function readMasterPid(string $stateFile): ?int
    {
        $contents = @file_get_contents($stateFile);

        if ($contents === false || $contents === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return null;
        }

        $pid = $decoded['masterProcessId'] ?? null;

        return is_int($pid) ? $pid : (is_numeric($pid) ? (int) $pid : null);
    }
}