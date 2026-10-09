<?php

namespace App\Enums;

enum AuditEventType: string
{
    case DeletionRequested = 'deletion_requested';
    case DeletionApproved = 'deletion_approved';
    case DeletionRejected = 'deletion_rejected';
    case RecordSoftDeleted = 'record_soft_deleted';

    // Form builder -- see docs/FORM_BUILDER.md §Audit integration.
    case FormCreated = 'form_created';
    case FormUpdated = 'form_updated';
    case FormPublished = 'form_published';
    case FormDeactivated = 'form_deactivated';
    case FormArchived = 'form_archived';
    case FormRestored = 'form_restored';
    case FormDuplicated = 'form_duplicated';

    public function label(): string
    {
        return match ($this) {
            self::DeletionRequested => 'Deletion requested',
            self::DeletionApproved => 'Deletion approved',
            self::DeletionRejected => 'Deletion rejected',
            self::RecordSoftDeleted => 'Record soft-deleted',
            self::FormCreated => 'Form created',
            self::FormUpdated => 'Form updated',
            self::FormPublished => 'Form published',
            self::FormDeactivated => 'Form deactivated',
            self::FormArchived => 'Form archived',
            self::FormRestored => 'Form restored from archive',
            self::FormDuplicated => 'Form duplicated',
        };
    }
}
