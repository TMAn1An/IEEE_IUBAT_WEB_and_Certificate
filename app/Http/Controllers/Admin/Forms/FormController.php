<?php

namespace App\Http\Controllers\Admin\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Forms\SaveFormDefinitionRequest;
use App\Http\Requests\Admin\Forms\StoreFormRequest;
use App\Models\Form;
use App\Services\Forms\FormBuilderService;
use App\Services\Forms\FormBuilderState;
use App\Services\Forms\FormCssScoper;
use App\Services\Forms\FormDefinitionValidator;
use App\Services\Forms\FormHtmlSanitizer;
use App\Services\Forms\FormPresenter;
use App\Services\Forms\FormVersionConflictException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Form builder admin: list, create, the builder screen + its JSON save,
 * preview, and lifecycle actions. Thin by design -- validation lives in
 * the Form Requests / FormDefinitionValidator, writes in
 * FormBuilderService. No destroy action exists. See docs/FORM_BUILDER.md.
 */
class FormController extends Controller
{
    public function __construct(private readonly FormBuilderService $builder) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Form::class);

        $showArchived = $request->boolean('archived');

        return view('admin.forms.index', [
            'forms' => Form::query()
                ->withCount('submissions')
                ->when(! $showArchived, fn ($q) => $q->notArchived())
                ->latest('updated_at')
                ->paginate(20)
                ->withQueryString(),
            'showArchived' => $showArchived,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Form::class);

        return view('admin.forms.create');
    }

    public function store(StoreFormRequest $request): RedirectResponse
    {
        $form = $this->builder->create($request->validated('name'), $request->validated('description'), $request->user());

        return redirect()->route('admin.forms.edit', $form)->with('status', 'Form created. Add fields from the left-hand panel.');
    }

    public function edit(Request $request, Form $form, FormBuilderState $state): View
    {
        $this->authorize('view', $form);

        return view('admin.forms.builder', [
            'form' => $form,
            'builder' => $state->forForm($form, $request->user()) + [
                'meta' => $state->meta(),
                'urls' => [
                    'save' => route('admin.forms.update', $form),
                    'sanitize' => route('admin.forms.sanitize', $form),
                    'preview' => route('admin.forms.preview', $form),
                    'public' => route('forms.show', ['form' => $form->slug]),
                    'publicBase' => url('/forms').'/',
                    'submissions' => route('admin.forms.submissions.index', $form),
                ],
            ],
        ]);
    }

    /** JSON: 200 with the fresh builder state, 409 on a stale version, 422 on validation errors. */
    public function update(SaveFormDefinitionRequest $request, Form $form, FormDefinitionValidator $definitions, FormBuilderState $state): JsonResponse
    {
        $canCode = $request->canManageCustomCode();
        $definition = $definitions->normalize($request->validated(), $canCode);
        $publish = $request->validated('intent') === 'publish';

        try {
            $saved = $this->builder->saveDefinition(
                $form,
                $definition,
                (int) $request->validated('version'),
                $request->user(),
                publish: $publish,
                autosave: $request->boolean('autosave'),
            );
        } catch (FormVersionConflictException $e) {
            return response()->json([
                'message' => $e->getMessage().' Reload the builder to get the latest version.',
                'current_version' => $e->currentVersion,
            ], 409);
        }

        return response()->json([
            'message' => $publish ? 'Saved and published.' : 'Saved.',
            'state' => $state->forForm($saved, $request->user()),
        ]);
    }

    /** Live-preview helper: the scoped CSS / sanitized HTML exactly as the public page would render it. */
    public function sanitize(Request $request, Form $form, FormHtmlSanitizer $html, FormCssScoper $css): JsonResponse
    {
        $this->authorize('update', $form);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['css', 'html'])],
            'content' => ['nullable', 'string', 'max:'.(FormCssScoper::MAX_LENGTH + 5000)],
        ]);

        $result = $data['kind'] === 'css'
            ? $css->scope($data['content'] ?? '', '#'.$form->wrapperId())
            : $html->sanitize($data['content'] ?? '');

        return response()->json(['result' => $result]);
    }

    public function preview(Form $form, FormPresenter $presenter): View
    {
        $this->authorize('view', $form);

        return view('forms.show', [
            'presented' => $presenter->present($form),
            'action' => null,
            'notice' => null,
            'success' => null,
            'previewBackUrl' => route('admin.forms.edit', $form),
        ]);
    }

    public function publish(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('publish', $form);
        $this->builder->publish($form, $request->user());

        return back()->with('status', "\"{$form->name}\" is now published at /forms/{$form->slug}.");
    }

    public function deactivate(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('deactivate', $form);
        $this->builder->deactivate($form, $request->user());

        return back()->with('status', "\"{$form->name}\" was deactivated and no longer accepts submissions.");
    }

    public function archive(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('archive', $form);
        $this->builder->archive($form, $request->user());

        return redirect()->route('admin.forms.index')->with('status', "\"{$form->name}\" was archived. Its submissions are still available.");
    }

    public function restore(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('restore', $form);
        $this->builder->restore($form, $request->user());

        return back()->with('status', "\"{$form->name}\" was restored as a deactivated form.");
    }

    public function duplicate(Request $request, Form $form): RedirectResponse
    {
        $this->authorize('duplicate', $form);
        $copy = $this->builder->duplicate($form, $request->user());

        return redirect()->route('admin.forms.edit', $copy)->with('status', "Duplicated as \"{$copy->name}\" (draft, no submissions copied).");
    }
}
