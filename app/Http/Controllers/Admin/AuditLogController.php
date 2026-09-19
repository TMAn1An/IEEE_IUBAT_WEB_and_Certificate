<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEventType;
use App\Enums\DeletableRecordType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\QrCertificate;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Read-only — see App\Policies\AuditLogPolicy and
 * docs/CERTIFICATE_SYSTEM.md §Logbook immutability. This controller
 * deliberately has only an index() action: no store/update/destroy method
 * exists, and no route points at one.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $from = $request->query('from');
        $to = $request->query('to');
        $eventType = $request->query('event_type');
        $actorId = $request->query('actor_id');
        $codeword = trim((string) $request->query('codeword', ''));

        $logs = AuditLog::query()
            ->with('actor')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($eventType, fn ($q) => $q->where('event_type', $eventType))
            ->when($actorId, fn ($q) => $q->where('actor_id', $actorId))
            ->when($codeword !== '', fn ($q) => $q->where(function ($q) use ($codeword) {
                // No codeword column on audit_logs itself -- resolve which
                // (record_type, record_id) pairs actually match, including
                // already-soft-deleted records, then filter by those.
                $qrIds = QrCertificate::withTrashed()->where('codeword', 'like', "%{$codeword}%")->pluck('id');
                $certIds = Certificate::withTrashed()
                    ->where('codeword', 'like', "%{$codeword}%")
                    ->orWhere('certificate_number', 'like', "%{$codeword}%")
                    ->pluck('id');

                $q->where(fn ($q2) => $q2->where('record_type', DeletableRecordType::QrCertificate)->whereIn('record_id', $qrIds))
                    ->orWhere(fn ($q2) => $q2->where('record_type', DeletableRecordType::Certificate)->whereIn('record_id', $certIds));
            }))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => $request->only(['from', 'to', 'event_type', 'actor_id', 'codeword']),
            'actors' => User::query()->orderBy('name')->get(['id', 'name']),
            'eventTypes' => AuditEventType::cases(),
        ]);
    }
}
