<x-layouts.app
    :page-title="$pageTitle"
    :page-desc="$pageDesc"
    :page-url="$pageUrl"
    :current="$current"
>

<x-site.crumb :trail="[['label' => 'Home', 'url' => '/'], ['label' => 'All Events']]" />

<main id="main">
  <x-site.page-head eyebrow="Events" title="All Events" lead="Workshops, competitions, technical sessions and humanitarian activities run by the IEEE IUBAT Student Branch." />

  <section class="section">
    <div class="wrap">
      <x-site.section-head :eyebrow="$upcomingLabel[0]" :title="$upcomingLabel[1]" />

      <div class="grid grid--2" style="gap:40px;align-items:center">
        <div class="rv">
          <img src="/assets/img/poster-exhibition.jpg" data-zoom="/assets/img/poster-exhibition.jpg"
               role="button" tabindex="0" aria-label="Enlarge exhibition poster"
               alt="Poster for the IEEE BECITHCON 2026 conference"
               style="border:1px solid var(--line);border-radius:8px" width="1200" height="1200">
        </div>
        <div class="rv">
          <span class="eyebrow">4&ndash;5 September 2026 &middot; IUBAT</span>
          <h3 style="font-size:1.8rem">IEEE BECITHCON 2026</h3>
          <p>
            The 4th IEEE International Conference on Biomedical Engineering, Computer
            and Information Technology for Health, hosted by IUBAT &mdash; two days of
            keynote talks, invited talks and 31 parallel technical sessions in a
            hybrid format.
          </p>
          <ul class="ticks">
            <li>Two-day hybrid conference with IEEE Xplore publication</li>
            <li>Keynote talks from leading biomedical engineering researchers</li>
            <li>Around 100 papers across 31 technical sessions</li>
            <li>Branch co-organised Region 10 HTA exhibition &amp; training on day two</li>
          </ul>
          <div class="hero__cta">
            <a class="btn btn--primary" href="/event/becithcon-2026">View Full Programme @icon('arrow')</a>
            <a class="btn btn--ghost" href="{{ $event['page_url'] }}">Region 10 HTA Exhibition</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--shell">
    <div class="wrap">
      <x-site.section-head eyebrow="Past events" title="What we have run" lead="A record of the branch's activities since its establishment." />

      <div class="timeline rv">
        <div class="timeline__item">
          <div class="timeline__time">May 2025</div>
          <div class="timeline__body">
            <h3>IEEE IUBAT Student Branch Inauguration</h3>
            <p>The official launch of the IEEE IUBAT Student Branch with an initiation program featuring the IEEE Bangladesh Section Chair.</p>
          </div>
        </div>
        <div class="timeline__item">
          <div class="timeline__time">Dec 2025</div>
          <div class="timeline__body">
            <h3>SympSIST 2025</h3>
            <p>International Symposium on Social Implications of Sustainable Technology &mdash; the branch's flagship event, jointly organised with IEEE SSIT Bangladesh Section. Featured a humanitarian project exhibition, poster presentations, pitch competition and workshop.</p>
          </div>
        </div>
        <div class="timeline__item">
          <div class="timeline__time">Dec 2025</div>
          <div class="timeline__body">
            <h3>Promising Student Branch Award</h3>
            <p>Recognised at the IEEE Bangladesh Section awards night for outstanding early-stage performance, leadership and active engagement in professional development.</p>
          </div>
        </div>
        <div class="timeline__item">
          <div class="timeline__time">Jul 2026</div>
          <div class="timeline__body">
            <h3>Chapter Inauguration Ceremony</h3>
            <p>Inauguration of four new IEEE chapters: RAS, EMBS, CIS and WIE, joining the existing Computer Society chapter.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <x-site.cta-block
      title="Become part of the branch"
      sub="IEEE membership opens the door to the world's largest technical professional organization &mdash; and the branch is where you put it to use."
      :buttons="[
          ['How to join', '/membership', 'light'],
          ['Talk to us first', '/contact', 'outline-light'],
      ]"
  />
</main>

</x-layouts.app>
