<?php

namespace App\Services\Forms;

use App\Enums\FormAvailabilityState;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\User;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The single answer to "may this visitor fill in this form now?" -- used by
 * both the public page (to show the form or a message) and the submission
 * service (re-checked inside the transaction, so the limit can't be raced).
 *
 * "Allow multiple submissions = off" is best-effort for anonymous visitors:
 * it is remembered per browser session, plus per account for a signed-in
 * user. A determined anonymous visitor can clear cookies; see
 * docs/FORM_BUILDER.md §Known limitations.
 */
class FormAvailability
{
    /** @param  list<int>  $sessionSubmittedFormIds */
    public function check(Form $form, ?User $viewer, array $sessionSubmittedFormIds = []): FormAvailabilityState
    {
        if ($form->status !== FormStatus::Active) {
            return FormAvailabilityState::Unavailable;
        }

        if ($form->setting('visibility') === 'private' && ! ($viewer?->is_active)) {
            return FormAvailabilityState::Unavailable;
        }

        $now = CarbonImmutable::now();
        $opensAt = $this->parse($form->setting('opens_at'));
        $closesAt = $this->parse($form->setting('closes_at'));

        if ($opensAt !== null && $now->lt($opensAt)) {
            return FormAvailabilityState::NotOpenYet;
        }
        if ($closesAt !== null && $now->gte($closesAt)) {
            return FormAvailabilityState::Closed;
        }

        $limit = $form->setting('submission_limit');
        if ($limit !== null && $form->submissions()->count() >= (int) $limit) {
            return FormAvailabilityState::LimitReached;
        }

        if (! $form->setting('allow_multiple_submissions', true)) {
            $submittedThisSession = in_array($form->id, $sessionSubmittedFormIds, true);
            $submittedThisAccount = $viewer !== null && $form->submissions()->where('submitted_by', $viewer->id)->exists();
            if ($submittedThisSession || $submittedThisAccount) {
                return FormAvailabilityState::AlreadySubmitted;
            }
        }

        return FormAvailabilityState::Open;
    }

    private function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat(FormSettingsSchema::DATETIME_FORMAT, $value) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
