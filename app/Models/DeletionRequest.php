<?php

namespace App\Models;

use App\Enums\DeletableRecordType;
use App\Enums\DeletionRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletionRequest extends Model
{
    use HasFactory;

    protected $table = 'certificate_deletion_requests';

    protected $fillable = [
        'record_type',
        'record_id',
        'requested_by',
        'reason',
        'status',
        'reviewed_by',
        'review_note',
        'requested_at',
        'reviewed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'record_type' => DeletableRecordType::class,
            'status' => DeletionRequestStatus::class,
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The most recent request against a given record, regardless of status — drives the record-detail page's status block (Pending/Rejected/Completed/none). */
    public static function latestFor(DeletableRecordType $type, int $recordId): ?self
    {
        return static::query()
            ->where('record_type', $type)
            ->where('record_id', $recordId)
            ->latest('requested_at')
            ->first();
    }

    /**
     * Resolves the actual target record through the safe enum mapping —
     * `withTrashed()` since a completed request's record is, by design,
     * already soft-deleted by the time anyone views this request again.
     */
    public function record(): ?Model
    {
        return ($this->record_type->modelClass())::withTrashed()->find($this->record_id);
    }
}
