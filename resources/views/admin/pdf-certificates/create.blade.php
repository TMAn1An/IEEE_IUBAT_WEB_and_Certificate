<x-layouts.admin title="New template">
  <div class="admin-card" style="max-width:560px">
    <h2 style="margin-top:0">New template</h2>
    <p style="color:var(--muted);margin-top:-6px">
      Give it a name and upload the demo certificate PDF you want to design from. You'll land
      straight in the editor afterward — no separate upload step.
    </p>
    <form method="POST" action="{{ route('admin.pdf-certificates.store') }}" enctype="multipart/form-data">
      @csrf

      <div class="field">
        <label for="name">Template name</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus placeholder="e.g. BECITHCON 2026 Speaker">
        @error('name')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="demo_pdf">Demo certificate PDF</label>
        <input type="file" id="demo_pdf" name="demo_pdf" accept="application/pdf" required>
        <div class="help">The Canva-exported (or similar) certificate PDF, up to 10MB. You'll place fields and the QR code on it next.</div>
        @error('demo_pdf')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div class="field">
        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" rows="3">{{ old('description') }}</textarea>
        @error('description')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn--primary">Create &amp; open editor</button>
        <a href="{{ route('admin.pdf-certificates.index') }}" class="btn btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</x-layouts.admin>
