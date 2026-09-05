<?php

declare(strict_types=1);

namespace Modules\Events\Listeners;

use Modules\Events\Models\Event;
use Spine\Events\EntityCreated;
use Spine\Events\EntityDeleted;
use Spine\Events\EntityUpdated;
use Spine\Services\ActivityLogService;

/**
 * HOOK — entity lifecycle generic (HasLifecycleHooks) untuk Event.
 * created/updated/deleted -> activity log.
 */
class LogEventActivity
{
    public function __construct(private readonly ActivityLogService $activityLog)
    {
    }

    public function created(EntityCreated $event): void
    {
        if (! $event->entity instanceof Event) {
            return;
        }

        $this->activityLog->log(
            "Event created: " . $this->label($event->entity),
            $event->entity,
            $this->user(),
            ['event' => 'created'],
        );
    }

    public function updated(EntityUpdated $event): void
    {
        if (! $event->entity instanceof Event) {
            return;
        }

        $changes = $event->changes;

        $this->activityLog->log(
            "Event updated: " . $this->label($event->entity) . " (" . $this->describe($changes) . ")",
            $event->entity,
            $this->user(),
            ['event' => 'updated', 'changes' => $changes],
        );
    }

    public function deleted(EntityDeleted $event): void
    {
        if (! $event->entity instanceof Event) {
            return;
        }

        $this->activityLog->log(
            "Event deleted: " . $this->label($event->entity),
            null,
            $this->user(),
            ['event' => 'deleted', 'id' => $event->entity->getKey()],
            null,
            $event->entityType,
        );
    }

    private function describe(array $changes): string
    {
        $parts = [];

        foreach ($changes as $field => $change) {
            if (in_array($field, ['updated_at', 'remember_token'], true)) {
                continue;
            }

            $label = Event::labels()[$field] ?? $field;
            $parts[] = $label . ': ' . $change['old'] . ' -> ' . $change['new'];
        }

        return implode(', ', $parts);
    }

    private function label($entity): string
    {
        return (string) ($entity->title ?? $entity->getKey());
    }

    private function user(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return auth('sanctum')->user() ?? auth()->user();
    }
}