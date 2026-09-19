<?php

namespace App\Http\Controllers\Admin\QrTool;

use App\Enums\DeletableRecordType;
use App\Http\Controllers\Controller;
use App\Models\DeletionRequest;
use App\Models\QrCertificate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class QrRecordsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', QrCertificate::class);

        $search = trim((string) $request->query('search', ''));

        $records = QrCertificate::query()
            ->with('category')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    // `data` is generic per-category JSON (role/session/etc.
                    // vary by category), so this searches its raw text
                    // representation rather than a hardcoded key -- covers
                    // "role"/"session" search per the brief without assuming
                    // every category actually has fields with those keys.
                    $query->where('recipient_name', 'like', "%{$search}%")
                        ->orWhere('codeword', 'like', "%{$search}%")
                        ->orWhere('data', 'like', "%{$search}%");
                });
            })
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.qr-tool.records.index', [
            'records' => $records,
            'search' => $search,
        ]);
    }

    /**
     * Read-only "Deleted Records" -- super_admin only, no Restore action
     * yet (see docs/CERTIFICATE_SYSTEM.md §Admin lists). Deliberately its
     * own action rather than a `?trashed=1` flag on index(): the normal
     * Records list must never accidentally include a soft-deleted row.
     */
    public function deleted(): View
    {
        $this->authorize('viewDeleted', QrCertificate::class);

        $records = QrCertificate::onlyTrashed()
            ->with('category')
            ->latest('deleted_at')
            ->paginate(20);

        return view('admin.qr-tool.records.deleted', ['records' => $records]);
    }

    public function show(QrCertificate $certificate): View
    {
        $this->authorize('view', $certificate);

        return view('admin.qr-tool.records.show', [
            'certificate' => $certificate->load('category', 'creator'),
            'deletionRequest' => DeletionRequest::latestFor(DeletableRecordType::QrCertificate, $certificate->id),
        ]);
    }
}
