<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Append an audit entry. Never updates or deletes — audit is append-only (PRD FR-AUD-001).
     */
    public static function log(
        string $action,
        string $entityType,
        Model|int|string|null $entity = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?User $actor = null,
        string $source = 'web',
    ): AuditLog {
        $actor ??= Auth::user();

        return AuditLog::create([
            'school_id' => $actor?->school_id,
            'actor_user_id' => $actor?->id,
            'actor_type' => $actor ? 'user' : 'system',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entity instanceof Model ? (string) $entity->getKey() : ($entity !== null ? (string) $entity : null),
            'before_ref' => $before,
            'after_ref' => $after,
            'reason' => $reason,
            'request_id' => request()->header('X-Request-Id'),
            'source' => $source,
        ]);
    }
}
