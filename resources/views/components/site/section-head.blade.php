@props(['eyebrow', 'title', 'lead' => ''])
<div class="head rv">
  <span class="eyebrow">{!! $eyebrow !!}</span>
  <h2>{!! $title !!}</h2>
  @if ($lead !== '')
    <p>{!! $lead !!}</p>
  @endif
</div>
