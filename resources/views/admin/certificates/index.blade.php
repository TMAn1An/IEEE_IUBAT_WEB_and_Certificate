<x-layouts.admin title="Certificates">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap">
    <form method="GET" action="{{ route('admin.certificates.index') }}" style="display:flex;gap:8px">
      <input type="search" name="search" value="{{ $search }}" placeholder="Search by certificate number, recipient name, or codeword">
      <button type="submit" class="btn btn--ghost">Search</button>
      @if ($search !== '')
        <a href="{{ route('admin.certificates.index') }}" class="btn btn--ghost">Clear</a>
      @endif
    </form>
    <div style="display:flex;gap:8px">
      @can('viewDeleted', \App\Models\Certificate::class)
        <a href="{{ route('admin.certificates.deleted') }}" class="btn btn--ghost">Deleted Certificates</a>
      @endcan
      <a href="{{ route('admin.pdf-certificates.index') }}" class="btn btn--primary">Issue via PDF Certificates</a>
    </div>
  </div>

  @can('create', \App\Models\DeletionRequest::class)
    <form method="POST" action="{{ route('admin.certificates.request-deletion.bulk') }}" id="bulk-deletion-form" onsubmit="return window.ieeeBulkCertDeletion.confirmSubmit(event)">
      @csrf
      <div class="admin-card" id="bulk-deletion-bar" style="display:none;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
          <strong><span class="bulk-count-display">0</span> selected</strong>
          <button type="button" class="btn btn--ghost btn--sm" onclick="window.ieeeBulkCertDeletion.clearSelection()">Clear selection</button>
        </div>
        <p style="color:var(--muted);margin:10px 0 0">
          Submitting this does not delete anything immediately — it creates one pending deletion
          request per selected certificate, for Super Admin review. See the shared reason below.
        </p>
        <div class="field" style="margin-top:10px">
          <label for="bulk-reason">Reason for deletion (applies to every selected certificate)</label>
          <textarea id="bulk-reason" name="reason" rows="3" maxlength="1000" placeholder="Example: Duplicate batch import, test data, wrong event...">{{ old('reason') }}</textarea>
          @error('reason')<div class="error">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn--danger btn--sm" id="bulk-submit-btn" disabled style="margin-top:8px">
          Request deletion (<span class="bulk-count-display">0</span>)
        </button>
      </div>
    </form>
  @endcan

  <div class="admin-card" style="padding:0;overflow-x:auto">
    <table class="admin-table">
      <thead>
        <tr>
          @can('create', \App\Models\DeletionRequest::class)
            <th style="width:36px">
              <input type="checkbox" id="select-page-checkbox" aria-label="Select this page">
            </th>
          @endcan
          <th>Certificate number</th>
          <th>Recipient</th>
          <th>Category</th>
          <th>Codeword</th>
          <th>Created</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($certificates as $certificate)
          @php $hasPendingRequest = in_array($certificate->id, $pendingCertificateIds, true); @endphp
          <tr>
            @can('create', \App\Models\DeletionRequest::class)
              <td>
                @if ($hasPendingRequest)
                  <input type="checkbox" disabled title="A deletion request is already pending for this certificate">
                @else
                  <input type="checkbox" class="bulk-select-checkbox" form="bulk-deletion-form" name="certificate_ids[]" value="{{ $certificate->id }}" aria-label="Select certificate {{ $certificate->certificate_number }}">
                @endif
              </td>
            @endcan
            <td><code>{{ $certificate->certificate_number }}</code></td>
            <td>{{ $certificate->recipient_name }}</td>
            <td>{{ $certificate->template->name }}</td>
            <td><code style="font-size:.8em">{{ \Illuminate\Support\Str::limit($certificate->codeword, 12, '…') }}</code></td>
            <td>{{ $certificate->issued_at?->format('j M Y') }}</td>
            <td>
              <span class="badge {{ $certificate->status->badgeClass() }}">{{ $certificate->status->label() }}</span>
              @if ($hasPendingRequest)
                <span class="badge" style="margin-left:4px">Deletion pending</span>
              @endif
            </td>
            <td style="text-align:right;white-space:nowrap">
              <a href="{{ route('admin.certificates.show', $certificate) }}" class="btn btn--ghost btn--sm">View</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" style="color:var(--muted);text-align:center;padding:30px">
              @if ($search !== '')
                No certificates match "{{ $search }}".
              @else
                No certificates yet. <a href="{{ route('admin.pdf-certificates.index') }}">Issue the first one via PDF Certificates</a>.
              @endif
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px">
    {{ $certificates->links() }}
  </div>

  @can('create', \App\Models\DeletionRequest::class)
    <script>
      (function () {
        var form = document.getElementById('bulk-deletion-form');
        var bar = document.getElementById('bulk-deletion-bar');
        var submitBtn = document.getElementById('bulk-submit-btn');
        var selectPageCb = document.getElementById('select-page-checkbox');
        var countDisplays = document.querySelectorAll('.bulk-count-display');

        function rowCheckboxes() {
          return Array.prototype.slice.call(document.querySelectorAll('.bulk-select-checkbox'));
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
          // Selects/deselects every ELIGIBLE row on this page only -- it
          // never implies "every record", and disabled (already-pending)
          // rows are never toggled by it. See
          // docs/CERTIFICATE_SYSTEM.md §Bulk deletion requests.
          selectPageCb.addEventListener('change', function () {
            rowCheckboxes().forEach(function (cb) { cb.checked = selectPageCb.checked; });
            refresh();
          });
        }

        window.ieeeBulkCertDeletion = {
          clearSelection: function () {
            rowCheckboxes().forEach(function (cb) { cb.checked = false; });
            refresh();
          },
          confirmSubmit: function (event) {
            var n = rowCheckboxes().filter(function (cb) { return cb.checked; }).length;
            var reason = document.getElementById('bulk-reason').value.trim();

            if (n === 0 || form.dataset.submitting === '1') {
              event.preventDefault();
              return false;
            }

            if (reason === '') {
              event.preventDefault();
              document.getElementById('bulk-reason').focus();
              return false;
            }

            if (!window.confirm('Request deletion for ' + n + ' certificate(s)? This submits them for Super Admin review -- nothing is deleted yet.')) {
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
  @endcan
</x-layouts.admin>
