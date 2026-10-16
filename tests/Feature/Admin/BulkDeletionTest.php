<?php

namespace Tests\Feature\Admin;

use App\Enums\DeletionRequestStatus;
use App\Models\Certificate;
use App\Models\DeletionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bulk siblings of the single-item flows covered by ControlledDeletionTest
 * -- "select several Certificate Records rows -> Request deletion (N)" and
 * "select several pending Deletion Requests rows -> Approve selected (N)".
 * Every assertion here is about the SAME authorization/service path the
 * single-item flow uses, just reached through the bulk routes. See
 * docs/CERTIFICATE_SYSTEM.md §Bulk deletion requests / §Bulk deletion
 * approvals.
 */
class BulkDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function cert(): Certificate
    {
        return Certificate::factory()->create();
    }

    // ---------------------------------------------------------------
    // §Bulk request deletion (Certificate Records)
    // ---------------------------------------------------------------

    public function test_selecting_a_subset_only_requests_deletion_for_that_subset(): void
    {
        $manager = User::factory()->create();
        $a = $this->cert();
        $b = $this->cert();
        $untouched = $this->cert();

        $this->actingAs($manager)->post('/admin/certificates/bulk-request-deletion', [
            'certificate_ids' => [$a->id, $b->id],
            'reason' => 'Duplicate batch import',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('certificate_deletion_requests', ['record_id' => $a->id, 'status' => 'pending']);
        $this->assertDatabaseHas('certificate_deletion_requests', ['record_id' => $b->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('certificate_deletion_requests', ['record_id' => $untouched->id]);
        $this->assertSame(2, DeletionRequest::count());
    }

    public function test_bulk_request_skips_a_certificate_with_an_existing_pending_request_but_processes_the_rest(): void
    {
        $manager = User::factory()->create();
        $alreadyPending = $this->cert();
        $fresh = $this->cert();

        $this->actingAs($manager)->post("/admin/certificates/{$alreadyPending->id}/request-deletion", [
            'reason' => 'First request',
        ]);

        $response = $this->actingAs($manager)->post('/admin/certificates/bulk-request-deletion', [
            'certificate_ids' => [$alreadyPending->id, $fresh->id],
            'reason' => 'Bulk reason',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertSessionHas('status');
        $this->assertStringContainsString('1 deletion request requested', session('status'));
        $this->assertStringContainsString('skipped', session('status'));

        // The fresh one succeeded; the already-pending one was not
        // duplicated -- still exactly one request for it.
        $this->assertSame(1, DeletionRequest::where('record_id', $fresh->id)->count());
        $this->assertSame(1, DeletionRequest::where('record_id', $alreadyPending->id)->count());
        $this->assertSame(2, DeletionRequest::count());
    }

    public function test_bulk_request_rejects_the_whole_submission_for_an_unauthenticated_user(): void
    {
        $cert = $this->cert();

        $this->post('/admin/certificates/bulk-request-deletion', [
            'certificate_ids' => [$cert->id],
            'reason' => 'Test',
        ])->assertRedirect('/admin/login');

        $this->assertDatabaseCount('certificate_deletion_requests', 0);
    }

    public function test_bulk_request_rejects_the_whole_submission_when_any_id_does_not_exist(): void
    {
        $manager = User::factory()->create();
        $valid = $this->cert();
        $bogusId = $valid->id + 999999;

        $response = $this->actingAs($manager)->post('/admin/certificates/bulk-request-deletion', [
            'certificate_ids' => [$valid->id, $bogusId],
            'reason' => 'Test',
        ]);

        $response->assertSessionHasErrors('certificate_ids.1');
        // Nothing applied -- not even the valid id in the same payload.
        $this->assertDatabaseCount('certificate_deletion_requests', 0);
    }

    public function test_bulk_request_requires_a_reason(): void
    {
        $manager = User::factory()->create();
        $cert = $this->cert();

        $this->actingAs($manager)->post('/admin/certificates/bulk-request-deletion', [
            'certificate_ids' => [$cert->id],
            'reason' => '',
        ])->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('certificate_deletion_requests', 0);
    }

    public function test_double_submitting_the_same_bulk_request_does_not_create_duplicate_pending_requests(): void
    {
        $manager = User::factory()->create();
        $a = $this->cert();
        $b = $this->cert();

        $this->actingAs($manager)->post('/admin/certificates/bulk-request-deletion', [
            'certificate_ids' => [$a->id, $b->id],
            'reason' => 'First submit',
        ]);

        // Simulated double-click / retry of the exact same batch.
        $response = $this->actingAs($manager)->post('/admin/certificates/bulk-request-deletion', [
            'certificate_ids' => [$a->id, $b->id],
            'reason' => 'Retry',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertStringContainsString('0 deletion requests requested', session('status'));
        $this->assertSame(1, DeletionRequest::where('record_id', $a->id)->count());
        $this->assertSame(1, DeletionRequest::where('record_id', $b->id)->count());
        $this->assertSame(2, DeletionRequest::count());
    }

    // ---------------------------------------------------------------
    // §Bulk approve (Deletion Requests review queue)
    // ---------------------------------------------------------------

    public function test_selecting_a_subset_only_approves_that_subset(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $a = $this->cert();
        $b = $this->cert();
        $leaveAlone = $this->cert();

        foreach ([$a, $b, $leaveAlone] as $cert) {
            $this->actingAs($admin)->post("/admin/certificates/{$cert->id}/request-deletion", ['reason' => 'test']);
        }

        $requestA = DeletionRequest::where('record_id', $a->id)->first();
        $requestB = DeletionRequest::where('record_id', $b->id)->first();
        $requestLeaveAlone = DeletionRequest::where('record_id', $leaveAlone->id)->first();

        $this->actingAs($admin)->post('/admin/deletion-requests/bulk-approve', [
            'deletion_request_ids' => [$requestA->id, $requestB->id],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(DeletionRequestStatus::Completed, $requestA->fresh()->status);
        $this->assertSame(DeletionRequestStatus::Completed, $requestB->fresh()->status);
        $this->assertSame(DeletionRequestStatus::Pending, $requestLeaveAlone->fresh()->status);
        $this->assertSoftDeleted('certificates', ['id' => $a->id]);
        $this->assertSoftDeleted('certificates', ['id' => $b->id]);
        $this->assertNotSoftDeleted($leaveAlone);
    }

    public function test_bulk_approve_skips_an_already_completed_request_but_processes_the_rest(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $alreadyDone = $this->cert();
        $stillPending = $this->cert();

        $this->actingAs($admin)->post("/admin/certificates/{$alreadyDone->id}/request-deletion", ['reason' => 'test']);
        $this->actingAs($admin)->post("/admin/certificates/{$stillPending->id}/request-deletion", ['reason' => 'test']);

        $doneRequest = DeletionRequest::where('record_id', $alreadyDone->id)->first();
        $pendingRequest = DeletionRequest::where('record_id', $stillPending->id)->first();

        // Pre-approve the first one directly so it's already completed by
        // the time the bulk submission reaches it.
        $this->actingAs($admin)->post("/admin/deletion-requests/{$doneRequest->id}/approve");

        $response = $this->actingAs($admin)->post('/admin/deletion-requests/bulk-approve', [
            'deletion_request_ids' => [$doneRequest->id, $pendingRequest->id],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertStringContainsString('1 deletion request approved', session('status'));
        $this->assertStringContainsString('skipped', session('status'));
        $this->assertSame(DeletionRequestStatus::Completed, $pendingRequest->fresh()->status);
        $this->assertSoftDeleted('certificates', ['id' => $stillPending->id]);
    }

    public function test_bulk_approve_rejects_whole_submission_for_a_certificate_manager(): void
    {
        $manager = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $cert = $this->cert();

        $this->actingAs($manager)->post("/admin/certificates/{$cert->id}/request-deletion", ['reason' => 'test']);
        $deletionRequest = DeletionRequest::first();

        $this->actingAs($manager)->post('/admin/deletion-requests/bulk-approve', [
            'deletion_request_ids' => [$deletionRequest->id],
        ])->assertForbidden();

        $this->assertSame(DeletionRequestStatus::Pending, $deletionRequest->fresh()->status);
        $this->assertNotSoftDeleted($cert);
    }

    public function test_bulk_approve_rejects_whole_submission_when_payload_contains_an_id_outside_the_users_authority(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $legit = $this->cert();
        $this->actingAs($admin)->post("/admin/certificates/{$legit->id}/request-deletion", ['reason' => 'test']);
        $legitRequest = DeletionRequest::first();

        $bogusId = $legitRequest->id + 999999;

        // A mix of one real, reviewable id and one that does not resolve to
        // any request at all -- the whole batch must be rejected, not just
        // the bogus id skipped.
        $this->actingAs($admin)->post('/admin/deletion-requests/bulk-approve', [
            'deletion_request_ids' => [$legitRequest->id, $bogusId],
        ])->assertForbidden();

        $this->assertSame(DeletionRequestStatus::Pending, $legitRequest->fresh()->status);
        $this->assertNotSoftDeleted($legit);
    }

    public function test_double_submitting_the_same_bulk_approval_does_not_double_apply(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $cert = $this->cert();
        $this->actingAs($admin)->post("/admin/certificates/{$cert->id}/request-deletion", ['reason' => 'test']);
        $deletionRequest = DeletionRequest::first();

        $this->actingAs($admin)->post('/admin/deletion-requests/bulk-approve', [
            'deletion_request_ids' => [$deletionRequest->id],
        ]);

        // Simulated double-click: the exact same bulk approval submitted again.
        $second = $this->actingAs($admin)->post('/admin/deletion-requests/bulk-approve', [
            'deletion_request_ids' => [$deletionRequest->id],
        ]);

        $second->assertSessionDoesntHaveErrors();
        $this->assertStringContainsString('0 deletion requests approved', session('status'));
        $this->assertSame(DeletionRequestStatus::Completed, $deletionRequest->fresh()->status);
        $this->assertSame(1, Certificate::onlyTrashed()->where('id', $cert->id)->count());
    }

    public function test_bulk_approve_allows_a_super_admin_to_approve_their_own_request_matching_single_item_behavior(): void
    {
        // §Documented limitation in docs/CERTIFICATE_SYSTEM.md §Authorization
        // ("Self-approval"): the single-item flow currently allows a Super
        // Admin to approve their own request. The bulk path must not be
        // stricter than the single-item path -- it preserves this exact
        // behavior rather than inventing a new restriction.
        $admin = User::factory()->superAdmin()->create();
        $cert = $this->cert();
        $this->actingAs($admin)->post("/admin/certificates/{$cert->id}/request-deletion", ['reason' => 'test']);
        $deletionRequest = DeletionRequest::first();
        $this->assertSame($admin->id, $deletionRequest->requested_by);

        $this->actingAs($admin)->post('/admin/deletion-requests/bulk-approve', [
            'deletion_request_ids' => [$deletionRequest->id],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(DeletionRequestStatus::Completed, $deletionRequest->fresh()->status);
        $this->assertSoftDeleted('certificates', ['id' => $cert->id]);
    }

    public function test_bulk_approved_certificate_fails_public_verification_same_as_single_item_flow(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $cert = $this->cert();
        $this->actingAs($admin)->post("/admin/certificates/{$cert->id}/request-deletion", ['reason' => 'test']);
        $deletionRequest = DeletionRequest::first();

        $this->actingAs($admin)->post('/admin/deletion-requests/bulk-approve', [
            'deletion_request_ids' => [$deletionRequest->id],
        ]);

        $this->get("/certificate/verify/{$cert->codeword}")
            ->assertOk()
            ->assertSee('Certificate Not Verified')
            ->assertDontSee('Certificate Verified');
    }
}
