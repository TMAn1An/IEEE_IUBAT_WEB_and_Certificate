@props(['template', 'current', 'batch' => null])
{{--
  Plain-Blade counterpart to pdfeditor's src/integration/Stepper.tsx -- same
  4 steps, same visual language (active/completed/future), same meaning of
  "completed" (a saved project / a batch that exists), used on the one page
  in the guided workflow that Laravel renders instead of React (Upload
  participants). Kept deliberately framework-free, matching admin.css's
  "no build step" approach, rather than duplicating the React component
  here.
--}}
@php
  $hasSavedProject = $template->hasEditorProject();
  $steps = [
    ['key' => 'design', 'label' => 'Design', 'helper' => 'Lay out fields on the PDF', 'href' => route('admin.pdf-studio.show', $template), 'done' => $hasSavedProject],
    ['key' => 'configure', 'label' => 'Configure data', 'helper' => 'Map fields to Excel columns', 'href' => route('admin.pdf-studio.show', $template), 'done' => $hasSavedProject],
    ['key' => 'participants', 'label' => 'Upload participants', 'helper' => 'Validate the spreadsheet', 'href' => route('admin.pdf-studio.prepare', $template), 'done' => $batch !== null],
    ['key' => 'generate', 'label' => 'Generate & download', 'helper' => 'Create certificates', 'href' => $batch ? route('admin.pdf-studio.show-batch', [$template, $batch]) : null, 'done' => false],
  ];
@endphp
<ol class="pdf-stepper" aria-label="Certificate workflow steps">
  @foreach ($steps as $i => $step)
    @php
      $isCurrent = $step['key'] === $current;
      $isDone = $step['done'] && !$isCurrent;
      $isClickable = $isDone || $isCurrent || ($step['href'] !== null);
    @endphp
    <li class="pdf-step{{ $isCurrent ? ' pdf-step--current' : '' }}{{ $isDone ? ' pdf-step--done' : '' }}">
      @if ($isClickable && $step['href'])
        <a href="{{ $step['href'] }}" class="pdf-step__button" title="{{ $step['helper'] }}">
          <span class="pdf-step__badge">{{ $isDone ? '✓' : $i + 1 }}</span>
          <span class="pdf-step__text">
            <span class="pdf-step__label">{{ $step['label'] }}</span>
            <span class="pdf-step__helper">{{ $step['helper'] }}</span>
          </span>
        </a>
      @else
        <span class="pdf-step__button" title="{{ $step['helper'] }}">
          <span class="pdf-step__badge">{{ $isDone ? '✓' : $i + 1 }}</span>
          <span class="pdf-step__text">
            <span class="pdf-step__label">{{ $step['label'] }}</span>
            <span class="pdf-step__helper">{{ $step['helper'] }}</span>
          </span>
        </span>
      @endif
      @if (!$loop->last)<span class="pdf-step__connector" aria-hidden="true"></span>@endif
    </li>
  @endforeach
</ol>
<style>
  .pdf-stepper { display:flex; align-items:flex-start; list-style:none; margin:0 0 20px; padding:10px 14px; gap:0; background:#fff; border:1px solid var(--line); border-radius:var(--r); overflow-x:auto; }
  .pdf-step { display:flex; align-items:center; flex:0 0 auto; }
  .pdf-step__button { display:flex; align-items:center; gap:10px; padding:6px 10px; border-radius:var(--r); text-decoration:none; color:inherit; }
  .pdf-step--current .pdf-step__button { background:#e8f1f8; }
  a.pdf-step__button:hover { background:#f4f6f8; }
  .pdf-step__badge { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; flex:0 0 26px; border-radius:50%; font-size:.8em; font-weight:700; background:#f4f6f8; color:var(--muted); border:1.5px solid var(--line); }
  .pdf-step--current .pdf-step__badge { background:var(--ieee-blue); color:#fff; border-color:var(--ieee-blue); }
  .pdf-step--done .pdf-step__badge { background:var(--ok-text); color:#fff; border-color:var(--ok-text); }
  .pdf-step__text { display:flex; flex-direction:column; line-height:1.25; }
  .pdf-step__label { font-weight:600; font-size:.92em; }
  .pdf-step__helper { font-size:.78em; color:var(--muted); white-space:nowrap; }
  .pdf-step__connector { width:28px; height:1.5px; margin:0 4px; background:var(--line); flex:0 0 28px; }
  @media (max-width:640px) { .pdf-step__helper { display:none; } .pdf-step__connector { width:14px; flex-basis:14px; } }
</style>
