<?php

declare(strict_types=1);

namespace App\Services;

use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;

/**
 * ModuleService — wrapper untuk nwidart/laravel-modules.
 *
 * Menyediakan API sederhana untuk:
 * - List modul dengan status (aktif/nonaktif/installed)
 * - Get module detail
 * - Cek apakah modul aktif
 *
 * Diadopsi dari `App_modules.php` PerfexCRM.
 * REF: docs/analisis-library-perfex.md (App_modules — ✅ ADOPT konsep)
 */
class ModuleService
{
    public function __construct(
        private RepositoryInterface $modules
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
     * @return array<string, mixed>
     */
    private function mapModule(Module $module): array
    {
        $composer = $module->json('composer.json')->all();
        $namespace = null;
        if (is_array($composer) && isset($composer['autoload']['psr-4'])) {
            $namespace = array_keys($composer['autoload']['psr-4'])[0] ?? null;
        }

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
