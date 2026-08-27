<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Service aktivitas log (ActivityLogService).
 *
 * Diadopsi dari database_helper.php PerfexCRM:
 *   - log_activity → log()
 *
 * REF: docs/porting-helper-implementasi.md
 */
class ActivityLogService
{
    /**
     * Catat aktivitas.
     *
     * @param string $description Deskripsi singkat aktivitas.
     * @param Model|int|null $subject  Entitas yang menjadi subjek (mis. Invoice yang dimodifikasi).
     * @param Model|int|null $causer  Pengguna/orang yang melakukan aktivitas.
     * @param array $properties Data tambahan (JSON).
     * @param int|null $tenantId Scope tenant (null = global).
     * @return \App\Models\ActivityLog
     */
    public function log(
        string $description,
        mixed $subject = null,
        mixed $causer = null,
        array $properties = [],
        ?int $tenantId = null
    ): ActivityLog {
        $subjectType = null;
        $subjectId = null;

        if ($subject instanceof Model) {
            $subjectType = get_class($subject);
            $subjectId = $subject->getKey();
        } elseif (is_int($subject)) {
            $subjectId = $subject;
        }

        $causerType = null;
        $causerId = null;

        if ($causer instanceof Model) {
            $causerType = get_class($causer);
            $causerId = $causer->getKey();
        } elseif (is_int($causer)) {
            $causerId = $causer;
        }

        return ActivityLog::create([
            'description'   => $description,
            'subject_type'  => $subjectType,
            'subject_id'    => $subjectId,
            'causer_type'   => $causerType,
            'causer_id'     => $causerId,
            'tenant_id'     => $tenantId,
            'properties'    => $properties,
        ]);
    }

    /**
     * Query semua log (opsional filter).
     *
     * @param int|null $tenantId Scope tenant.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(?int $tenantId = null): \Illuminate\Database\Eloquent\Builder
    {
        $query = ActivityLog::query();

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }
}
