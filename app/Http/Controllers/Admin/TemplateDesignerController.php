<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TemplateFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTemplateLayoutRequest;
use App\Models\CertificateTemplate;
use App\Services\Templates\TemplateLayoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TemplateDesignerController extends Controller
{
    public function __construct(private readonly TemplateLayoutService $layouts) {}

    public function edit(CertificateTemplate $template): View
    {
        // Viewing stays on the same `update` ability as the rest of template
        // management — an archived template's layout is still visible
        // (read-only), only saving is blocked (`manageLayout`, checked in
        // update() below and reflected in the view via $canEdit).
        $this->authorize('update', $template);

        return view('admin.templates.designer', [
            'template' => $template,
            'fields' => $template->fields, // ordered by sort_order, same as the management page
            'fieldTypes' => TemplateFieldType::assignable(),
            'canEdit' => auth()->user()->can('manageLayout', $template),
        ]);
    }

    public function update(SaveTemplateLayoutRequest $request, CertificateTemplate $template): RedirectResponse
    {
        $this->layouts->saveLayout($template, $request->validated());

        return redirect()
            ->route('admin.templates.designer.edit', $template)
            ->with('status', 'Layout saved.');
    }
}
