<?php

namespace App\Services\Forms;

use App\Enums\AuditEventType;
use App\Enums\DeletableRecordType;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\User;

/**
 * Writes form-builder actions into the EXISTING append-only audit_logs
 * table (the Logbook) -- deliberately not a second audit system. Same
 * columns and conventions as DeletionRequestService::log(): actor role
 * snapshotted at the time of the action, never updated afterwards.
 */
final class FormAuditLogger
{
    /** @param  array<string, mixed>|null  $metadata */
    public function log(AuditEventType $event, Form $form, User $actor, string $summary, ?array $metadata = null): AuditLog
    {
        return AuditLog::create([
            'event_type' => $event,
            'record_type' => DeletableRecordType::Form,
            'record_id' => $form->id,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role->value,
            'summary' => mb_substr($summary, 0, 255),
            'snapshot' => [
                'name' => $form->name,
                'slug' => $form->slug,
                'status' => $form->status->value,
                'lock_version' => $form->lock_version,
            ],
            'metadata' => $metadata,
        ]);
    }
}
