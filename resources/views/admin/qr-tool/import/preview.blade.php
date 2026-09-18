<x-layouts.admin title="Import Preview">
  <div class="admin-card">
    <h2 style="margin-top:0">Import preview</h2>

    <table class="admin-table">
      <tbody>
        <tr><th style="width:220px">Destination</th><td>{{ $group->event_type }} / {{ $group->event_name }} / {{ $group->role }}</td></tr>
        <tr><th>Rows found</th><td>{{ $totalRows }}</td></tr>
        <tr><th>Valid</th><td>{{ $validCount }}</td></tr>
        <tr><th>Duplicates</th><td>{{ $duplicateCount }}</td></tr>
        <tr><th>Errors</th><td>{{ $invalidCount }}</td></tr>
      </tbody>
    </table>

    @if ($duplicateCount > 0)
      <h3 style="font-size:1rem">Duplicate rows (already exist in this group){{ $moreDuplicates > 0 ? ' (first 25 shown)' : '' }}</h3>
      <ul>
        @foreach ($sampleDuplicates as $row)
          <li>Row {{ $row->rowNumber }}: {{ implode(' ', $row->errors) }}</li>
        @endforeach
      </ul>
      @if ($moreDuplicates > 0)
        <p style="color:var(--muted)">...and {{ $moreDuplicates }} more duplicates. Download the full error report below.</p>
      @endif
    @endif

    @if ($invalidCount > 0)
      <h3 style="font-size:1rem">Row errors{{ $moreErrors > 0 ? ' (first 25 shown)' : '' }}</h3>
      <ul>
        @foreach ($sampleErrors as $row)
          <li>Row {{ $row->rowNumber }}: {{ implode(' ', $row->errors) }}</li>
        @endforeach
      </ul>
      @if ($moreErrors > 0)
        <p style="color:var(--muted)">...and {{ $moreErrors }} more. Download the full error report below.</p>
      @endif
    @endif

    <p style="color:var(--muted)">
      Importing will create {{ $validCount }} record(s) in this group. {{ $duplicateCount }} duplicate
      and {{ $invalidCount }} invalid row(s) will be skipped — nothing is imported until you confirm
      below.
    </p>

    <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
      <form method="POST" action="{{ route('admin.qr.import.confirm', $group) }}">
        @csrf
        <input type="hidden" name="stored_file" value="{{ $storedFile }}">
        @foreach ($mapping as $index => $target)
          <input type="hidden" name="mapping[{{ $index }}]" value="{{ $target }}">
        @endforeach
        <button type="submit" class="btn btn--primary" @disabled($validCount === 0)>Confirm import ({{ $validCount }} rows)</button>
      </form>

      @if ($invalidCount > 0 || $duplicateCount > 0)
        <form method="POST" action="{{ route('admin.qr.import.errors', $group) }}">
          @csrf
          <input type="hidden" name="stored_file" value="{{ $storedFile }}">
          @foreach ($mapping as $index => $target)
            <input type="hidden" name="mapping[{{ $index }}]" value="{{ $target }}">
          @endforeach
          <button type="submit" class="btn btn--ghost">Download error report (CSV)</button>
        </form>
      @endif

      <a href="{{ route('admin.qr.import.choose-group') }}" class="btn btn--ghost">Cancel</a>
    </div>
  </div>
</x-layouts.admin>
