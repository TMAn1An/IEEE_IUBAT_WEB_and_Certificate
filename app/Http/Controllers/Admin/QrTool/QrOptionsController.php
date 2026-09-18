<?php

namespace App\Http\Controllers\Admin\QrTool;

use App\Http\Controllers\Controller;
use App\Models\QrCategory;
use App\Models\QrConferenceOption;
use App\Models\QrConferenceType;
use App\Services\QrTool\QrCategoryFieldService;
use App\Services\QrTool\QrCategoryService;
use App\Services\QrTool\QrConferenceOptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The old tool's "Add role option" / "Add type option" / "Add conference
 * name" buttons, persisted server-side instead of in the browser's
 * `localStorage`. Small JSON endpoints, no page reload, matching the old
 * tool's own instant-update UX — see
 * resources/views/admin/qr-tool/tool.blade.php's JS and
 * docs/CERTIFICATE_SYSTEM.md §Simple QR tool: old-tool-parity rebuild.
 */
class QrOptionsController extends Controller
{
    public function __construct(
        private readonly QrCategoryFieldService $fields,
        private readonly QrConferenceOptionService $conference,
        private readonly QrCategoryService $categories,
    ) {}

    public function addRole(Request $request): JsonResponse
    {
        $this->authorize('update', $this->primaryCategory());
        $request->validate(['value' => ['required', 'string', 'max:255']]);

        $options = $this->fields->addOption($this->roleField(), $request->string('value')->toString());

        return response()->json(['options' => $options]);
    }

    public function removeRole(Request $request): JsonResponse
    {
        $this->authorize('update', $this->primaryCategory());
        $request->validate(['value' => ['required', 'string']]);

        $options = $this->fields->removeOption($this->roleField(), $request->string('value')->toString());

        return response()->json(['options' => $options]);
    }

    public function addConferenceType(Request $request): JsonResponse
    {
        $this->authorize('update', $this->primaryCategory());
        $request->validate(['value' => ['required', 'string', 'max:255']]);

        $this->conference->addType($request->string('value')->toString());

        return response()->json(['types' => $this->serializedTypes()]);
    }

    public function removeConferenceType(Request $request): JsonResponse
    {
        $this->authorize('update', $this->primaryCategory());
        $request->validate(['value' => ['required', 'string']]);

        $type = QrConferenceType::query()->where('name', $request->input('value'))->first();
        if ($type !== null) {
            $this->conference->removeType($type);
        }

        return response()->json(['types' => $this->serializedTypes()]);
    }

    public function addConferenceOption(Request $request): JsonResponse
    {
        $this->authorize('update', $this->primaryCategory());
        $request->validate(['type' => ['required', 'string'], 'value' => ['required', 'string', 'max:255']]);

        $type = QrConferenceType::query()->where('name', $request->input('type'))->first();
        if ($type === null) {
            throw new NotFoundHttpException;
        }

        $this->conference->addOption($type, $request->string('value')->toString());

        return response()->json(['types' => $this->serializedTypes()]);
    }

    public function removeConferenceOption(Request $request): JsonResponse
    {
        $this->authorize('update', $this->primaryCategory());
        $request->validate(['type' => ['required', 'string'], 'value' => ['required', 'string']]);

        $option = QrConferenceOption::query()
            ->whereHas('type', fn ($q) => $q->where('name', $request->input('type')))
            ->where('name', $request->input('value'))
            ->first();

        if ($option !== null) {
            $this->conference->removeOption($option);
        }

        return response()->json(['types' => $this->serializedTypes()]);
    }

    private function primaryCategory(): QrCategory
    {
        $category = $this->categories->primary();
        abort_if($category === null, 404);

        return $category;
    }

    private function roleField()
    {
        $field = $this->primaryCategory()->fields->firstWhere('key', 'role');
        abort_if($field === null, 404);

        return $field;
    }

    /** @return array<int, array{name: string, options: list<string>}> */
    private function serializedTypes(): array
    {
        return QrConferenceType::with('options')->orderBy('name')->get()
            ->map(fn ($type) => ['name' => $type->name, 'options' => $type->options->pluck('name')->all()])
            ->all();
    }
}
