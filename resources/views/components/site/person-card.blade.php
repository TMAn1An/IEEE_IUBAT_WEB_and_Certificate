@props(['person', 'rv' => true, 'tel' => true])
@php
    $photoSrc = $siteContent->memberPhoto($person['photo'] ?? null);
    $dial = isset($person['tel']) ? preg_replace('/\s+/', '', $person['tel']) : null;
@endphp
<div class="person{{ $rv ? ' rv' : '' }}">
  @if ($photoSrc)
    <img class="person__av" src="{{ $photoSrc }}" alt="Photo of {{ $person['name'] }}" width="80" height="80">
  @else
    <div class="person__av">{{ $person['initials'] ?? '--' }}</div>
  @endif
  <div class="person__role">{!! $person['role'] !!}</div>
  <h3>{{ $person['name'] }}</h3>
  @if (!empty($person['detail']))
    <p class="person__detail">{!! $person['detail'] !!}</p>
  @endif
  @if ($tel && !empty($person['tel']))
    <a class="person__link" href="tel:{{ $dial }}">@icon('phone') {{ $person['tel'] }}</a>
  @endif
  @if (!empty($person['email']))
    <a class="person__link" href="mailto:{{ $person['email'] }}">@icon('mail') {{ $person['email'] }}</a>
  @endif
</div>
