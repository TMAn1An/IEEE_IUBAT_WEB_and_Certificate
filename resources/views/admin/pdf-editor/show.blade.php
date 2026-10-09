<x-layouts.admin title="Batch #{{ $batch->id }}">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h2 style="margin:0">Batch #{{ $batch->id }} — {{ $batch->template->name }}</h2>
    <span class="badge">{{ $batch->status->label() }}</span>
  </div>

  @if (session('status'))
    <div class="alert alert--ok">{{ session('status') }}</div>
  @endif

  @if (session('bridge-errors') && count(session('bridge-errors')) > 0)
    <div class="alert alert--error">
      <strong>Row errors:</strong>
      <ul style="margin:4px 0 0;padding-left:18px">
        @foreach (session('bridge-errors') as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="admin-card">
    <h3>Step 1 — Design in the PDF editor</h3>
    <p style="color:var(--muted)">
      Download these, then open the separate <strong>PDF Template Studio</strong> editor: load the
      spreadsheet, select the QR images as your image-file folder, map the <code>qr_image</code>
      column to a QR-code image field, and set the bulk-export filename pattern to
      <code>{codeword}</code> so each exported PDF is named by its verification codeword.
    </p>
    <a href="{{ route('admin.batches.reservation-excel', $batch) }}" class="btn btn--ghost">Download reservation spreadsheet (.xlsx)</a>
    <a href="{{ route('admin.batches.qr-zip', $batch) }}" class="btn btn--ghost">Download QR codes (.zip)</a>
  </div>

  <div class="admin-card">
    <h3>Step 2 — Upload the generated PDFs</h3>
    <p style="color:var(--muted)">Upload the ZIP (or single PDF) the editor exported. Each file is matched by filename to a reserved row.</p>
    <form method="POST" action="{{ route('admin.batches.finalize', $batch) }}" enctype="multipart/form-data">
      @csrf
      <input type="file" name="package" accept=".zip,.pdf" required>
      <button type="submit" class="btn btn--primary">Finalize</button>
    </form>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Row</th>
          <th>Recipient</th>
          <th>Certificate number</th>
          <th>Codeword</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($batch->reservations as $reservation)
          <tr>
            <td>{{ $reservation->row_index + 2 }}</td>
            <td>{{ $reservation->recipient_name }}</td>
            <td><code>{{ $reservation->certificate_number }}</code></td>
            <td><code style="font-size:.8em">{{ $reservation->codeword }}</code></td>
            <td><span class="badge">{{ $reservation->status->label() }}</span></td>
            <td style="text-align:right">
              @if ($reservation->certificate)
                <a href="{{ route('admin.certificates.show', $reservation->certificate) }}" class="btn btn--ghost">View certificate</a>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</x-layouts.admin>
