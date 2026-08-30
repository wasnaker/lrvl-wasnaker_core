<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;
use ZipArchive;

/**
 * ModuleService — wrapper untuk nwidart/laravel-modules.
 *
 * Menyediakan API sederhana untuk:
 * - List modul dengan status (aktif/nonaktif/installed)
 * - Get module detail
 * - Cek apakah modul aktif
 * - Install modul dari zip upload / uninstall (purge opsional)
 *
 * Diadopsi dari `App_modules.php` + `App_module_installer.php` PerfexCRM.
 * REF: docs/analisis-library-perfex.md (App_modules — ✅ ADOPT konsep;
 * App_module_installer — ⚠️ PARCIAL, dilengkapi di Batch 10)
 */
class ModuleService
{
    public function __construct(
        private RepositoryInterface $modules,
        private ActivatorInterface $activator
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        /** @var array<int, Module> $items */
        $items = $this->modules->all();

        return array_map(function (Module $module): array {
            return $this->mapModule($module);
        }, $items);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function enabled(): array
    {
        /** @var array<int, Module> $items */
        $items = $this->modules->allEnabled();

        return array_map(function (Module $module): array {
            return $this->mapModule($module);
        }, $items);
    }

    public function find(string $name): ?array
    {
        $module = $this->modules->find($name);

        if (!$module) {
            return null;
        }

        return $this->mapModule($module);
    }

    public function isEnabled(string $name): bool
    {
        return $this->modules->isEnabled($name);
    }

    public function isDisabled(string $name): bool
    {
        return $this->modules->isDisabled($name);
    }

    public function enable(string $name): bool
    {
        $module = $this->modules->find($name);

        if (!$module) {
            return false;
        }

        $module->enable();

        return true;
    }

    public function disable(string $name): bool
    {
        $module = $this->modules->find($name);

        if (!$module) {
            return false;
        }

        $module->disable();

        return true;
    }

    public function getPath(string $name): ?string
    {
        $module = $this->modules->find($name);

        return $module?->getPath();
    }

    /**
     * Install modul dari file zip (pola `App_module_installer` Perfex).
     *
     * Zip wajib berisi `module.json` (di root zip atau di dalam satu folder
     * top-level). Modul diekstrak ke `modules/<Name>/`, langsung di-enable,
     * lalu migrasi modul dijalankan (jika ada).
     *
     * @return array<string, mixed>|null detail modul, null jika gagal
     */
    public function installFromZip(string $zipPath): ?array
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $moduleJsonEntry = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (str_ends_with($entry, 'module.json')) {
                $moduleJsonEntry = $entry;
                break;
            }
        }

        if ($moduleJsonEntry === null) {
            $zip->close();

            return null;
        }

        $meta = json_decode((string) $zip->getFromName($moduleJsonEntry), true);
        $moduleName = is_array($meta) ? ($meta['name'] ?? null) : null;

        if (! is_string($moduleName) || $moduleName === '') {
            $zip->close();

            return null;
        }

        $targetDir = base_path('modules') . DIRECTORY_SEPARATOR . $moduleName;

        if (is_dir($targetDir)) {
            $zip->close();

            return null; // sudah terinstall
        }

        // Staging di DALAM modules/ (same filesystem → moveDirectory/rename aman;
        // /tmp bisa beda FS → EXDEV). modules/ harus writable oleh user FPM.
        $staging = base_path('modules') . DIRECTORY_SEPARATOR . '.staging-' . uniqid('mod_', true);
        if (! File::makeDirectory($staging, 0755, true)) {
            $zip->close();

            return null;
        }

        $zip->extractTo($staging);
        $zip->close();

        // Zip berisi satu folder top-level → pindahkan isinya; selain itu
        // pindahkan seluruh isi langsung ke target.
        $entries = array_values(array_diff(scandir($staging), ['.', '..']));
        if (count($entries) === 1 && is_dir($staging . '/' . $entries[0])) {
            File::moveDirectory($staging . '/' . $entries[0], $targetDir);
        } else {
            File::moveDirectory($staging, $targetDir);
        }
        File::deleteDirectory($staging, true);

        if (! is_file($targetDir . '/module.json')) {
            File::deleteDirectory($targetDir, true);

            return null;
        }

