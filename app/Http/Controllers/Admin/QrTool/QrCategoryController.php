<?php

namespace App\Http\Controllers\Admin\QrTool;

use App\Enums\QrCategoryFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQrCategoryRequest;
use App\Http\Requests\Admin\UpdateQrCategoryRequest;
use App\Models\QrCategory;
use App\Services\QrTool\QrCategoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class QrCategoryController extends Controller
{
    public function __construct(private readonly QrCategoryService $categories) {}

    public function index(): View
    {
        $this->authorize('viewAny', QrCategory::class);

        return view('admin.qr-tool.categories.index', [
            'categories' => QrCategory::withCount('fields')->orderByDesc('created_at')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QrCategory::class);

        return view('admin.qr-tool.categories.create');
    }

    public function store(StoreQrCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = ($data['slug'] ?? null) ?: $this->categories->generateUniqueSlug($data['name']);
        $data['created_by'] = $request->user()->id;

        $category = QrCategory::create($data);

        return redirect()
            ->route('admin.qr.categories.edit', $category)
            ->with('status', 'QR category created. Add fields below.');
    }

    public function edit(QrCategory $category): View
    {
        $this->authorize('update', $category);

        return view('admin.qr-tool.categories.edit', [
            'category' => $category,
            'fields' => $category->fields,
            'fieldTypes' => QrCategoryFieldType::cases(),
        ]);
    }

    public function update(UpdateQrCategoryRequest $request, QrCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('admin.qr.categories.edit', $category)->with('status', 'QR category updated.');
    }

    public function activate(QrCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);
        $category->update(['is_active' => true]);

        return back()->with('status', 'QR category activated.');
    }

    public function deactivate(QrCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);
        $category->update(['is_active' => false]);

        return back()->with('status', 'QR category deactivated. It will not be available for new QR generation.');
    }
}
