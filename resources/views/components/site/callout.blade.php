@props(['icon', 'title', 'titleTag' => 'h3', 'green' => true])
<div class="callout{{ $green ? ' callout--green' : '' }}">
  @icon($icon)
  <div>
    <{{ $titleTag }}>{!! $title !!}</{{ $titleTag }}>
    {{ $slot }}
  </div>
</div>
