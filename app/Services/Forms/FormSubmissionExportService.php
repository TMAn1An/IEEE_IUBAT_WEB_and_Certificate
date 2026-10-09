<?php

namespace App\Services\Forms;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Services\Certificates\Export\ExcelFormulaGuard;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * One submission per row, one field per column. See docs/FORM_BUILDER.md
 * §Excel export.
 *
 * Column mapping is by `form_field_id`, so a renamed field keeps its
 * column and its old values; the heading is the field's CURRENT label
 * (archived fields are marked). A value whose field no longer exists at
 * all falls back to a column keyed by its stored `field_key`, headed by
 * its stored label snapshot. Cells hold the human-readable
 * `display_value` snapshot, are written as explicit strings (so "0123"
 * stays "0123"), and pass through the project-wide ExcelFormulaGuard.
 *
 * Submissions are read in chunks rather than loaded all at once.
 */
class FormSubmissionExportService
{
    private const CHUNK = 500;

    /** Writes the workbook to a temp file and returns its path. */
    public function export(Form $form): string
    {
        $columns = $this->columns($form);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Submissions');

        $headings = array_merge(['Submission ID', 'Submitted At', 'Submitted By'], array_column($columns, 'heading'));
        foreach (ExcelFormulaGuard::sanitizeRow($headings) as $i => $heading) {
            $sheet->setCellValueExplicit([$i + 1, 1], (string) $heading, DataType::TYPE_STRING);
        }
        $sheet->getStyle([1, 1, count($headings), 1])->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $row = 2;
        FormSubmission::query()
            ->where('form_id', $form->id)
            ->with(['values', 'submitter:id,name'])
            ->chunkById(self::CHUNK, function ($submissions) use ($sheet, $columns, &$row) {
                foreach ($submissions as $submission) {
                    $byField = $submission->values->keyBy('form_field_id');
                    $byKey = $submission->values->keyBy('field_key');

                    $cells = [
                        (string) $submission->id,
                        $submission->submitted_at->format('Y-m-d H:i:s'),
                        $submission->submitter?->name ?? '',
                    ];
                    foreach ($columns as $column) {
                        /** @var FormSubmissionValue|null $value */
                        $value = $column['field_id'] !== null
                            ? ($byField->get($column['field_id']) ?? $byKey->get($column['key']))
                            : $byKey->get($column['key']);
                        $cells[] = (string) ($value?->display_value ?? '');
                    }

                    foreach (ExcelFormulaGuard::sanitizeRow($cells) as $i => $cell) {
                        $sheet->setCellValueExplicit([$i + 1, $row], (string) $cell, DataType::TYPE_STRING);
                    }
                    $row++;
                }
            });

        $path = tempnam(sys_get_temp_dir(), 'form-export').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    public function filename(Form $form): string
    {
        return 'form-'.Str::limit($form->slug, 60, '').'-submissions-'.now()->format('Y-m-d').'.xlsx';
    }

    /** @return list<array{field_id: int|null, key: string, heading: string}> */
    public function columns(Form $form): array
    {
        $columns = [];
        $knownKeys = [];

        foreach ($form->fields()->get() as $field) {
            if (! $field->type->collectsValue()) {
                continue;
            }
            $columns[] = [
                'field_id' => $field->id,
                'key' => $field->key,
                'heading' => $field->label.($field->is_active ? '' : ' (archived)'),
            ];
            $knownKeys[$field->key] = true;
        }

        // Values whose field row is gone entirely: keep them, keyed by their snapshot.
        $orphans = FormSubmissionValue::query()
            ->whereIn('form_submission_id', FormSubmission::query()->select('id')->where('form_id', $form->id))
            ->whereNull('form_field_id')
            ->orderByDesc('id')
            ->get(['field_key', 'field_label_snapshot'])
            ->unique('field_key'); // newest label snapshot per key

        foreach ($orphans as $orphan) {
            if (! isset($knownKeys[$orphan->field_key])) {
                $columns[] = ['field_id' => null, 'key' => $orphan->field_key, 'heading' => $orphan->field_label_snapshot.' (removed)'];
            }
        }

        // Two fields may share a label; disambiguate headings with the key.
        $counts = array_count_values(array_column($columns, 'heading'));

        return array_map(function (array $column) use ($counts) {
            if ($counts[$column['heading']] > 1) {
                $column['heading'] .= ' ['.$column['key'].']';
            }

            return $column;
        }, $columns);
    }
}
