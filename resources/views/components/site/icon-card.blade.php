@props(['icon' => '', 'title', 'body', 'card' => '', 'titleTag' => 'h3', 'rv' => true])
<article class="card{{ $card ? ' '.$card : '' }}{{ $rv ? ' rv' : '' }}">
  @if ($icon !== '')
    <div class="card__icon">@icon($icon)</div>
  @endif
  <{{ $titleTag }}>{!! $title !!}</{{ $titleTag }}>
  <p>{!! $body !!}</p>
</article>
