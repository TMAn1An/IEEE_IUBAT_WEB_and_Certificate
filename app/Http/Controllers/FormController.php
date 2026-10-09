<?php

namespace App\Http\Controllers;

use App\Enums\FormAvailabilityState;
use App\Models\Form;
use App\Services\Forms\FormAvailability;
use App\Services\Forms\FormPresenter;
use App\Services\Forms\FormSubmissionService;
use App\Services\Forms\FormUnavailableException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public /forms/{slug}. Only Active forms exist publicly (anything else
 * 404s, as does a private form for a visitor who isn't signed in). See
 * docs/FORM_BUILDER.md §Public form route.
 */
class FormController extends Controller
{
    private const SESSION_SUBMITTED = 'forms.submitted';

    public function __construct(
        private readonly FormAvailability $availability,
        private readonly FormPresenter $presenter,
        private readonly FormSubmissionService $submissions,
    ) {}

    public function show(Request $request, Form $form): View
    {
        $state = $this->availability->check($form, $request->user(), $this->submittedIds($request));
        abort_if($state === FormAvailabilityState::Unavailable, 404);

        $justSubmitted = $request->session()->get('form_submitted') === $form->id;

        return view('forms.show', [
            'presented' => $this->presenter->present($form),
            'action' => $state === FormAvailabilityState::Open ? route('forms.submit', $form) : null,
            'notice' => $state === FormAvailabilityState::Open ? null : $state->message(),
            'success' => $justSubmitted ? $form->setting('success_message') : null,
        ]);
    }

    public function submit(Request $request, Form $form): RedirectResponse
    {
        $state = $this->availability->check($form, $request->user(), $this->submittedIds($request));
        abort_if($state === FormAvailabilityState::Unavailable, 404);

        try {
            // post() -- body only: query-string parameters are never form data.
            $this->submissions->submit($form, $request->post(), $request->user(), $this->submittedIds($request), [
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (FormUnavailableException) {
            return redirect()->route('forms.show', $form);
        }

        $request->session()->push(self::SESSION_SUBMITTED, $form->id);

        $redirect = $form->setting('redirect_url');
        if ($redirect) {
            return redirect()->away($redirect);
        }

        return redirect()->route('forms.show', $form)->with('form_submitted', $form->id);
    }

    /** @return list<int> */
    private function submittedIds(Request $request): array
    {
        return array_map('intval', (array) $request->session()->get(self::SESSION_SUBMITTED, []));
    }
}
