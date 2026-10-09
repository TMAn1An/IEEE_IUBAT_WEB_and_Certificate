<?php

namespace App\FormBuilder;

use App\Enums\AuditEventType;
use App\Enums\DeletableRecordType;
use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use TMAn1An\FormBuilder\Contracts\AuditLogger;
use TMAn1An\FormBuilder\Enums\AuditEvent;
use TMAn1An\FormBuilder\Models\Form;
use TMAn1An\FormBuilder\Models\Page;

/**
 * Writes Form/Page Builder actions into the existing append-only Logbook
 * (audit_logs) -- not a second audit system. The package reports
 * AuditEvent values; they map 1:1 onto App\Enums\AuditEventType cases with
 * the same string values. Record types use the audit-only Form/Page cases
 * of DeletableRecordType (never deletion targets).
 */
class LogbookAuditLogger implements AuditLogger
{
    public function record(AuditEvent $event, Model $subject, Authenticatable $actor, string $summary, array $metadata = []): void
    {
        $recordType = match (true) {
            $subject instanceof Form => DeletableRecordType::Form,
            $subject instanceof Page => DeletableRecordType::Page,
            default => null,
        };
        if ($recordType === null) {
            return;
        }

        AuditLog::create([
            'event_type' => AuditEventType::from($event->value),
            'record_type' => $recordType,
            'record_id' => $subject->getKey(),
            'actor_id' => $actor->getAuthIdentifier(),
            'actor_role' => $actor->role->value,
            'summary' => mb_substr($summary, 0, 255),
            'snapshot' => [
                'name' => $subject instanceof Form ? $subject->name : $subject->title,
                'slug' => $subject->slug,
                'status' => $subject->status->value,
                'lock_version' => $subject->lock_version,
            ],
            'metadata' => $metadata ?: null,
        ]);
    }
}
