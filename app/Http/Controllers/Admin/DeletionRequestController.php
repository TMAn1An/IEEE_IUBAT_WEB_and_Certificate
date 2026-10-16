<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeletableRecordType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkApproveDeletionRequestsRequest;
use App\Http\Requests\Admin\BulkRequestDeletionRequest;
use App\Http\Requests\Admin\RejectDeletionRequestRequest;
use App\Http\Requests\Admin\RequestDeletionRequest;
use App\Models\Certificate;
use App\Models\DeletionRequest;
use App\Models\QrCertificate;
use App\Services\Deletion\DeletionRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * §CORE RULE: no certificate/QR record is ever deleted directly. The two
 * `request*()` actions below are model-bound per record type
 * (`{certificate}` resolves via route-model-binding against the correct
 * Eloquent model for that URL) — `DeletableRecordType` is hard-coded here,
 * never read from request input, so there is no way for a client to submit
 * an arbitrary "record_type" string. See docs/CERTIFICATE_SYSTEM.md
 * §Controlled deletion.
 */
class DeletionRequestController extends Controller
{
    public function __construct(private readonly DeletionRequestService $service) {}

    public function requestForQrCertificate(RequestDeletionRequest $request, QrCertificate $certificate): RedirectResponse
    {
        $this->service->request(DeletableRecordType::QrCertificate, $certificate->id, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Deletion request submitted for Super Admin review.');
    }

    public function requestForCertificate(RequestDeletionRequest $request, Certificate $certificate): RedirectResponse
    {
        $this->service->request(DeletableRecordType::Certificate, $certificate->id, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Deletion request submitted for Super Admin review.');
    }

    /**
     * Bulk sibling of requestForCertificate() -- same hard-coded
     * DeletableRecordType::Certificate (never read from the request body,
     * same structural safety as the single-item routes above), just applied
     * to the set of ids the Certificate Records list page submitted. See
     * docs/CERTIFICATE_SYSTEM.md §Bulk deletion requests.
     */
    public function bulkRequestForCertificates(BulkRequestDeletionRequest $request): RedirectResponse
    {
        $result = $this->service->bulkRequest(
            DeletableRecordType::Certificate,
            $request->validated('certificate_ids'),
            $request->user(),
            $request->validated('reason'),
        );

        return back()->with('status', $this->summarize(
            'requested',
            count($result['requested']),
            $result['skipped'],
        ));
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DeletionRequest::class);

        $requests = DeletionRequest::query()
            ->with(['requester', 'reviewer'])
            ->latest('requested_at')
            ->paginate(20);

        return view('admin.deletion-requests.index', ['requests' => $requests]);
    }

    public function approve(DeletionRequest $deletionRequest): RedirectResponse
    {
        $this->authorize('review', $deletionRequest);

        try {
            $this->service->approve($deletionRequest, request()->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.deletion-requests.index')
            ->with('status', 'Deletion request approved — the record has been soft-deleted.');
    }

    public function reject(RejectDeletionRequestRequest $request, DeletionRequest $deletionRequest): RedirectResponse
    {
        try {
            $this->service->reject($deletionRequest, $request->user(), $request->validated('review_note'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.deletion-requests.index')
            ->with('status', 'Deletion request rejected.');
    }

    /**
     * Bulk sibling of approve() -- authorization for every submitted id is
     * checked by BulkApproveDeletionRequestsRequest::authorize() (the same
     * DeletionRequestPolicy::review() ability the single-item route above
     * uses), so by the time this method runs the whole batch has already
     * been confirmed reviewable. See docs/CERTIFICATE_SYSTEM.md §Bulk
     * deletion approvals.
     */
    public function bulkApprove(BulkApproveDeletionRequestsRequest $request): RedirectResponse
    {
        $result = $this->service->bulkApprove($request->validated('deletion_request_ids'), $request->user());

        return redirect()->route('admin.deletion-requests.index')
            ->with('status', $this->summarize('approved', count($result['approved']), $result['skipped']));
    }

    /** @param  array<int, string>  $skipped */
    private function summarize(string $verb, int $succeededCount, array $skipped): string
    {
        $noun = $succeededCount === 1 ? 'deletion request' : 'deletion requests';
        $message = "{$succeededCount} {$noun} {$verb}.";

        if ($skipped !== []) {
            $reasons = [];
            foreach ($skipped as $id => $reason) {
                $reasons[] = "#{$id} ({$reason})";
            }

            $skippedNoun = count($skipped) === 1 ? 'item' : 'items';
            $message .= ' '.count($skipped)." {$skippedNoun} skipped: ".implode('; ', $reasons);
        }

        return $message;
    }
}
