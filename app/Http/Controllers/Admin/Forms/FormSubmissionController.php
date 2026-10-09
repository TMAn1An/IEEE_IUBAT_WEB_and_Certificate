<?php

namespace App\Http\Controllers\Admin\Forms;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Services\Forms\FormSubmissionExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Read-only submission views + Excel export. No update/destroy action
 * exists for submissions. The per-form list shows a few summary columns
 * (fields flagged "show in submissions list", else the first three);
 * the full record is on the detail page. See docs/FORM_BUILDER.md.
 */
class FormSubmissionController extends Controller
{
    private const SUMMARY_COLUMNS = 3;

    public function overview(): View
    {
        $this->authorize('viewAny', Form::class);

        return view('admin.forms.submissions.overview', [
            'forms' => Form::query()
                ->withCount('submissions')
                ->withMax('submissions', 'submitted_at')
                ->orderByDesc('submissions_max_submitted_at')
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function index(Form $form): View
    {
        $this->authorize('viewSubmissions', $form);

        $summaryFields = $this->summaryFields($form);
        $submissions = $form->submissions()
            ->with(['submitter:id,name', 'values' => fn ($q) => $q->whereIn('form_field_id', $summaryFields->pluck('id'))])
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(25);

        return view('admin.forms.submissions.index', [
            'form' => $form,
            'summaryFields' => $summaryFields,
            'submissions' => $submissions,
            'rows' => $submissions->getCollection()->map(fn (FormSubmission $submission) => [
                'submission' => $submission,
                'cells' => $summaryFields->map(
                    fn (FormField $field) => $submission->values->firstWhere('form_field_id', $field->id)?->display_value
                )->all(),
            ]),
        ]);
    }

    public function show(Form $form, FormSubmission $submission): View
    {
        $this->authorize('viewSubmissions', $form);

        $currentFields = $form->fields()->get()->keyBy('id');

        return view('admin.forms.submissions.show', [
            'form' => $form,
            'submission' => $submission->load(['values', 'submitter:id,name']),
            // Shown alongside the snapshot label when the field was renamed/archived since.
            'currentFields' => $currentFields,
        ]);
    }

    public function export(Form $form, FormSubmissionExportService $exporter): BinaryFileResponse
    {
        $this->authorize('export', $form);

        return response()
            ->download($exporter->export($form), $exporter->filename($form), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /** @return Collection<int, FormField> */
    private function summaryFields(Form $form): Collection
    {
        $collecting = $form->activeFields()->get()->filter(fn (FormField $f) => $f->type->collectsValue())->values();
        $flagged = $collecting->filter(fn (FormField $f) => $f->setting('show_in_list'))->values();

        return ($flagged->isNotEmpty() ? $flagged : $collecting)->take(self::SUMMARY_COLUMNS)->values();
    }
}
