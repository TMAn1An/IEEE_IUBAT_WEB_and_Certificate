<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditEventType;
use App\Enums\DeletableRecordType;
use App\Enums\DeletionRequestStatus;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\DeletionRequest;
use App\Models\QrCertificate;
use App\Models\QrGroup;
use App\Models\User;
use App\Services\Deletion\DeletionRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * §CORE RULE: no certificate/QR record may be deleted directly — only via
 * Staff/Admin request -> Super Admin review -> approve/reject. Covers both
 * record types through the one App\Enums\DeletableRecordType mapping. See
 * docs/CERTIFICATE_SYSTEM.md §Controlled deletion.
 */
class ControlledDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function qrRecord(): QrCertificate
    {
        return QrCertificate::factory()->create(['qr_group_id' => QrGroup::factory()]);
    }

    private function certificate(): Certificate
    {
        return Certificate::factory()->create();
    }

    // ---------------------------------------------------------------
    // §Who can request
    // ---------------------------------------------------------------

    public function test_certificate_manager_can_request_deletion(): void
    {
        $manager = User::factory()->create();
        $record = $this->qrRecord();

        $response = $this->actingAs($manager)->post("/admin/qr-tool/records/{$record->id}/request-deletion", [
            'reason' => 'Duplicate record',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('certificate_deletion_requests', [
            'record_type' => 'qr_certificate',
            'record_id' => $record->id,
            'requested_by' => $manager->id,
            'status' => 'pending',
            'reason' => 'Duplicate record',
        ]);
    }

    public function test_super_admin_can_request_deletion(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->certificate();

        $this->actingAs($admin)->post("/admin/certificates/{$record->id}/request-deletion", [
            'reason' => 'Wrong participant name',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('certificate_deletion_requests', [
            'record_type' => 'certificate',
            'record_id' => $record->id,
            'requested_by' => $admin->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_request_deletion(): void
    {
        $record = $this->qrRecord();

        $this->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data'])
            ->assertRedirect('/admin/login');

        $this->assertDatabaseCount('certificate_deletion_requests', 0);
    }

    public function test_reason_is_required(): void
    {
        $manager = User::factory()->create();
        $record = $this->qrRecord();

        $this->actingAs($manager)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('certificate_deletion_requests', 0);
    }

    public function test_duplicate_pending_request_is_blocked(): void
    {
        $manager = User::factory()->create();
        $record = $this->qrRecord();

        $this->actingAs($manager)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Wrong role']);
        $response = $this->actingAs($manager)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Wrong role again']);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(1, DeletionRequest::where('record_id', $record->id)->count());
    }

    // ---------------------------------------------------------------
    // §Authorization: approve/reject
    // ---------------------------------------------------------------

    public function test_certificate_manager_cannot_approve(): void
    {
        $manager = User::factory()->create();
        $record = $this->qrRecord();
        $this->actingAs($manager)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $deletionRequest = DeletionRequest::first();

        $this->actingAs($manager)->post("/admin/deletion-requests/{$deletionRequest->id}/approve")->assertForbidden();

        $this->assertSame(DeletionRequestStatus::Pending, $deletionRequest->fresh()->status);
        $this->assertNotSoftDeleted($record);
    }

    public function test_certificate_manager_cannot_reject(): void
    {
        $manager = User::factory()->create();
        $record = $this->qrRecord();
        $this->actingAs($manager)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $deletionRequest = DeletionRequest::first();

        $this->actingAs($manager)->post("/admin/deletion-requests/{$deletionRequest->id}/reject", ['review_note' => 'no'])
            ->assertForbidden();

        $this->assertSame(DeletionRequestStatus::Pending, $deletionRequest->fresh()->status);
    }

    public function test_certificate_manager_cannot_view_the_review_queue(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->get('/admin/deletion-requests')->assertForbidden();
    }

    // ---------------------------------------------------------------
    // §Approval flow
    // ---------------------------------------------------------------

    public function test_super_admin_can_approve_a_pending_request(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $deletionRequest = DeletionRequest::first();

        $response = $this->actingAs($admin)->post("/admin/deletion-requests/{$deletionRequest->id}/approve");

        $response->assertRedirect(route('admin.deletion-requests.index'));
        $this->assertSame(DeletionRequestStatus::Completed, $deletionRequest->fresh()->status);
    }

    public function test_approval_soft_deletes_the_qr_record(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $deletionRequest = DeletionRequest::first();

        $this->actingAs($admin)->post("/admin/deletion-requests/{$deletionRequest->id}/approve");

        $this->assertSoftDeleted('qr_certificates', ['id' => $record->id]);
        // The row itself, including its codeword, is still physically present.
        $this->assertDatabaseHas('qr_certificates', ['id' => $record->id, 'codeword' => $record->codeword]);
    }

    public function test_approval_soft_deletes_the_advanced_certificate(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->certificate();
        $this->actingAs($admin)->post("/admin/certificates/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $deletionRequest = DeletionRequest::first();

        $this->actingAs($admin)->post("/admin/deletion-requests/{$deletionRequest->id}/approve");

        $this->assertSoftDeleted('certificates', ['id' => $record->id]);
    }

    public function test_soft_deleted_qr_record_no_longer_verifies(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $this->actingAs($admin)->post('/admin/deletion-requests/'.DeletionRequest::first()->id.'/approve');

        $this->get("/certificate/verify/{$record->codeword}")
            ->assertOk()
            ->assertSee('Certificate Not Verified')
            ->assertDontSee('Certificate Verified');
    }

    public function test_soft_deleted_advanced_certificate_no_longer_verifies(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->certificate();
        $this->actingAs($admin)->post("/admin/certificates/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $this->actingAs($admin)->post('/admin/deletion-requests/'.DeletionRequest::first()->id.'/approve');

        $this->get("/certificate/verify/{$record->codeword}")
            ->assertOk()
            ->assertSee('Certificate Not Verified')
            ->assertDontSee('Certificate Verified');
    }

    public function test_soft_deleted_records_are_excluded_from_normal_lists(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $this->actingAs($admin)->post('/admin/deletion-requests/'.DeletionRequest::first()->id.'/approve');

        $this->actingAs($admin)->get('/admin/qr-tool/records')->assertDontSee($record->recipient_name);
    }

    // ---------------------------------------------------------------
    // §Rejection flow
    // ---------------------------------------------------------------

    public function test_rejected_request_leaves_the_record_active(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $deletionRequest = DeletionRequest::first();

        $this->actingAs($admin)->post("/admin/deletion-requests/{$deletionRequest->id}/reject", ['review_note' => 'Not a duplicate.']);

        $this->assertSame(DeletionRequestStatus::Rejected, $deletionRequest->fresh()->status);
        $this->assertNotSoftDeleted($record);
        $this->get("/certificate/verify/{$record->codeword}")->assertSee('Certificate Verified');
    }

    // ---------------------------------------------------------------
    // §No direct delete
    // ---------------------------------------------------------------

    public function test_super_admin_cannot_directly_delete_without_a_request(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();

        // No matching named route exists at all for a direct delete.
        $this->assertFalse(Route::has('admin.qr.records.destroy'));
        $this->assertFalse(Route::has('admin.certificates.destroy'));

        // Even a raw guessed DELETE request to the show-page URL is refused
        // (405 — no DELETE method registered for that URI at all).
        $this->actingAs($admin)->delete("/admin/qr-tool/records/{$record->id}")->assertStatus(405);
        $this->actingAs($admin)->delete("/admin/certificates/{$record->id}")->assertStatus(405);

        $this->assertNotSoftDeleted($record);
    }

    // ---------------------------------------------------------------
    // §Logbook / audit trail
    // ---------------------------------------------------------------

    public function test_deletion_request_is_logged(): void
    {
        $manager = User::factory()->create();
        $record = $this->qrRecord();

        $this->actingAs($manager)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Wrong event']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEventType::DeletionRequested->value,
            'record_id' => $record->id,
            'actor_id' => $manager->id,
        ]);
    }

    public function test_approval_is_logged_and_deletion_snapshot_is_stored(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $this->actingAs($admin)->post('/admin/deletion-requests/'.DeletionRequest::first()->id.'/approve');

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEventType::DeletionApproved->value,
            'record_id' => $record->id,
            'actor_id' => $admin->id,
        ]);

        $deletionLog = AuditLog::where('event_type', AuditEventType::RecordSoftDeleted)->where('record_id', $record->id)->firstOrFail();
        $this->assertSame($record->recipient_name, $deletionLog->snapshot['recipient_name']);
        $this->assertSame($record->codeword, $deletionLog->snapshot['codeword']);
        $this->assertArrayHasKey('event_type', $deletionLog->snapshot);
        $this->assertArrayHasKey('role', $deletionLog->snapshot);
        $this->assertArrayHasKey('created_at', $deletionLog->snapshot);
        // No binary/secret payloads leak into the snapshot.
        $snapshotJson = json_encode($deletionLog->snapshot);
        $this->assertStringNotContainsString('password', strtolower($snapshotJson));
    }

    public function test_rejection_is_logged(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $this->actingAs($admin)->post('/admin/deletion-requests/'.DeletionRequest::first()->id.'/reject', ['review_note' => 'Keep it.']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditEventType::DeletionRejected->value,
            'record_id' => $record->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_logbook_shows_the_full_lifecycle_and_has_no_edit_or_delete_action(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $this->actingAs($admin)->post('/admin/deletion-requests/'.DeletionRequest::first()->id.'/approve');

        $response = $this->actingAs($admin)->get('/admin/logbook');

        $response->assertOk()
            ->assertSee('Deletion requested')
            ->assertSee('Deletion approved')
            ->assertSee('Record soft-deleted')
            ->assertDontSee('>Delete<', false)
            ->assertDontSee('>Edit<', false);
    }

    public function test_audit_log_has_no_frontend_update_or_delete_route(): void
    {
        $this->assertFalse(Route::has('admin.logbook.update'));
        $this->assertFalse(Route::has('admin.logbook.destroy'));
        $this->assertFalse(Route::has('admin.logbook.edit'));
    }

    public function test_certificate_manager_cannot_view_the_logbook(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->get('/admin/logbook')->assertForbidden();
    }

    // ---------------------------------------------------------------
    // §Duplicate request protection — double-submit/re-review edge cases
    // ---------------------------------------------------------------

    public function test_approving_an_already_completed_request_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $deletionRequest = DeletionRequest::first();
        $this->actingAs($admin)->post("/admin/deletion-requests/{$deletionRequest->id}/approve");

        // Second approval attempt on the now-completed request.
        $this->actingAs($admin)->post("/admin/deletion-requests/{$deletionRequest->id}/approve")
            ->assertSessionHasErrors('status');

        $this->assertSame(1, QrCertificate::onlyTrashed()->where('id', $record->id)->count());
    }

    public function test_rejecting_an_already_completed_request_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $deletionRequest = DeletionRequest::first();
        $this->actingAs($admin)->post("/admin/deletion-requests/{$deletionRequest->id}/approve");

        $this->actingAs($admin)->post("/admin/deletion-requests/{$deletionRequest->id}/reject", ['review_note' => 'too late'])
            ->assertSessionHasErrors('status');

        $this->assertSame(DeletionRequestStatus::Completed, $deletionRequest->fresh()->status);
    }

    public function test_requesting_deletion_of_an_already_deleted_record_is_blocked(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $this->qrRecord();
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Test data']);
        $this->actingAs($admin)->post('/admin/deletion-requests/'.DeletionRequest::first()->id.'/approve');

        // The request-deletion route uses the default (trashed-excluding)
        // route-model binding -- a resubmission against an already-deleted
        // record's URL 404s before the controller/service even runs. The
        // service's own findActiveRecord() null-check (exercised directly
        // below) is the defense-in-depth layer for a race condition instead
        // (record deleted between page load and form submit).
        $this->actingAs($admin)->post("/admin/qr-tool/records/{$record->id}/request-deletion", ['reason' => 'Again'])
            ->assertNotFound();

        $this->assertSame(1, DeletionRequest::where('record_id', $record->id)->count());

        $this->expectException(ValidationException::class);
        app(DeletionRequestService::class)->request(
            DeletableRecordType::QrCertificate,
            $record->id,
            $admin,
            'Again via service'
        );
    }
}
