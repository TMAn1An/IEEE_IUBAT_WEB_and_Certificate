<?php

namespace App\Services\Deletion;

use App\Enums\AuditEventType;
use App\Enums\DeletableRecordType;
use App\Enums\DeletionRequestStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\DeletionRequest;
use App\Models\QrCertificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The ONLY path by which a certificate/QR record can ever be soft-deleted
 * — see docs/CERTIFICATE_SYSTEM.md §Controlled deletion for the full
 * request -> review -> approve/reject workflow this implements. No
 * controller, policy, or route in this codebase calls
 * QrCertificate::delete()/Certificate::delete() directly; every call goes
 * through approve() below.
 */
class DeletionRequestService
{
    /**
     * Step 1 of the workflow: a certificate_manager or super_admin asks for
     * a record to be deleted. Never deletes anything itself.
     */
    public function request(DeletableRecordType $type, int $recordId, User $requester, string $reason): DeletionRequest
    {
        return DB::transaction(function () use ($type, $recordId, $requester, $reason) {
            $record = $this->findActiveRecord($type, $recordId);
            if ($record === null) {
                throw ValidationException::withMessages([
                    'reason' => 'This record no longer exists or has already been deleted.',
                ]);
            }

            // Row-lock the (record_type, record_id) slice so two concurrent
            // submissions can't both pass the "no pending request" check —
            // see docs/CERTIFICATE_SYSTEM.md §Duplicate request protection.
            $existingPending = DeletionRequest::query()
                ->where('record_type', $type)
                ->where('record_id', $recordId)
                ->where('status', DeletionRequestStatus::Pending)
                ->lockForUpdate()
                ->exists();

            if ($existingPending) {
                throw ValidationException::withMessages([
                    'reason' => 'A deletion request for this record is already pending review.',
                ]);
            }

            $deletionRequest = DeletionRequest::create([
                'record_type' => $type,
                'record_id' => $recordId,
                'requested_by' => $requester->id,
                'reason' => $reason,
                'status' => DeletionRequestStatus::Pending,
                'requested_at' => now(),
            ]);

            $this->log(
                event: AuditEventType::DeletionRequested,
                type: $type,
                recordId: $recordId,
                actor: $requester,
                deletionRequest: $deletionRequest,
                summary: "Deletion requested for {$type->label()} #{$recordId}.",
                metadata: ['reason' => $reason],
            );

            return $deletionRequest;
        });
    }

    /**
     * Steps 1-8 of §Approval flow: re-check authorization, re-confirm the
     * request is still pending and the record still exists/isn't already
     * deleted, snapshot it, soft-delete it, and log both the approval and
     * the deletion as separate audit events.
     */
    public function approve(DeletionRequest $deletionRequest, User $reviewer): DeletionRequest
    {
        $this->assertReviewer($reviewer);

        return DB::transaction(function () use ($deletionRequest, $reviewer) {
            /** @var DeletionRequest $locked */
            $locked = DeletionRequest::query()->lockForUpdate()->findOrFail($deletionRequest->id);

            if ($locked->status !== DeletionRequestStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'This request is no longer pending — it may have already been reviewed.',
                ]);
            }

            $record = $this->findActiveRecord($locked->record_type, $locked->record_id);
            if ($record === null) {
                throw ValidationException::withMessages([
                    'status' => 'The target record no longer exists or has already been deleted.',
                ]);
            }

            $snapshot = $this->buildSnapshot($locked->record_type, $record);

            $locked->update([
                'status' => DeletionRequestStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->log(
                event: AuditEventType::DeletionApproved,
                type: $locked->record_type,
                recordId: $locked->record_id,
                actor: $reviewer,
                deletionRequest: $locked,
                summary: "Deletion request #{$locked->id} approved for {$locked->record_type->label()} #{$locked->record_id}.",
            );

            $record->delete();

            $locked->update([
                'status' => DeletionRequestStatus::Completed,
                'completed_at' => now(),
            ]);

            $this->log(
                event: AuditEventType::RecordSoftDeleted,
                type: $locked->record_type,
                recordId: $locked->record_id,
                actor: $reviewer,
                deletionRequest: $locked,
                summary: "{$locked->record_type->label()} #{$locked->record_id} soft-deleted.",
                snapshot: $snapshot,
            );

            return $locked->fresh();
        });
    }

