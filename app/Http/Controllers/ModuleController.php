<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API untuk manajemen modul (discover, status, enable/disable).
 *
 * Diadopsi dari `App_modules.php` PerfexCRM.
 * Hanya super-admin yang boleh enable/disable modul.
 *
 * @group Modules
 */
class ModuleController extends Controller
{
    public function __construct(
        private ModuleService $modules
    ) {}

    /**
     * List semua modul dengan status.
     *
     * @authenticated
     *
     * @response scenario=success {
     *   "data": [
     *     {"name":"Sales","alias":"sales","enabled":true,"installed":true,"namespace":"Modules\\Sales"}
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->modules->all()]);
    }

    /**
     * List hanya modul yang aktif (enabled).
     *
     * @authenticated
     */
    public function enabled(): JsonResponse
    {
        return response()->json(['data' => $this->modules->enabled()]);
    }

    /**
     * Detail modul by name/alias.
     *
     * @authenticated
     *
     * @urlParam name string required Nama atau alias modul. Example: sales
     *
     * @response scenario=success {
     *   "name":"Sales","alias":"sales","enabled":true,"installed":true,
     *   "namespace":"Modules\\Sales","providers":[],"aliases":[]
     * }
     * @response status=404 scenario=not-found {"message":"Module not found"}
     */
    public function show(string $name): JsonResponse
    {
        $module = $this->modules->find($name);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        return response()->json($module);
    }

    /**
     * Aktifkan modul.
     *
     * @authenticated
     *
     * @urlParam name string required Nama modul. Example: sales
     *
     * @response scenario=success {"message":"Module 'sales' enabled","enabled":true}
     * @response status=404 scenario=not-found {"message":"Module not found"}
     */
    public function enable(string $name): JsonResponse
    {
        $enabled = $this->modules->enable($name);

        if (!$enabled) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        return response()->json(['message' => "Module '{$name}' enabled", 'enabled' => true]);
    }

    /**
     * Nonaktifkan modul.
     *
     * @authenticated
     *
     * @urlParam name string required Nama modul. Example: sales
     *
     * @response scenario=success {"message":"Module 'sales' disabled","enabled":false}
     * @response status=404 scenario=not-found {"message":"Module not found"}
     */
    public function disable(string $name): JsonResponse
    {
        $disabled = $this->modules->disable($name);

        if (!$disabled) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        return response()->json(['message' => "Module '{$name}' disabled", 'enabled' => false]);
    }

    /**
     * Cek status modul (enabled/installed).
     *
     * @authenticated
     *
     * @urlParam name string required Nama modul. Example: sales
     *
     * @response scenario=success {"name":"sales","enabled":true,"installed":true}
     * @response status=404 scenario=not-found {"message":"Module not found"}
     */
    public function status(string $name): JsonResponse
    {
        $module = $this->modules->find($name);

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        return response()->json([
            'name'      => $module['name'],
            'enabled'   => $module['enabled'],
            'installed' => $module['installed'],
        ]);
    }
}