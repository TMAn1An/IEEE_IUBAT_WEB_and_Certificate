<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TemplateFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTemplateFieldRequest;
use App\Http\Requests\Admin\UpdateTemplateFieldRequest;
use App\Models\CertificateTemplate;
use App\Models\TemplateField;
use App\Services\Templates\TemplateFieldService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TemplateFieldController extends Controller
{
    public function __construct(private readonly TemplateFieldService $fields) {}

    public function create(CertificateTemplate $template): View
    {
        $this->authorize('update', $template);

        return view('admin.templates.fields.create', [
            'template' => $template,
            'fieldTypes' => TemplateFieldType::assignable(),
        ]);
    }

    public function store(StoreTemplateFieldRequest $request, CertificateTemplate $template): RedirectResponse
    {
        $this->fields->create($template, $this->preparedData($request));

        return redirect()->route('admin.templates.edit', $template)->with('status', 'Field added.');
    }

    public function edit(CertificateTemplate $template, TemplateField $field): View
    {
        $this->authorize('update', $template);
        $this->ensureFieldBelongsToTemplate($template, $field);

        return view('admin.templates.fields.edit', [
            'template' => $template,
            'field' => $field,
            'fieldTypes' => TemplateFieldType::assignable(),
        ]);
    }

    public function update(UpdateTemplateFieldRequest $request, CertificateTemplate $template, TemplateField $field): RedirectResponse
    {
        $this->ensureFieldBelongsToTemplate($template, $field);

        $this->fields->update($field, $this->preparedData($request));

        return redirect()->route('admin.templates.edit', $template)->with('status', 'Field updated.');
    }

    public function destroy(CertificateTemplate $template, TemplateField $field): RedirectResponse
    {
        $this->authorize('update', $template);
        $this->ensureFieldBelongsToTemplate($template, $field);

        $this->fields->delete($field);

        return redirect()->route('admin.templates.edit', $template)->with('status', 'Field removed.');
    }

    public function moveUp(CertificateTemplate $template, TemplateField $field): RedirectResponse
    {
        $this->authorize('update', $template);
        $this->ensureFieldBelongsToTemplate($template, $field);

        $this->fields->moveUp($field);

        return redirect()->route('admin.templates.edit', $template);
    }

    public function moveDown(CertificateTemplate $template, TemplateField $field): RedirectResponse
    {
        $this->authorize('update', $template);
        $this->ensureFieldBelongsToTemplate($template, $field);

        $this->fields->moveDown($field);

        return redirect()->route('admin.templates.edit', $template);
    }

    /** @return array<string, mixed> */
    private function preparedData(StoreTemplateFieldRequest|UpdateTemplateFieldRequest $request): array
    {
        $data = $request->validated();
        $data['is_required'] = $request->boolean('is_required');
        $data['show_on_verification'] = $request->boolean('show_on_verification');
        $data['is_recipient_name'] = $request->boolean('is_recipient_name');

        if ($data['field_type'] !== 'dropdown') {
            $data['options'] = null;
        } else {
            $data['options'] = array_values(array_filter($data['options'] ?? [], fn ($option) => trim((string) $option) !== ''));
        }

        return $data;
    }

    /** IDOR guard: a {field} route parameter must actually belong to the {template} in the URL. */
    private function ensureFieldBelongsToTemplate(CertificateTemplate $template, TemplateField $field): void
    {
        if ($field->certificate_template_id !== $template->id) {
            throw new NotFoundHttpException;
        }
    }
}
