<?php

namespace App\Enums;

enum AuditEventType: string
{
    case DeletionRequested = 'deletion_requested';
    case DeletionApproved = 'deletion_approved';
    case DeletionRejected = 'deletion_rejected';
    case RecordSoftDeleted = 'record_soft_deleted';

    public function label(): string
    {
        return match ($this) {
            self::DeletionRequested => 'Deletion requested',
            self::DeletionApproved => 'Deletion approved',
            self::DeletionRejected => 'Deletion rejected',
            self::RecordSoftDeleted => 'Record soft-deleted',
        };
    }
}
