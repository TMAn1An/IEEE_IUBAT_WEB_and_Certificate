@php
  use App\Enums\DeletableRecordType;
@endphp
<x-layouts.admin title="Logbook" wide>
  <p style="color:var(--muted);margin-top:-4px">
    Read-only audit trail of every deletion request, approval, rejection, and soft deletion — see
    docs/CERTIFICATE_SYSTEM.md §Logbook immutability. There is no edit or delete action anywhere on
    this page, including for Super Admin.
  </p>

  <form method="GET" action="{{ route('admin.logbook.index') }}" class="admin-card" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div class="field" style="min-width:160px">
      <label for="from">From</label>
      <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
    </div>
    <div class="field" style="min-width:160px">
      <label for="to">To</label>
      <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
    </div>
    <div class="field" style="min-width:200px">
      <label for="event_type">Action</label>
      <select id="event_type" name="event_type">
        <option value="">All actions</option>
        @foreach ($eventTypes as $type)
          <option value="{{ $type->value }}" @selected(($filters['event_type'] ?? null) === $type->value)>{{ $type->label() }}</option>
        @endforeach
      </select>
    </div>
    <div class="field" style="min-width:200px">
      <label for="actor_id">Actor</label>
      <select id="actor_id" name="actor_id">
        <option value="">All actors</option>
        @foreach ($actors as $actor)
          <option value="{{ $actor->id }}" @selected((string) ($filters['actor_id'] ?? '') === (string) $actor->id)>{{ $actor->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="field" style="min-width:200px">
      <label for="codeword">Codeword / Cert No.</label>
      <input type="text" id="codeword" name="codeword" value="{{ $filters['codeword'] ?? '' }}" placeholder="Search codeword or certificate number">
    </div>
    <button type="submit" class="btn btn--primary btn--sm">Filter</button>
    <a href="{{ route('admin.logbook.index') }}" class="btn btn--ghost btn--sm">Clear</a>
  </form>

  <div class="admin-card" style="padding:0;margin-top:16px">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Date/Time</th>
          <th>Action</th>
          <th>Record type</th>
          <th>Recipient</th>
          <th>Codeword / Cert No.</th>
          <th>Actor</th>
          <th>Actor role</th>
          <th>Request ID</th>
          <th>Summary</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($logs as $log)
          @php $record = $log->record(); @endphp
          <tr>
            <td style="white-space:nowrap">{{ $log->created_at->format('j M Y, g:i A') }}</td>
            <td>{{ $log->event_type->label() }}</td>
            <td>{{ $log->record_type->label() }}</td>
            <td>{{ $record?->recipient_name ?? '—' }}</td>
            <td>
              <code style="font-size:.8em">
                {{ $log->record_type === DeletableRecordType::Certificate ? ($record?->certificate_number ?? $record?->codeword) : $record?->codeword }}
              </code>
            </td>
            <td>{{ $log->actor?->name }}</td>
            <td>{{ $log->actor_role }}</td>
            <td>{{ $log->deletion_request_id ?? '—' }}</td>
            <td style="max-width:320px">{{ $log->summary }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="9" style="color:var(--muted);text-align:center;padding:30px">No log entries match these filters.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px">
    {{ $logs->links() }}
  </div>
</x-layouts.admin>
