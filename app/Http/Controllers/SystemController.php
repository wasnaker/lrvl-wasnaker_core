<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

/**
 * API utilitas sistem (info aplikasi, bahasa tersedia).
 *
 * Diadopsi dari sisa `App.php` PerfexCRM (`get_available_languages`).
 * `get_available_reminders_keys` sengaja TIDAK di-port ke core — reminder
 * adalah domain per-modul (mis. Sales: invoice_due_reminder), bukan
 * cross-cutting.
 * REF: docs/analisis-library-perfex.md (App.php — ADAPT)
 *
 * @group api/v1
 *     * @subgroup System
 */
class SystemController extends Controller
{
    /**
     * Daftar bahasa (locale) yang tersedia di aplikasi.
     *
     * Memindai direktori `lang/` (konvensi Laravel 12) + selalu menyertakan
     * `app.locale` default. Belum ada folder lang → hanya locale default.
     *
     * @authenticated
     *
     * @response scenario=success {
     *   "data": ["id", "en"]
     * }
     */
    public function languages(): JsonResponse
    {
        $locales = [];

        $langDir = base_path('lang');
        if (is_dir($langDir)) {
            foreach (File::directories($langDir) as $dir) {
                $locales[] = basename($dir);
            }
        }

        $default = (string) config('app.locale');
        if (! in_array($default, $locales, true)) {
            array_unshift($locales, $default);
        }

        return response()->json(['data' => array_values(array_unique($locales))]);
    }
}
