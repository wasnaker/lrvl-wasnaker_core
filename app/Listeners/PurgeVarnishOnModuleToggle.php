<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spine\Events\ModuleActivated;
use Spine\Events\ModuleDeactivated;

/**
 * Purge Varnish cache otomatis saat module di-enable/disable.
 *
 * Trigger: ModuleActivated / ModuleDeactivated (dari ModuleService).
 * Target: semua pattern di config('varnish.purge_patterns').
 *
 * Tidak perlu Varnish CLI — gunakan HTTP PURGE/BAN via varnishadm HTTP API
 * (Varnish 6+ supports PURGE/BAN via HTTP).
 */
class PurgeVarnishOnModuleToggle
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
        if (! config('varnish.enabled', false)) {
            return;
        }

        $url = $this->purgeUrl();
        if ($url === null) {
            return;
        }

        $patterns = config('varnish.purge_patterns', []);
        if ($patterns === []) {
            return;
        }

        $module = $event->name ?? 'all';

        foreach ($patterns as $pattern) {
            try {
                Http::withHeaders([
                    'X-Purge-Reason' => 'module:' . $module,
                ])->timeout(5)
                    ->request('PURGE', $url . $pattern);
            } catch (ConnectionException) {
                // Varnish tidak reachable — jangan gagal request
                report('Varnish purge gagal: ' . $pattern);
            }
        }
    }

    /**
     * Resolve Varnish purge base URL.
     *
     * Prioritas: VARNISH_PURGE_URL > http://{HOST}:{PORT}
     */
    private function purgeUrl(): ?string
    {
        $url = config('varnish.purge_url');
        if (is_string($url) && $url !== '') {
            return rtrim($url, '/');
        }

        $host = config('varnish.host');
        if (! is_string($host) || $host === '') {
            return null;
        }

        $port = config('varnish.port', 6082);

        return sprintf('http://%s:%s', $host, $port);
    }
}