@php
    $nav = config('site.nav');
@endphp
<footer class="footer">
  <div class="footer__main">
    <div class="wrap footer__grid">
      <div>
        <div class="footer__id">
          <img src="{{ config('site.site.logo') }}" alt="">
          <span>{!! config('site.site.short') !!}</span>
        </div>
        <p>Advancing technology for humanity &mdash; a student branch of IEEE at IUBAT, Dhaka.</p>
        <div class="social">
          <a href="{{ config('site.site.facebook') }}" target="_blank" rel="noopener" aria-label="Facebook">@icon('facebook')</a>
          <a href="{{ config('site.site.linkedin') }}" target="_blank" rel="noopener" aria-label="LinkedIn">@icon('linkedin')</a>
        </div>
      </div>

      @foreach ($nav as $item)
        @if (!empty($item['panel']))
          <div>
            <h4>{{ $item['label'] }}</h4>
            <ul>
              @foreach ($item['panel'] as $p)
                <li><a href="{{ $p['url'] }}">{{ $p['label'] }}</a></li>
              @endforeach
            </ul>
          </div>
        @endif
      @endforeach

      <div>
        <h4>Contact</h4>
        <ul>
          <li class="footer__line">@icon('pin')<span>{!! config('site.site.address') !!}</span></li>
          <li class="footer__line"><a href="mailto:{{ config('site.site.email') }}">@icon('mail')<span>{{ config('site.site.email') }}</span></a></li>
          <li class="footer__line"><a href="tel:{{ config('site.site.phone_dial') }}">@icon('phone')<span>{{ config('site.site.phone') }}</span></a></li>
        </ul>
      </div>
    </div>
  </div>

  {{-- IEEE-required administrative footer links. Link directly to the IEEE
       policies; do not replicate their text on this site. --}}
  <div class="footer__admin">
    <div class="wrap">
      <ul>
        <li><a href="/">Home</a></li>
        <li><a href="/contact">Contact</a></li>
        <li><a href="https://www.ieee.org/sitemap.html">More Sites</a></li>
        <li><a href="https://www.ieee.org/accessibility_statement.html">Accessibility</a></li>
        <li><a href="https://www.ieee.org/about/corporate/governance/p9-26.html">Nondiscrimination Policy</a></li>
        <li><a href="https://www.ieee-ethics-reporting.org">IEEE Ethics Reporting</a></li>
        <li><a href="https://www.ieee.org/about/help/site_terms_conditions.html">Terms &amp; Disclosures</a></li>
        <li><a href="https://privacy.ieee.org/policies">IEEE Privacy Policy</a></li>
      </ul>
    </div>
  </div>

  <div class="footer__legal">
    <div class="wrap">
      <p>&copy; Copyright <span data-year>2026</span> IEEE &ndash; All rights reserved. Use of this website signifies your agreement to the <a href="https://www.ieee.org/about/help/site_terms_conditions.html">IEEE Terms and Conditions</a>.</p>
      <p>A public charity, IEEE is the world's largest technical professional organization dedicated to advancing technology for the benefit of humanity.</p>
    </div>
  </div>
</footer>

<div class="lb" id="lightbox" role="dialog" aria-modal="true" aria-label="Poster preview">
  <button class="lb__x" type="button" aria-label="Close preview">&times;</button>
  <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="Event poster, enlarged">
</div>

<script>window.IEEE_EVENT = {!! $siteContent->eventClientConfig() !!};</script>
<script src="/assets/js/particles.js"></script>
<script src="/assets/js/main.js"></script>

{{ $slot ?? '' }}
