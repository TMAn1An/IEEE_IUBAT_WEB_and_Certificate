@props(['person', 'rv' => true, 'tel' => true])
@php
    $photoSrc = $siteContent->memberPhoto($person['photo'] ?? null);
    $dial = isset($person['tel']) ? preg_replace('/\s+/', '', $person['tel']) : null;
    // Per-member override for where a photo is "anchored" inside the circular
    // crop -- e.g. 'top' or '50% 30%' -- for source photos whose framing isn't
    // centered. Defaults to 'center' (today's existing behavior, unchanged for
    // every member that doesn't set this). See config/site.php's 'people' doc
    // comment. This cannot recover pixels a photo doesn't contain (e.g. a head
    // already cropped at the top edge of the source file) -- only re-centers
    // within what the photo actually shows.
    $avatarPosition = $person['avatar_position'] ?? 'center';
@endphp
<div class="person{{ $rv ? ' rv' : '' }}">
  @if ($photoSrc)
    <img class="person__av" src="{{ $photoSrc }}" alt="Photo of {{ $person['name'] }}" width="80" height="80" style="object-position: {{ $avatarPosition }};">
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