        // nwidart v12: Module::getPriority() strict-typed → TypeError kalau
        // 'priority' tidak ada di module.json (modul rusak bisa 500 di semua
        // list). Normalisasi default '0' supaya zip apa pun bisa terinstall.
        $moduleJsonPath = $targetDir . '/module.json';
        $moduleMeta = json_decode((string) file_get_contents($moduleJsonPath), true);
        if (is_array($moduleMeta) && ! isset($moduleMeta['priority'])) {
            $moduleMeta['priority'] = '0';
            file_put_contents($moduleJsonPath, json_encode($moduleMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        }

        // Reset cache static nwidart DULU, baru setEnabled — kalau tidak,
        // find() di dalam setEnabled masih kena daftar modul lama (tanpa
        // modul baru) → enable jadi no-op.
        $this->clearModuleCache();
        $this->setEnabled($moduleName, true);

        try {
            Artisan::call('module:migrate', ['module' => $moduleName]);
        } catch (\Throwable) {
            // modul tanpa migrasi / migrasi gagal tidak menggagalkan install
        }

        return $this->find($moduleName);
    }

    /**
     * Uninstall modul: nonaktifkan; dengan $purge=true direktori ikut dihapus.
     */
    public function uninstall(string $name, bool $purge = false): bool
    {
        $module = $this->modules->find($name);

        if (! $module) {
            return false;
        }

        $this->setEnabled($name, false);

        if ($purge) {
            $path = $module->getPath();
            if (is_dir($path)) {
                File::deleteDirectory($path);
            }
            $this->removeFromStatuses($name);
        }

        $this->clearModuleCache();

        return true;
    }

    /**
     * nwidart v12 meng-cache daftar modul di STATIC FileRepository::$modules
     * (dipakai scan() lintas request) + file bootstrap/cache/modules.php.
     * Setelah install/uninstall KEDUANYA harus dibersihkan, atau daftar modul
     * stale: find() → null (422), list berisi modul yang sudah dihapus.
     */
    private function clearModuleCache(): void
    {
        @unlink(base_path('bootstrap/cache/modules.php'));

        try {
            $prop = new \ReflectionProperty(\Nwidart\Modules\FileRepository::class, 'modules');
            $prop->setValue(null, []); // static property → object arg diabaikan
        } catch (\Throwable) {
            // versi nwidart lain — abaikan
        }
    }

    /**
     * Set status enabled/disabled via nwidart activator (in-memory + file).
     * JANGAN tulis modules_statuses.json langsung — FileActivator menyimpan
     * status di memori per-proses; tulis manual membuat hasStatus() stale.
     */
    private function setEnabled(string $name, bool $enabled): void
    {
        $module = $this->modules->find($name);
        if (! $module) {
            return;
        }

        $enabled ? $this->activator->enable($module) : $this->activator->disable($module);
    }

    private function removeFromStatuses(string $name): void
    {
        $module = $this->modules->find($name);
        if ($module) {
            $this->activator->delete($module);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapModule(Module $module): array
    {
        // nwidart v12: Module::json() THROWS FileNotFoundException kalau file
        // tidak ada (bukan return null) — modul malformed tidak boleh 500.
        try {
            $composerJson = $module->json('composer.json');
            $composer = $composerJson ? $composerJson->all() : [];
        } catch (\Throwable) {
            $composer = [];
        }
        $namespace = null;
        if (is_array($composer) && isset($composer['autoload']['psr-4'])) {
            $namespace = array_keys($composer['autoload']['psr-4'])[0] ?? null;
        }

        // nwidart v12 strict-typed: getPriority() TypeError kalau key tidak ada.
        $priority = '0';
        try {
            $priority = $module->getPriority();
        } catch (\Throwable) {
        }
        $priority = is_string($priority) ? $priority : '0';

        return [
            'name'       => $module->getName(),
            'studly'     => $module->getStudlyName(),
            'lower'      => $module->getLowerName(),
            'path'       => $module->getPath(),
            'namespace'  => $namespace,
            'enabled'    => $module->isEnabled(),
            'description' => $module->getDescription(),
            'priority'   => $module->getPriority(),
            'providers'  => $module->get('providers', []),
            'aliases'    => $module->get('aliases', []),
        ];
    }
}
