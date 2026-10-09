<?php

namespace App\Enums;

enum AuditEventType: string
{
    case DeletionRequested = 'deletion_requested';
    case DeletionApproved = 'deletion_approved';
    case DeletionRejected = 'deletion_rejected';
    case RecordSoftDeleted = 'record_soft_deleted';

    // Form + Page Builder package -- values match TMAn1An\FormBuilder\Enums\AuditEvent
    // (see App\FormBuilder\LogbookAuditLogger and docs/FORM_BUILDER.md §Audit integration).
    case FormCreated = 'form_created';
    case FormUpdated = 'form_updated';
    case FormPublished = 'form_published';
    case FormDeactivated = 'form_deactivated';
    case FormArchived = 'form_archived';
    case FormRestored = 'form_restored';
    case FormDuplicated = 'form_duplicated';
    case PageCreated = 'page_created';
    case PageUpdated = 'page_updated';
    case PagePublished = 'page_published';
    case PageDeactivated = 'page_deactivated';
    case PageArchived = 'page_archived';
    case PageRestored = 'page_restored';
    case PageDuplicated = 'page_duplicated';

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
            self::PageCreated => 'Page created',
            self::PageUpdated => 'Page updated',
            self::PagePublished => 'Page published',
            self::PageDeactivated => 'Page deactivated',
            self::PageArchived => 'Page archived',
            self::PageRestored => 'Page restored from archive',
            self::PageDuplicated => 'Page duplicated',
        };
    }
}
