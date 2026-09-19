@php
  use App\Enums\DeletableRecordType;
  use App\Enums\DeletionRequestStatus;
@endphp
<x-layouts.admin title="Deletion Requests" wide>
  <p style="color:var(--muted);margin-top:-4px">
    Certificate/QR records are never deleted directly — a staff member requests deletion here, and
    only a Super Admin's approval actually soft-deletes the record. See docs/CERTIFICATE_SYSTEM.md
    §Controlled deletion.
  </p>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Recipient</th>
          <th>Codeword / Cert No.</th>
          <th>Event / Group</th>
          <th>Requested by</th>
          <th>Reason</th>
          <th>Requested at</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($requests as $deletionRequest)
          @php $record = $deletionRequest->record(); @endphp
          <tr>
            <td>{{ $record?->recipient_name ?? '(record not found)' }}</td>
            <td>
              <code style="font-size:.8em">
                {{ $deletionRequest->record_type === DeletableRecordType::Certificate ? ($record?->certificate_number ?? $record?->codeword) : $record?->codeword }}
              </code>
            </td>
            <td>
              @if ($deletionRequest->record_type === DeletableRecordType::QrCertificate)
                {{ $record?->group?->event_name ?? $record?->event_name }}
              @else
                {{ $record?->template?->name }}
              @endif
            </td>
            <td>{{ $deletionRequest->requester?->name }}</td>
            <td style="max-width:220px">{{ $deletionRequest->reason }}</td>
            <td>{{ $deletionRequest->requested_at->format('j M Y, g:i A') }}</td>
            <td><span class="badge {{ $deletionRequest->status->badgeClass() }}">{{ $deletionRequest->status->label() }}</span></td>
            <td style="white-space:nowrap">
              @if ($deletionRequest->status === DeletionRequestStatus::Pending)
                <form method="POST" action="{{ route('admin.deletion-requests.approve', $deletionRequest) }}" style="display:inline" onsubmit="return confirm('Approve this deletion request? The record will be soft-deleted immediately.');">
                  @csrf
                  <button type="submit" class="btn btn--primary btn--sm">Approve</button>
                </form>
                <button type="button" class="btn btn--danger btn--sm" onclick="document.getElementById('reject-form-{{ $deletionRequest->id }}').classList.toggle('is-open')">Reject</button>
                <form id="reject-form-{{ $deletionRequest->id }}" method="POST" action="{{ route('admin.deletion-requests.reject', $deletionRequest) }}" class="reject-form" style="display:none;margin-top:8px;min-width:220px">
                  @csrf
                  <textarea name="review_note" rows="2" placeholder="Optional review note" maxlength="1000"></textarea>
                  <button type="submit" class="btn btn--ghost btn--sm" style="margin-top:6px">Confirm reject</button>
                </form>
              @else
                <span style="color:var(--muted)">&mdash;</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" style="color:var(--muted);text-align:center;padding:30px">No deletion requests yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px">
    {{ $requests->links() }}
  </div>

  <style>
    .reject-form.is-open { display:block !important; }
  </style>
</x-layouts.admin>
