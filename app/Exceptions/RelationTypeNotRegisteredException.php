<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown ketika RelationService.resolve() dipanggil untuk tipe
 * yang tidak terdaftar (belum di-register oleh module via hook).
 *
 * Ini guard keamanan: core hanya me-resolve tipe opt-in.
 */
class RelationTypeNotRegisteredException extends Exception
{
    public function __construct(string $type)
    {
        parent::__construct("Relation type '{$type}' is not registered.");
    }
}
