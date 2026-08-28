<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GdprService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API untuk GDPR / data privacy.
 *
 * Diadopsi dari `gdpr/` PerfexCRM.
 *
 * @group api/v1
     * @subgroup GDPR
 */
class GdprController extends Controller
{
    public function __construct(
        private GdprService $gdpr
    ) {}

    /**
     * Export semua data user.
     *
     * @authenticated
     *
     * @urlParam user_id int required ID user. Example: 1
     *
     * @response scenario=success {
     *   "data": {
     *     "user": {"id": 1, "name": "...", "email": "..."},
     *     "meta": [],
     *     "attachments": [],
     *     "activity_logs": []
     *   }
     * }
     */
    public function export(Request $request, int $user_id): JsonResponse
    {
        $user = User::findOrFail($user_id);

        return response()->json(['data' => $this->gdpr->export($user)]);
    }

    /**
     * Anonymize data user.
     *
     * @authenticated
     *
     * @urlParam user_id int required ID user. Example: 1
     *
     * @response scenario=success {"message":"User anonymized","success":true}
     * @response status=404 scenario=not-found {"message":"User not found"}
     */
    public function anonymize(Request $request, int $user_id): JsonResponse
    {
        $user = User::find($user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $success = $this->gdpr->anonymize($user);

        return response()->json([
            'message' => $success ? 'User anonymized' : 'Failed to anonymize user',
            'success' => $success,
        ], $success ? 200 : 500);
    }

    /**
     * Hapus data user permanently.
     *
     * @authenticated
     *
     * @urlParam user_id int required ID user. Example: 1
     *
     * @response scenario=success {"message":"User deleted","success":true}
     * @response status=404 scenario=not-found {"message":"User not found"}
     */
    public function delete(Request $request, int $user_id): JsonResponse
    {
        $user = User::find($user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $success = $this->gdpr->delete($user);

        return response()->json([
            'message' => $success ? 'User deleted' : 'Failed to delete user',
            'success' => $success,
        ], $success ? 200 : 500);
    }
}
