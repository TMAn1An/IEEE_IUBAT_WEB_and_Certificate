<?php

namespace App\Models;

use App\Enums\AuditEventType;
use App\Enums\DeletableRecordType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Append-only — see the audit_logs migration's docblock and
 * App\Policies\AuditLogPolicy. Nothing in this codebase ever calls
 * ->update()/->delete() on this model; there is no route that could.
 */
class AuditLog extends Model
{
    use HasFactory;

    /** No `updated_at` column exists — see the audit_logs migration. */
    const UPDATED_AT = null;

    protected $fillable = [
        'event_type',
        'record_type',
        'record_id',
        'actor_id',
        'actor_role',
        'deletion_request_id',
        'summary',
        'snapshot',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AuditEventType::class,
            'record_type' => DeletableRecordType::class,
            'snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<DeletionRequest, $this> */
    public function deletionRequest(): BelongsTo
    {
        return $this->belongsTo(DeletionRequest::class, 'deletion_request_id');
    }

    /** Resolves the record this event was about — `withTrashed()` where the model supports it, since most deletion events point at a record that is, by now, soft-deleted (Form has no soft deletes; it is archived, never removed). Used only by the internal Logbook, never the public verification page. */
    public function record(): ?Model
    {
        $class = $this->record_type->modelClass();

        return in_array(SoftDeletes::class, class_uses_recursive($class), true)
            ? $class::withTrashed()->find($this->record_id)
            : $class::query()->find($this->record_id);
    }
}
