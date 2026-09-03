<?php

declare(strict_types=1);

namespace Modules\Events\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Events\Models\Event;
use Spine\Services\ActivityLogService;

/**
 * CRUD Event — modul Events.
 *
 * Scope: event milik user yang login (paritas legacy: dashboard calendar
 * hanya menampilkan events user sendiri — Utilities_model get_all_events
 * where userid = staff). Index mendukung filter rentang untuk fullcalendar:
 *   GET /api/v1/events?start=YYYY-MM-DD&end=YYYY-MM-DD
 */
class EventController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLog)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Event::query()->where('user_id', $request->user()->id);

        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('start', [$request->string('start'), $request->string('end')]);
        }

        return response()->json(['data' => $query->orderBy('start')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'start'       => ['required', 'date'],
            'end'         => ['nullable', 'date', 'after_or_equal:start'],
            'color'       => ['nullable', 'string', 'max:20'],
        ]);

        $entity = Event::create($validated + ['user_id' => $request->user()->id]);

        return response()->json($entity, 201);
    }

    public function show(int $id): JsonResponse
    {
        $entity = Event::where('user_id', request()->user()->id)->find($id);

        if (! $entity) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        return response()->json($entity);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $entity = Event::where('user_id', $request->user()->id)->find($id);

        if (! $entity) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'start'       => ['sometimes', 'date'],
            'end'         => ['nullable', 'date', 'after_or_equal:start'],
            'color'       => ['nullable', 'string', 'max:20'],
        ]);

        $entity->update($validated);

        return response()->json($entity);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $entity = Event::where('user_id', $request->user()->id)->find($id);

        if (! $entity) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        $entity->delete();

        return response()->json(['message' => 'Event deleted']);
    }

    public function activityLogs(int $id, Request $request): JsonResponse
    {
        if (! Event::where('user_id', $request->user()->id)->find($id)) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        $logs = $this->activityLog
            ->query()
            ->where('subject_type', Event::class)
            ->where('subject_id', $id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($log) => [
                'id'          => $log->id,
                'description' => $log->description,
                'causer'      => $log->causer?->name ?? 'System',
                'properties'  => $log->properties,
                'at'          => $log->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }
}
