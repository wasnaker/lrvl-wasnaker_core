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
 * @group api/v1
     * @subgroup Modules
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

    /**
     * Install modul baru dari upload zip.
     *
     * Zip wajib berisi `module.json` (di root atau dalam satu folder
     * top-level). Modul langsung di-enable dan migrasinya dijalankan.
     * Diadopsi dari `App_module_installer.php` PerfexCRM.
     *
     * @authenticated
     *
     * @bodyParam file file required File zip modul (maks 20MB).
     *
     * @response status=200 scenario=success {
     *   "message": "Module 'Demo' installed",
     *   "data": {"name":"Demo","enabled":true}
     * }
     * @response status=422 scenario=invalid {
     *   "message": "File zip modul tidak valid atau modul sudah terinstall"
     * }
     */
    public function install(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:20480'],
        ]);

        $module = $this->modules->installFromZip($request->file('file')->getRealPath());

        if (!$module) {
            return response()->json([
                'message' => 'File zip modul tidak valid atau modul sudah terinstall',
            ], 422);
        }

        return response()->json([
            'message' => "Module '{$module['name']}' installed",
            'data'    => $module,
        ]);
    }

    /**
     * Uninstall modul: nonaktifkan; hapus file hanya dengan ?purge=1.
     *
     * @authenticated
     *
     * @urlParam name string required Nama modul. Example: demo
     * @queryParam purge boolean Hapus direktori modul dari disk. Example: false
     *
     * @response scenario=success {"message":"Module 'demo' uninstalled","purge":false}
     * @response status=404 scenario=not-found {"message":"Module not found"}
     */
    public function uninstall(string $name, Request $request): JsonResponse
    {
        $purge = filter_var($request->query('purge', false), FILTER_VALIDATE_BOOL);

        $ok = $this->modules->uninstall($name, $purge);

        if (!$ok) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        return response()->json([
            'message' => "Module '{$name}' uninstalled",
            'purge'   => $purge,
        ]);
    }
}