<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>{{ $category->name ?? 'QR Tool' }} Codeword &amp; QR Generator &mdash; IEEE IUBAT Admin</title>
{{-- Vendored as-is from IEEEQRCODEGENERATOR-main/static/css/style.css for
     visual parity with the old tool -- see
     docs/CERTIFICATE_SYSTEM.md §Simple QR tool: old-tool-parity rebuild. --}}
<link rel="stylesheet" href="/css/qr-tool.css">
<style>
  /* Minimal admin-navigation strip only -- NOT the full admin sidebar
     theme, which would visually clash with the old tool's own look. Keeps
     the other admin sections reachable without redesigning this page. */
  .admin-strip { background: #172033; padding: 10px 16px; display: flex; gap: 16px; align-items: center; font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 13px; flex-wrap: wrap; }
  .admin-strip a { color: #cbd5e1; text-decoration: none; }
  .admin-strip a:hover, .admin-strip a.is-active { color: #fff; text-decoration: underline; }
  .admin-strip form { margin-left: auto; }
  .admin-strip button { background: none; border: none; color: #cbd5e1; cursor: pointer; font: inherit; padding: 0; }
</style>
</head>
<body>
  <div class="admin-strip">
    <a href="{{ route('admin.dashboard') }}">Admin Dashboard</a>
    <a href="{{ route('admin.qr.generate.show') }}" class="is-active">Generate QR</a>
    <a href="{{ route('admin.qr.records.index') }}">All Records</a>
    <a href="{{ route('admin.qr.groups.index') }}">Groups</a>
    <a href="{{ route('admin.qr.import.choose-group') }}">Import Excel</a>
    <a href="{{ route('admin.qr.categories.index') }}">QR Categories</a>
    <form method="POST" action="{{ route('admin.logout') }}">
      @csrf
      <button type="submit">Log out</button>
    </form>
  </div>

  <div class="page-shell">
    <header class="topbar">
      <div>
        <div class="eyebrow">{{ $category->event_name ?? $category->name ?? 'QR Tool' }}</div>
        <h1>Codeword &amp; QR Generator</h1>
        <p class="subtext">Create a unique codeword, save the record to the database, and generate a QR code.</p>
      </div>
      @if ($result)
        <a class="button secondary" href="{{ route('admin.qr.generate.download-excel') }}">Download Excel</a>
      @endif
    </header>

    @if (! $category)
      <div class="messages">
        <div class="message error">
          The QR tool's primary category isn't set up yet. Run <code>php artisan db:seed --class=QrCategorySeeder</code>.
        </div>
      </div>
    @endif

    @if (session('status'))
      <div class="messages"><div class="message {{ $resultIsDuplicate ?? false ? 'warning' : '' }}">{{ session('status') }}</div></div>
    @endif
    @if ($errors->any())
      <div class="messages">
        @foreach ($errors->all() as $error)
          <div class="message error">{{ $error }}</div>
        @endforeach
      </div>
    @endif

    <main class="grid">
      <section class="card">
        <h2>Create Entry</h2>

        <form action="{{ route('admin.qr.generate.store') }}" method="POST" id="generatorForm">
          @csrf
          <label class="check-row">
            <input type="checkbox" id="include_conference" name="include_conference" value="1" @checked(old('include_conference', true)) onchange="toggleConferenceField()">
            Include conference/event in QR
          </label>
          <div id="conferenceField">
            <label for="conference_type">Conference or Event</label>
            <select id="conference_type" name="conference_type" onchange="toggleConferenceName()">
              <option value="">Select Type</option>
            </select>
            <div class="option-tools">
              <input id="conference_type_add" type="text" placeholder="Add type option">
              <button type="button" class="small-button" onclick="addConferenceType()">Add</button>
              <button type="button" class="small-button danger" onclick="removeConferenceType()">Remove selected</button>
            </div>

            <div id="conferenceNameWrap" style="display:none">
              <label for="conference_select">Select Name</label>
              <select id="conference_select" name="conference_select">
                <option value="">Select Name</option>
              </select>
              <div class="option-tools">
                <input id="conference_option_add" type="text" placeholder="Add conference name">
                <button type="button" class="small-button" onclick="addConferenceOption()">Add</button>
                <button type="button" class="small-button danger" onclick="removeConferenceOption()">Remove selected</button>
              </div>
            </div>
          </div>

          <label for="role_select">Role</label>
          <select id="role_select" name="role_select" required>
            <option value="">Select Role</option>
          </select>
          <div class="option-tools">
            <input id="role_option_add" type="text" placeholder="Add role option">
            <button type="button" class="small-button" onclick="addRoleOption()">Add</button>
            <button type="button" class="small-button danger" onclick="removeRoleOption()">Remove selected</button>
          </div>

          <label for="name">Name</label>
          <input
              id="name"
              name="name"
              type="text"
              placeholder="Example: Dr. Hadaate Ullah"
              value="{{ old('name') }}"
              required
              maxlength="160"
          >

          <label class="check-row">
            <input type="checkbox" id="include_session" name="include_session" value="1" @checked(old('include_session')) onchange="toggleSessionField()">
            Include session in QR
          </label>
          <div id="sessionField" style="display:none">
            <label for="session">Session</label>
            <textarea
                id="session"
                name="session"
                rows="5"
                placeholder="Example: Technical Session &ndash; TS-1: BSP1: Biomedical Signal Processing -1"
                maxlength="500"
            >{{ old('session') }}</textarea>
          </div>

          <button class="button primary full" type="submit" id="generateBtn" @disabled(! $category)>Generate Codeword + QR</button>
        </form>
      </section>

      <section class="card result-card">
        <h2>Generated Result</h2>

        @if ($result)
          @php $verificationUrl = app(\App\Services\Certificates\QrCodeService::class)->verificationUrlForCodeword($result->codeword); @endphp
          <div class="result-layout">
            <div class="result-info">
              @if ($result->event_name)
                <div class="info-row">
                  <span>Conference/Event</span>
                  <strong>{{ $result->event_name }}</strong>
                </div>
              @endif
              <div class="info-row">
                <span>Role</span>
                <strong>{{ $result->data['role'] ?? '' }}</strong>
              </div>
              <div class="info-row">
                <span>Name</span>
                <strong>{{ $result->recipient_name }}</strong>
              </div>
              @if (! empty($result->data['session']))
                <div class="info-row">
                  <span>Session</span>
                  <strong>{{ $result->data['session'] }}</strong>
                </div>
              @endif

              <div class="code-box">
                <span>Codeword</span>
                <strong id="codeword">{{ $result->codeword }}</strong>
                <button type="button" class="copy-btn" onclick="copyCodeword()">Copy</button>
              </div>

              <div class="success-note">
                {{ $resultIsDuplicate ?? false ? 'Matched an existing entry -- showing its codeword and QR.' : 'Saved to the database.' }}
              </div>

              @if (! empty($resultGroup))
                <div class="info-row" style="margin-top:14px">
                  <span>Saved under</span>
                  <strong>{{ $resultGroup->event_type }} / {{ $resultGroup->event_name }} / {{ $resultGroup->role }}</strong>
                </div>
                <div class="info-row">
                  <span>Records in this group</span>
                  <strong>{{ $resultGroupCount }}</strong>
                </div>
              @endif

              <input type="text" id="verify-url" value="{{ $verificationUrl }}" style="margin-top:14px" readonly onclick="this.select()">
              <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
                <button type="button" class="small-button" id="copy-link-btn">Copy verification link</button>
                <a class="small-button" href="{{ $verificationUrl }}" target="_blank" rel="noopener">View verification</a>
              </div>
              <p id="copy-status" style="color:var(--muted);font-size:13px;margin-top:8px"></p>
            </div>

            <div class="qr-panel">
              <img
                  id="qr-image"
                  src="{{ route('admin.qr.records.qr-image', $result) }}"
                  alt="Generated QR Code"
                  class="qr-image"
              >
              <a
                  class="button secondary full"
                  href="{{ route('admin.qr.records.qr-image', $result) }}"
                  download="{{ $result->codeword }}-qr.png"
              >
                Download QR
              </a>
            </div>
          </div>
        @else
          <div class="empty-state">
            <div class="qr-placeholder"></div>
            <p>Your generated QR code and codeword will appear here.</p>
          </div>
        @endif
      </section>
    </main>

    <section class="card recent-card">
      <div class="section-heading">
        <div>
          <h2>Recent Entries</h2>
          <p>Last 10 records saved in the database.</p>
        </div>
        <a class="small-button" href="{{ route('admin.qr.records.index') }}">View all &amp; search</a>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>SL</th>
              <th>Conference</th>
              <th>Role</th>
              <th>Name</th>
              <th>Session</th>
              <th>Codeword</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($recentEntries as $index => $row)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $row->event_name }}</td>
                <td>{{ $row->data['role'] ?? '' }}</td>
                <td><a href="{{ route('admin.qr.records.show', $row) }}">{{ $row->recipient_name }}</a></td>
                <td>{{ $row->data['session'] ?? '' }}</td>
                <td><code>{{ $row->codeword }}</code></td>
                <td>{{ $row->created_at->format('Y-m-d H:i:s') }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="empty-cell">No records yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <script>
    window.QR_TOOL_CONFIG = {
      roleOptions: @json($roleOptions),
      conferenceTypes: @json($conferenceTypes->map(fn ($t) => ['name' => $t->name, 'options' => $t->options->pluck('name')->all()])->values()),
      urls: {
        addRole: @json(route('admin.qr.options.roles.add')),
        removeRole: @json(route('admin.qr.options.roles.remove')),
        addType: @json(route('admin.qr.options.conference-types.add')),
        removeType: @json(route('admin.qr.options.conference-types.remove')),
        addOption: @json(route('admin.qr.options.conference-options.add')),
        removeOption: @json(route('admin.qr.options.conference-options.remove')),
      },
      selected: {
        roleSelect: @json(old('role_select')),
        conferenceType: @json(old('conference_type')),
        conferenceSelect: @json(old('conference_select')),
      },
      csrf: @json(csrf_token()),
    };
  </script>
  <script src="/js/admin/qr-tool.js"></script>
</body>
</html>
