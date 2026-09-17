@props(['title', 'sub', 'buttons'])
<section class="cta">
  <div class="wrap">
    <h2>{!! $title !!}</h2>
    <p>{!! $sub !!}</p>
    <div class="cta__row">
      @foreach ($buttons as $btn)
        <a class="btn btn--{{ $btn[2] }}" href="{{ $btn[1] }}"@if(!empty($btn[3] ?? null)) target="_blank" rel="noopener"@endif>{!! $btn[0] !!}</a>
      @endforeach
    </div>
  </div>
</section>
