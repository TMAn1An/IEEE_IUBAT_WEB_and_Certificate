@php
  use App\Enums\DeletionRequestStatus;
  use App\Models\DeletionRequest;
@endphp
@props(['record', 'deletionRequest', 'action'])

{{--
  Shared by both record-detail pages (simple QR record + advanced
  certificate) so the request/pending/rejected UI never drifts between the
  two — see docs/CERTIFICATE_SYSTEM.md §Controlled deletion §Record detail
  UI. Never rendered for an already-deleted record (the parent view shows a
  "Record Deleted" banner instead — see its own @unless($trashed) guard).
--}}
<div class="admin-card">
  <h3 style="margin-top:0;font-size:1rem">Deletion Request</h3>

  @if ($deletionRequest && $deletionRequest->status === DeletionRequestStatus::Pending)
    <p style="margin-top:0"><strong>Status:</strong> <span class="badge {{ $deletionRequest->status->badgeClass() }}">Pending</span></p>
    <table class="admin-table">
      <tbody>
        <tr><th style="width:180px">Requested by</th><td>{{ $deletionRequest->requester?->name }}</td></tr>
        <tr><th>Reason</th><td>{{ $deletionRequest->reason }}</td></tr>
        <tr><th>Requested at</th><td>{{ $deletionRequest->requested_at->format('j M Y, g:i A') }}</td></tr>
      </tbody>
    </table>
    <p style="color:var(--muted)">Deletion request pending — awaiting Super Admin review. Only one pending request is allowed per record.</p>

  @elseif ($deletionRequest && $deletionRequest->status === DeletionRequestStatus::Rejected)
    <p style="margin-top:0"><strong>Status:</strong> <span class="badge {{ $deletionRequest->status->badgeClass() }}">Rejected</span></p>
    <table class="admin-table">
      <tbody>
        <tr><th style="width:180px">Reviewed by</th><td>{{ $deletionRequest->reviewer?->name }}</td></tr>
        <tr><th>Review note</th><td>{{ $deletionRequest->review_note ?: '—' }}</td></tr>
        <tr><th>Reviewed at</th><td>{{ $deletionRequest->reviewed_at?->format('j M Y, g:i A') }}</td></tr>
      </tbody>
    </table>

    @can('create', DeletionRequest::class)
      <form method="POST" action="{{ $action }}" style="margin-top:14px">
        @csrf
        <div class="field">
          <label for="reason">Reason for deletion</label>
          <textarea id="reason" name="reason" rows="3" required maxlength="1000" placeholder="Example: Wrong participant name, Duplicate record, Test data...">{{ old('reason') }}</textarea>
          @error('reason')<div class="error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn--danger btn--sm" style="margin-top:8px">Request Deletion Again</button>
      </form>
    @endcan

  @elseif ($deletionRequest && $deletionRequest->status === DeletionRequestStatus::Completed)
    {{-- Defensive only -- the parent view shows the "Record Deleted" banner instead of this component once trashed. --}}
    <p style="color:var(--muted)">This record has already been deleted.</p>

  @else
    @can('create', DeletionRequest::class)
      <p style="color:var(--muted);margin-top:-4px">
        Deleting a record requires Super Admin review. Submitting this form does not delete
        anything immediately.
      </p>
      <form method="POST" action="{{ $action }}">
        @csrf
        <div class="field">
          <label for="reason">Reason for deletion</label>
          <textarea id="reason" name="reason" rows="3" required maxlength="1000" placeholder="Example: Wrong participant name, Duplicate record, Test data...">{{ old('reason') }}</textarea>
          @error('reason')<div class="error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn--danger btn--sm" style="margin-top:8px">Request Deletion</button>
      </form>
    @else
      <p style="color:var(--muted)">You do not have permission to request deletion of this record.</p>
    @endcan
  @endif
</div>
