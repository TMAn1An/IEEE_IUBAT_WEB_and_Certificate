@props(['current' => ''])
@php
    $event = config('site.event');
    $nav = config('site.nav');
    $alert = $siteContent->headerAlert($event);
    $navCta = $siteContent->navCta($event);
@endphp
<a class="skip-link" href="#main">Skip to content</a>

@if ($alert)
{{-- Alert banner. IEEE asks that sites keep a prominent slot for
     time-sensitive notices. Self-clearing once eventPhase() reaches
     'ended' — see App\Services\SiteContentService::headerAlert(). --}}
<div class="alert" role="region" aria-label="Site notice">
  <div class="wrap alert__in">
    @icon('bell')
    <p class="alert__text"><strong>{{ $event['name'] }}</strong> &mdash; {!! $alert['text'] !!} <a href="{{ $alert['linkUrl'] }}">{{ $alert['linkLabel'] }}</a></p>
    <button class="alert__x" type="button" aria-label="Dismiss notice">&times;</button>
  </div>
</div>
@endif

{{-- IEEE enterprise meta-navigation. Required on all IEEE websites.
     Do not reorder or rename these links. --}}
<div class="metanav">
  <div class="wrap metanav__in">
    <ul>
      <li><a href="https://www.ieee.org/">IEEE.org</a></li>
      <li><a href="https://ieeexplore.ieee.org/">IEEE <em>Xplore</em>&reg; Digital Library</a></li>
      <li><a href="https://standards.ieee.org/">IEEE Standards</a></li>
      <li><a href="https://spectrum.ieee.org/">IEEE Spectrum</a></li>
      <li><a href="https://www.ieee.org/sitemap.html">More Sites</a></li>
    </ul>
    <ul>
      <li><a href="https://www.ieee.org/join">Join IEEE</a></li>
      <li><a href="https://www.ieee.org/give">Donate</a></li>
    </ul>
  </div>
</div>

<header class="header">
  <div class="wrap header__bar">

    {{-- Site identifier: upper left, links to the home page, contains IEEE,
         and is larger than the IEEE Master Brand. --}}
    <a class="identifier" href="/">
      <img src="{{ config('site.site.logo') }}" alt="">
      <span class="identifier__txt">
        <span class="identifier__name">{{ config('site.site.name') }}</span>
        <span class="identifier__tag">{{ config('site.site.tagline') }}</span>
      </span>
    </a>

    <button class="burger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="site-nav">
      <span></span>
    </button>

    <div class="header__right">
      <nav aria-label="Main">
        <ul class="nav" id="site-nav">
        @foreach ($nav as $key => $item)
          @if (!empty($item['panel']))
            <li class="nav__item has-panel{{ $current === $key ? ' is-current' : '' }}">
              <button class="nav__link" type="button" aria-expanded="false">{{ $item['label'] }}@icon('caret', 'class="nav__caret"')</button>
              <ul class="nav__panel">
                @foreach ($item['panel'] as $p)
                  <li><a href="{{ $p['url'] }}">{{ $p['label'] }}<small>{!! $p['sub'] !!}</small></a></li>
                @endforeach
              </ul>
            </li>
          @else
            <li class="nav__item{{ $current === $key ? ' is-current' : '' }}"><a class="nav__link" href="{{ $item['url'] }}"@if($current === $key) aria-current="page"@endif>{{ $item['label'] }}</a></li>
          @endif
        @endforeach
          <li class="nav__cta">
            <a class="btn btn--green btn--sm" href="{{ $navCta['url'] }}"@if($navCta['external']) target="_blank" rel="noopener"@endif>{{ $navCta['label'] }}</a>
          </li>
        </ul>
      </nav>

      {{-- IEEE Master Brand: upper right, minimum 100x33px, white or black
           only, alt text exactly "IEEE", links to www.ieee.org. --}}
      <a class="masterbrand" href="https://www.ieee.org" aria-label="IEEE">
        <img src="/assets/img/ieee-masterbrand-black.png" alt="IEEE" width="113" height="33">
      </a>
    </div>

  </div>
</header>
