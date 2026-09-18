<?php

namespace App\Http\Controllers\Admin\QrTool;

use App\Http\Controllers\Controller;
use App\Models\QrCertificate;
use App\Models\QrGroup;
use App\Services\Certificates\Export\ExcelFormulaGuard;
use App\Services\QrTool\QrGroupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Lists the groups QrGroupService has auto-created (Event Type + Event
 * Name + Role combinations) and lets an admin browse/export/import per
 * group -- see docs/CERTIFICATE_SYSTEM.md §Simple QR tool: automatic
 * grouping. Nothing here creates a group; that only ever happens via
 * QrGroupService::resolve() from the Generate page or the importer.
 */
class QrGroupController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', QrCertificate::class);

        $groups = QrGroup::query()
            ->withCount('certificates')
            ->orderBy('event_type')
            ->orderBy('event_name')
            ->orderBy('role')
            ->get();

        return view('admin.qr-tool.groups.index', ['groups' => $groups]);
    }

    public function show(QrGroup $group): View
    {
        $this->authorize('viewAny', QrCertificate::class);

        $records = $group->certificates()->latest('created_at')->paginate(20);

        return view('admin.qr-tool.groups.show', ['group' => $group, 'records' => $records]);
    }

    /**
     * Same headings/QR-File-blank behavior as
     * QrGenerateController::downloadExcel(), scoped to one group and named
     * with the old tool's exact filename convention (see
     * QrGroupService::exportFilename()).
     */
    public function export(QrGroup $group, QrGroupService $groups): Response
    {
        $this->authorize('viewAny', QrCertificate::class);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['SL', 'Conference', 'Role', 'Name', 'Session', 'Codeword', 'Created At', 'QR File'], null, 'A1');

        $records = $group->certificates()->orderBy('id')->get();

        foreach ($records as $index => $record) {
            $sheet->fromArray(ExcelFormulaGuard::sanitizeRow([
                $index + 1,
                $record->event_name,
                $record->data['role'] ?? '',
                $record->recipient_name,
                $record->data['session'] ?? '',
                $record->codeword,
                $record->created_at->format('Y-m-d H:i:s'),
                '',
            ]), null, 'A'.($index + 2));
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'qr-group-export').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tmpPath);

        return response(file_get_contents($tmpPath), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$groups->exportFilename($group).'"',
        ]);
    }
}
