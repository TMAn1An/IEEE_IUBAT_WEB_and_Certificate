@props(['trail'])
<nav class="crumbs" aria-label="Breadcrumb">
  <div class="wrap"><ol>
    @foreach ($trail as $step)
      @if ($loop->last)
        <li aria-current="page">{!! $step['label'] !!}</li>
      @else
        <li><a href="{{ $step['url'] ?? '' }}">{!! $step['label'] !!}</a></li>
      @endif
    @endforeach
  </ol></div>
</nav>