    /** §Rejection flow: the target record is never touched. */
    public function reject(DeletionRequest $deletionRequest, User $reviewer, ?string $reviewNote): DeletionRequest
    {
        $this->assertReviewer($reviewer);

        return DB::transaction(function () use ($deletionRequest, $reviewer, $reviewNote) {
            /** @var DeletionRequest $locked */
            $locked = DeletionRequest::query()->lockForUpdate()->findOrFail($deletionRequest->id);

            if ($locked->status !== DeletionRequestStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'This request is no longer pending — it may have already been reviewed.',
                ]);
            }

            $locked->update([
                'status' => DeletionRequestStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
            ]);

            $this->log(
                event: AuditEventType::DeletionRejected,
                type: $locked->record_type,
                recordId: $locked->record_id,
                actor: $reviewer,
                deletionRequest: $locked,
                summary: "Deletion request #{$locked->id} rejected for {$locked->record_type->label()} #{$locked->record_id}.",
                metadata: $reviewNote !== null ? ['review_note' => $reviewNote] : null,
            );

            return $locked->fresh();
        });
    }

    /**
     * Re-checked here even though the controller already gates this via
     * DeletionRequestPolicy::review() — see §Approval flow step 1 ("Re-check
     * authorization"), defense in depth against ever calling this service
     * from a future code path that forgets the policy check.
     */
    private function assertReviewer(User $reviewer): void
    {
        if ($reviewer->role !== UserRole::SuperAdmin) {
            throw ValidationException::withMessages([
                'status' => 'Only a Super Admin may review deletion requests.',
            ]);
        }
    }

    private function findActiveRecord(DeletableRecordType $type, int $recordId): QrCertificate|Certificate|null
    {
        return ($type->modelClass())::query()->find($recordId);
    }

    /**
     * §Deletion snapshot — compact, historical fields only. Deliberately
     * excludes anything binary (QR PNG/PDF bytes) or secret (passwords,
     * tokens) — see docs/CERTIFICATE_SYSTEM.md §Controlled deletion.
     *
     * @return array<string, mixed>
     */
    private function buildSnapshot(DeletableRecordType $type, Model $record): array
    {
        return match ($type) {
            DeletableRecordType::QrCertificate => [
                'recipient_name' => $record->recipient_name,
                'codeword' => $record->codeword,
                'event_type' => $record->group?->event_type,
                'event_name' => $record->event_name,
                'role' => $record->data['role'] ?? null,
                'session' => $record->data['session'] ?? null,
                'created_at' => $record->created_at?->toIso8601String(),
                'status' => $record->status?->value,
            ],
            DeletableRecordType::Certificate => [
                'certificate_number' => $record->certificate_number,
                'recipient_name' => $record->recipient_name,
                'codeword' => $record->codeword,
                'certificate_template_id' => $record->certificate_template_id,
                'template_name' => $record->template_snapshot['name'] ?? $record->template?->name,
                'issued_at' => $record->issued_at?->toIso8601String(),
                'status' => $record->status?->value,
            ],
        };
    }

    /** @param  array<string, mixed>|null  $snapshot
     * @param  array<string, mixed>|null  $metadata */
    private function log(
        AuditEventType $event,
        DeletableRecordType $type,
        int $recordId,
        User $actor,
        string $summary,
        ?DeletionRequest $deletionRequest = null,
        ?array $snapshot = null,
        ?array $metadata = null,
    ): AuditLog {
        return AuditLog::create([
            'event_type' => $event,
            'record_type' => $type,
            'record_id' => $recordId,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role->value,
            'deletion_request_id' => $deletionRequest?->id,
            'summary' => $summary,
            'snapshot' => $snapshot,
            'metadata' => $metadata,
        ]);
    }
}
