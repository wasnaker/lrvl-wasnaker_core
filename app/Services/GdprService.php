<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * GdprService — data privacy / compliance service.
 *
 * Diadopsi dari `gdpr/` PerfexCRM.
 * Menyediakan:
 * - Data export per user (JSON)
 * - Data erasure: anonymize/delete (GDPR right to be forgotten)
 *
 * Mapping ke tabel aktual:
 * - meta -> custom_meta (meta_type, meta_id)
 * - files -> attachments (rel_type, rel_id)
 * - settings -> global/tenant, not user-owned
 *
 * REF: docs/analisis-library-perfex.md (gdpr/ — ❌ BELUM)
 */
class GdprService
{
    /**
     * Export all data associated with a user.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toDateTimeString(),
                'updated_at' => $user->updated_at?->toDateTimeString(),
            ],
            'meta' => DB::table('custom_meta')->where('meta_type', 'user')->where('meta_id', $user->id)->get(['meta_key', 'meta_value']),
            'attachments' => DB::table('attachments')->where('rel_type', 'user')->where('rel_id', $user->id)->get(['id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'created_at']),
            'activity_logs' => DB::table('activity_logs')->where(function ($q) use ($user): void {
                $q->where('causer_id', $user->id)
                  ->orWhere(function ($q2) use ($user): void {
                      $q2->where('subject_type', 'user')->where('subject_id', $user->id);
                  });
            })->get(['description', 'subject_type', 'subject_id', 'causer_id', 'properties', 'created_at']),
        ];
    }

    /**
     * Anonymize user data (keep account but remove PII).
     */
    public function anonymize(User $user): bool
    {
        try {
            DB::transaction(function () use ($user): void {
                $user->name = 'Anonymized User';
                $user->email = 'anonymized-' . $user->id . '@local.invalid';
                $user->password = Hash::make(Str::random(32));
                $user->save();

                DB::table('custom_meta')->where('meta_type', 'user')->where('meta_id', $user->id)->update(['meta_value' => null]);
            });

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Delete user data permanently.
     */
    public function delete(User $user): bool
    {
        try {
            DB::transaction(function () use ($user): void {
                DB::table('custom_meta')->where('meta_type', 'user')->where('meta_id', $user->id)->delete();
                DB::table('attachments')->where('rel_type', 'user')->where('rel_id', $user->id)->delete();
                DB::table('activity_logs')->where(function ($q) use ($user): void {
                    $q->where('causer_id', $user->id)
                      ->orWhere(function ($q2) use ($user): void {
                          $q2->where('subject_type', 'user')->where('subject_id', $user->id);
                      });
                })->delete();

                $user->delete();
            });

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
