<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateTemplateStatus;
use App\Enums\DeletableRecordType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IssueCertificateRequest;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\DeletionRequest;
use App\Services\Certificates\CertificateIssuanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CertificateController extends Controller
{
    public function __construct(private readonly CertificateIssuanceService $issuance) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Certificate::class);

        $search = trim((string) $request->query('search', ''));

        $certificates = Certificate::query()
            ->with('template')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('certificate_number', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('codeword', 'like', "%{$search}%");
                });
            })
            ->latest('issued_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.certificates.index', [
            'certificates' => $certificates,
            'search' => $search,
        ]);
    }

    public function chooseTemplate(): View
    {
        $this->authorize('create', Certificate::class);

        $templates = CertificateTemplate::query()
            ->where('status', CertificateTemplateStatus::Active)
            ->orderBy('name')
            ->get();

        return view('admin.certificates.choose-template', ['templates' => $templates]);
    }

    public function create(CertificateTemplate $template): View
    {
        $this->authorize('create', Certificate::class);
        $this->authorize('view', $template);
        $this->ensureTemplateIsActive($template);

        return view('admin.certificates.issue', [
            'template' => $template,
            'fields' => $template->fields,
        ]);
    }

    public function store(IssueCertificateRequest $request, CertificateTemplate $template): RedirectResponse
    {
        $this->ensureTemplateIsActive($template);

        $result = $this->issuance->issue($template, $request->validated()['fields'] ?? [], $request->user());

        $status = 'Certificate '.$result->certificate->certificate_number.' issued.';
        if ($result->overflowWarnings !== []) {
            $status .= ' Warning: '.implode(' ', $result->overflowWarnings);
        }

        return redirect()
            ->route('admin.certificates.show', $result->certificate)
            ->with('status', $status);
    }

    /** See Admin\QrTool\QrRecordsController::deleted() — identical reasoning. */
    public function deleted(): View
    {
        $this->authorize('viewDeleted', Certificate::class);

        $certificates = Certificate::onlyTrashed()
            ->with('template')
            ->latest('deleted_at')
            ->paginate(20);

        return view('admin.certificates.deleted', ['certificates' => $certificates]);
    }

    public function show(Certificate $certificate): View
    {
        $this->authorize('view', $certificate);

        return view('admin.certificates.show', [
            'certificate' => $certificate->load('template', 'creator'),
            'deletionRequest' => DeletionRequest::latestFor(DeletableRecordType::Certificate, $certificate->id),
        ]);
    }

    public function download(Certificate $certificate): StreamedResponse
    {
        $this->authorize('download', $certificate);

        if ($certificate->pdf_path === null || ! Storage::disk('local')->exists($certificate->pdf_path)) {
            throw new NotFoundHttpException;
        }

        return Storage::disk('local')->response(
            $certificate->pdf_path,
            "{$certificate->certificate_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    private function ensureTemplateIsActive(CertificateTemplate $template): void
    {
        if ($template->status !== CertificateTemplateStatus::Active) {
            throw ValidationException::withMessages([
                'template' => 'Only active templates can be used to issue certificates.',
            ]);
        }
    }
}
