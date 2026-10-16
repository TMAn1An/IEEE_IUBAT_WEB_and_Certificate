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

  <form method="POST" action="{{ route('admin.deletion-requests.bulk-approve') }}" id="bulk-approve-form" onsubmit="return window.ieeeBulkApprove.confirmSubmit(event)">
    @csrf
    <div class="admin-card" id="bulk-approve-bar" style="display:none;margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <strong><span class="bulk-approve-count">0</span> selected</strong>
        <button type="button" class="btn btn--ghost btn--sm" onclick="window.ieeeBulkApprove.clearSelection()">Clear selection</button>
      </div>
      <p style="color:var(--danger);margin:10px 0 0">
        Approving soft-deletes every selected record immediately and cannot be undone through this
        screen. Make sure every selected request is really meant to be approved.
      </p>
      <button type="submit" class="btn btn--primary btn--sm" id="bulk-approve-btn" disabled style="margin-top:8px">
        Approve selected (<span class="bulk-approve-count">0</span>)
      </button>
    </div>

    <div class="admin-card" style="padding:0;overflow-x:auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width:36px">
              <input type="checkbox" id="select-page-approve-checkbox" aria-label="Select this page">
            </th>
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
            @php
              $record = $deletionRequest->record();
              $isPending = $deletionRequest->status === DeletionRequestStatus::Pending;
              $canReview = $isPending && auth()->user()->can('review', $deletionRequest);
            @endphp
            <tr>
              <td>
                @if ($canReview)
                  <input type="checkbox" class="bulk-approve-checkbox" form="bulk-approve-form" name="deletion_request_ids[]" value="{{ $deletionRequest->id }}" aria-label="Select deletion request #{{ $deletionRequest->id }}">
                @else
                  <input type="checkbox" disabled title="{{ $isPending ? 'You are not allowed to review this request' : 'This request has already been reviewed' }}">
                @endif
              </td>
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
              <td colspan="9" style="color:var(--muted);text-align:center;padding:30px">No deletion requests yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </form>

  <div style="margin-top:16px">
    {{ $requests->links() }}
  </div>

  <style>
    .reject-form.is-open { display:block !important; }
  </style>

  <script>
    (function () {
      var form = document.getElementById('bulk-approve-form');
      var bar = document.getElementById('bulk-approve-bar');
      var submitBtn = document.getElementById('bulk-approve-btn');
      var selectPageCb = document.getElementById('select-page-approve-checkbox');
      var countDisplays = document.querySelectorAll('.bulk-approve-count');

      function rowCheckboxes() {
        return Array.prototype.slice.call(document.querySelectorAll('.bulk-approve-checkbox'));
      }

      function refresh() {
        var boxes = rowCheckboxes();
        var checked = boxes.filter(function (cb) { return cb.checked; });
        var n = checked.length;

        countDisplays.forEach(function (el) { el.textContent = String(n); });
        bar.style.display = boxes.length > 0 ? '' : 'none';
        submitBtn.disabled = n === 0 || form.dataset.submitting === '1';

        if (selectPageCb) {
          selectPageCb.checked = boxes.length > 0 && n === boxes.length;
          selectPageCb.indeterminate = n > 0 && n < boxes.length;
        }
      }

      rowCheckboxes().forEach(function (cb) {
        cb.addEventListener('change', refresh);
      });

      if (selectPageCb) {
        // Selects/deselects every eligible PENDING row on this page only --
        // see docs/CERTIFICATE_SYSTEM.md §Bulk deletion approvals.
        selectPageCb.addEventListener('change', function () {
          rowCheckboxes().forEach(function (cb) { cb.checked = selectPageCb.checked; });
          refresh();
        });
      }

      window.ieeeBulkApprove = {
        clearSelection: function () {
          rowCheckboxes().forEach(function (cb) { cb.checked = false; });
          refresh();
        },
        confirmSubmit: function (event) {
          var n = rowCheckboxes().filter(function (cb) { return cb.checked; }).length;

          if (n === 0 || form.dataset.submitting === '1') {
            event.preventDefault();
            return false;
          }

          if (!window.confirm(
            'Approve ' + n + ' deletion request(s)? Every matching record will be soft-deleted ' +
            'immediately and this cannot be undone through this screen.'
          )) {
            event.preventDefault();
            return false;
          }

          form.dataset.submitting = '1';
          submitBtn.disabled = true;
          return true;
        }
      };

      refresh();
    })();
  </script>
</x-layouts.admin>
