@props(['eyebrow', 'title', 'lead'])
<section class="pagehead">
  <div class="wrap">
    <span class="eyebrow">{!! $eyebrow !!}</span>
    <h1>{!! $title !!}</h1>
    <p>{!! $lead !!}</p>
    {{ $slot }}
  </div>
</section>
