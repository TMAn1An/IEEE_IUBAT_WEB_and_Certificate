<?php

namespace App\Http\Controllers\Admin\QrTool;

use App\Enums\QrCategoryFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQrCategoryFieldRequest;
use App\Http\Requests\Admin\UpdateQrCategoryFieldRequest;
use App\Models\QrCategory;
use App\Models\QrCategoryField;
use App\Services\QrTool\QrCategoryFieldService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QrCategoryFieldController extends Controller
{
    public function __construct(private readonly QrCategoryFieldService $fields) {}

    public function create(QrCategory $category): View
    {
        $this->authorize('update', $category);

        return view('admin.qr-tool.categories.fields.create', [
            'category' => $category,
            'fieldTypes' => QrCategoryFieldType::cases(),
        ]);
    }

    public function store(StoreQrCategoryFieldRequest $request, QrCategory $category): RedirectResponse
    {
        $this->fields->create($category, $this->preparedData($request));

        return redirect()->route('admin.qr.categories.edit', $category)->with('status', 'Field added.');
    }

    public function edit(QrCategory $category, QrCategoryField $field): View
    {
        $this->authorize('update', $category);
        $this->ensureFieldBelongsToCategory($category, $field);

        return view('admin.qr-tool.categories.fields.edit', [
            'category' => $category,
            'field' => $field,
            'fieldTypes' => QrCategoryFieldType::cases(),
        ]);
    }

    public function update(UpdateQrCategoryFieldRequest $request, QrCategory $category, QrCategoryField $field): RedirectResponse
    {
        $this->ensureFieldBelongsToCategory($category, $field);

        $this->fields->update($field, $this->preparedData($request));

        return redirect()->route('admin.qr.categories.edit', $category)->with('status', 'Field updated.');
    }

    public function destroy(QrCategory $category, QrCategoryField $field): RedirectResponse
    {
        $this->authorize('update', $category);
        $this->ensureFieldBelongsToCategory($category, $field);

        $this->fields->delete($field);

        return redirect()->route('admin.qr.categories.edit', $category)->with('status', 'Field removed.');
    }

    public function moveUp(QrCategory $category, QrCategoryField $field): RedirectResponse
    {
        $this->authorize('update', $category);
        $this->ensureFieldBelongsToCategory($category, $field);

        $this->fields->moveUp($field);

        return redirect()->route('admin.qr.categories.edit', $category);
    }

    public function moveDown(QrCategory $category, QrCategoryField $field): RedirectResponse
    {
        $this->authorize('update', $category);
        $this->ensureFieldBelongsToCategory($category, $field);

        $this->fields->moveDown($field);

        return redirect()->route('admin.qr.categories.edit', $category);
    }

    /** @return array<string, mixed> */
    private function preparedData(StoreQrCategoryFieldRequest|UpdateQrCategoryFieldRequest $request): array
    {
        $data = $request->validated();
        $data['required'] = $request->boolean('required');
        $data['show_on_verification'] = $request->boolean('show_on_verification');
        $data['is_recipient_name'] = $request->boolean('is_recipient_name');

        if ($data['type'] !== 'dropdown') {
            $data['options'] = null;
        } else {
            $data['options'] = array_values(array_filter($data['options'] ?? [], fn ($option) => trim((string) $option) !== ''));
        }

        return $data;
    }

    private function ensureFieldBelongsToCategory(QrCategory $category, QrCategoryField $field): void
    {
        if ($field->qr_category_id !== $category->id) {
            throw new NotFoundHttpException;
        }
    }
}
