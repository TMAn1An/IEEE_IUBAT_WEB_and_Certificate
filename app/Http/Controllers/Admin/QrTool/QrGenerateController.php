<?php

namespace App\Http\Controllers\Admin\QrTool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateQrRequest;
use App\Models\QrCategory;
use App\Models\QrCertificate;
use App\Models\QrConferenceType;
use App\Services\Certificates\QrCodeService;
use App\Services\QrTool\QrCategoryService;
use App\Services\QrTool\QrCertificateIssuanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * The old-tool-parity page: one screen with a Create Entry panel, a
 * Generated Result panel, and a Recent Entries table — recreating
 * IEEEQRCODEGENERATOR-main/templates/index.html's actual layout and
 * workflow as closely as practical, per explicit instruction not to
 * redesign it into a category-picker/multi-step admin flow. See
 * docs/CERTIFICATE_SYSTEM.md §Simple QR tool: old-tool-parity rebuild.
 *
 * Bound to exactly one QrCategory (config('qr-tool.primary_category_slug')
 * — the old tool only ever had one form too), not a category the admin
 * picks here. Full multi-category management still exists separately at
 * /admin/qr-tool/categories.
 */
class QrGenerateController extends Controller
{
    public function __construct(
        private readonly QrCertificateIssuanceService $issuance,
        private readonly QrCategoryService $categories,
    ) {}

    public function show(): View
    {
        $this->authorize('create', QrCertificate::class);

        return view('admin.qr-tool.tool', $this->pageData());
    }

    public function store(GenerateQrRequest $request): View
    {
        $category = $this->requirePrimaryCategory();

        $fieldValues = [
            'recipient_name' => $request->string('name')->trim()->toString(),
            'role' => $request->input('role_select'),
        ];
        if ($request->boolean('include_session')) {
            $fieldValues['session'] = trim((string) $request->input('session'));
        }

        $eventName = $request->boolean('include_conference') ? $request->input('conference_select') : null;

        $certificate = $this->issuance->issue($category, $fieldValues, $request->user(), $eventName);

        $wasDuplicate = ! $certificate->wasRecentlyCreated;
        $status = $wasDuplicate
            ? 'This exact person/role/session entry already exists. Showing the existing codeword.'
            : 'Codeword and QR generated.';

        return view('admin.qr-tool.tool', [
            ...$this->pageData(),
            'result' => $certificate,
            'resultIsDuplicate' => $wasDuplicate,
            'status' => $status,
        ]);
    }

    public function qrImage(QrCertificate $certificate, QrCodeService $qr): Response
    {
        $this->authorize('view', $certificate);

        $url = $qr->verificationUrlForCodeword($certificate->codeword);
        $png = $qr->pngBytes($url);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$certificate->codeword.'-qr.png"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Exports DB rows using the old tool's exact historical headings
     * (SL, Conference, Role, Name, Session, Codeword, Created At, QR File)
     * — see docs/CERTIFICATE_SYSTEM.md §Excel export. `QR File` is always
     * blank: the QR is generated on demand from the codeword, never stored
     * as a file, so there is no path to put there.
     */
    public function downloadExcel(): Response
    {
        $this->authorize('viewAny', QrCertificate::class);
        $category = $this->requirePrimaryCategory();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['SL', 'Conference', 'Role', 'Name', 'Session', 'Codeword', 'Created At', 'QR File'], null, 'A1');

        $records = QrCertificate::query()
            ->where('qr_category_id', $category->id)
            ->orderBy('id')
            ->get();

        foreach ($records as $index => $record) {
            $sheet->fromArray([
                $index + 1,
                $record->event_name,
                $record->data['role'] ?? '',
                $record->recipient_name,
                $record->data['session'] ?? '',
                $record->codeword,
                $record->created_at->format('Y-m-d H:i:s'),
                '',
            ], null, 'A'.($index + 2));
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'qr-export').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tmpPath);

        return response(file_get_contents($tmpPath), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$category->slug.'.xlsx"',
        ]);
    }

    /** @return array<string, mixed> */
    private function pageData(): array
    {
        $category = $this->categories->primary();

        return [
            'category' => $category,
            'roleOptions' => $category?->fields->firstWhere('key', 'role')?->options ?? [],
            'conferenceTypes' => QrConferenceType::with('options')->orderBy('name')->get(),
            'recentEntries' => $category
                ? QrCertificate::where('qr_category_id', $category->id)->latest('created_at')->take(10)->get()
                : collect(),
            'result' => null,
            'resultIsDuplicate' => false,
        ];
    }

    private function requirePrimaryCategory(): QrCategory
    {
        $category = $this->categories->primary();
        abort_if($category === null, 500, 'The QR tool\'s primary category is not configured. Run php artisan db:seed --class=QrCategorySeeder.');

        return $category;
    }
}
