@props(['count', 'label', 'suffix' => '', 'extraClass' => ''])
<div class="stat{{ $extraClass ? ' '.$extraClass : '' }}"><div class="stat__n"><span data-count="{{ $count }}"@if($suffix !== '') data-suffix="{{ $suffix }}"@endif>{{ $count }}{{ $suffix }}</span></div><div class="stat__l">{!! $label !!}</div></div>
