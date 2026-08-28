<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RelationTypeNotRegisteredException;
use Closure;

/**
 * RelationService — inti resolver relasi antar entity.
 *
 * Diadaptasi dari relation_helper.php PerfexCRM, TAPI hanya bagian inti:
 * core TIDAK tahu domain spesifik (customer/project/lead/...).
 *
 * Module (mis. Sales) yang mendefinisikan resolver per-tipe via HOOK:
 *   RelationService::registerResolver('customer', fn (int $id) => [...]);
 *
 * Core hanya:
 *   - menyimpan mapping type => resolver
 *   - memvalidasi bahwa type terdaftar (opt-in, cegah leakage)
 *   - memanggil resolver saat di-request
 *
 * REF: docs/analisis-helper-perfex.md (relation_helper.php — ADAPT)
 *      docs/porting-helper-implementasi.md (RelationService, via hook module)
 */
class RelationService
{
    /**
     * @var array<string, Closure(int):array<string, mixed>>
     */
    private array $resolvers = [];

    /**
     * Daftarkan resolver untuk tipe relasi tertentu.
     * Dipanggil oleh module melalui hook (ServiceProvider::boot).
     *
     * @param string $type  mis. 'customer', 'project', 'lead'
     * @param Closure(int $id): array<string, mixed> $resolver
     */
    public function registerResolver(string $type, Closure $resolver): void
    {
        $this->resolvers[strtolower($type)] = $resolver;
    }

    /**
     * List tipe relasi yang terdaftar (untuk introspeksi/UI).
     *
     * @return array<int, string>
     */
    public function knownTypes(): array
    {
        return array_keys($this->resolvers);
    }

    /**
     * Apakah tipe relasi terdaftar?
     */
    public function isRegistered(string $type): bool
    {
        return isset($this->resolvers[strtolower($type)]);
    }

    /**
     * Resolve entity by type + id.
     *
     * @param string $type
     * @param int $id
     * @return array<string, mixed>  data relasi (mis. ['id'=>, 'name'=>, 'type'=>])
     * @throws RelationTypeNotRegisteredException jika type tidak terdaftar
     */
    public function resolve(string $type, int $id): array
    {
        $type = strtolower($type);

        if (!isset($this->resolvers[$type])) {
            throw new RelationTypeNotRegisteredException($type);
        }

        return ($this->resolvers[$type])($id);
    }
}
