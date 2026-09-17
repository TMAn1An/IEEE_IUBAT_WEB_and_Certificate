@php $bc = $becithcon; @endphp
<x-layouts.app
    :page-title="$pageTitle"
    :page-desc="$pageDesc"
    :page-url="$pageUrl"
    :page-image="$pageImage"
    :page-image-w="$pageImageW"
    :page-image-h="$pageImageH"
    :current="$current"
>

<main id="main">
  <section class="section section--shell theme-event">
    <div class="wrap">
      <x-site.section-head eyebrow="Happening now" title="IEEE BECITHCON 2026" lead="Two days of keynote talks, technical sessions and the branch&rsquo;s Region 10 HTA exhibition &amp; training. Conference schedules are available as PDFs below." />

      <div class="event-spot rv">
        <div class="event-spot__deck event-banner__poster">
          <div class="deck" aria-label="Event posters">
            <div class="deck__item" data-zoom="/assets/img/poster-keynote.jpg" role="button" tabindex="0" aria-label="Enlarge keynote poster">
              <img src="/assets/img/poster-keynote.jpg" alt="Poster: Keynote talk and panel discussion" loading="lazy" width="1200" height="1200">
            </div>
            <div class="deck__item" data-zoom="/assets/img/poster-workshop.jpg" role="button" tabindex="0" aria-label="Enlarge workshop poster">
              <img src="/assets/img/poster-workshop.jpg" alt="Poster: Volunteer training program" loading="lazy" width="1200" height="1200">
            </div>
            <div class="deck__item" data-zoom="/assets/img/poster-exhibition.jpg" role="button" tabindex="0" aria-label="Enlarge exhibition poster">
              <img src="/assets/img/poster-exhibition.jpg" alt="Poster: Humanitarian project exhibition" loading="lazy" width="1200" height="1200">
            </div>
          </div>
        </div>
        <div class="event-spot__cards">
          <article class="spotlight">
            <span class="event-banner__flag">Featured &middot; Conference</span>
            <h2 class="spotlight__title">IEEE BECITHCON 2026</h2>
            <p class="spotlight__lead">
              The 4th IEEE International Conference on Biomedical Engineering, Computer
              and Information Technology for Health &mdash; two days of parallel technical
              sessions, keynotes and invited talks across a hybrid format.
            </p>
            <ul class="promo__meta">
              <li>@icon('calendar') {!! $bc['date_label'] !!}</li>
              <li>@icon('pin') {{ $bc['venue'] }}</li>
            </ul>
            <div class="hero__cta">
              <a class="btn btn--green" href="{{ $bc['full_program_pdf'] }}" target="_blank" rel="noopener">Full programme (PDF) @icon('arrow')</a>
              <a class="btn btn--ghost" href="{{ $bc['sessions_pdf'] }}" target="_blank" rel="noopener">Session schedule (PDF) @icon('arrow')</a>
              <a class="btn btn--primary" href="{{ $bc['page_url'] }}">View details @icon('arrow')</a>
            </div>
          </article>

          <article class="spotlight">
            <span class="event-banner__flag">Featured &middot; HTA Programme</span>
            <h2 class="spotlight__title">{{ $event['name'] }}</h2>
            <p class="spotlight__lead">
              The branch&rsquo;s Region 10 HTA exhibition &amp; volunteer training, run
              within BECITHCON &mdash; hands-on training, a judged humanitarian project
              exhibition and a closing keynote on the 5th.
            </p>
            <ul class="promo__meta">
              <li>@icon('calendar') {{ $event['date_label'] }} &middot; within BECITHCON 2026</li>
              <li>@icon('pin') {{ $event['venue'] }}</li>
            </ul>
            <div class="hero__cta">
              <a class="btn btn--green" href="{{ $bc['full_program_pdf'] }}" target="_blank" rel="noopener">Schedule (PDF) @icon('arrow')</a>
              <a class="btn btn--primary" href="{{ $event['page_url'] }}">View details @icon('arrow')</a>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section class="hero">
    <div class="wrap hero__grid">
      <div>
        <span class="eyebrow">IEEE Bangladesh Section &middot; Region 10</span>
        <h1>Engineering that reaches<span>the people it is for</span></h1>
        <p class="hero__lead">
          The IEEE IUBAT Student Branch is the student chapter of IEEE at the International
          University of Business Agriculture and Technology in Uttara, Dhaka. We connect
          students to the global IEEE community through technical activities, training,
          competitions and volunteering.
        </p>
        <div class="hero__cta">
          <a class="btn btn--primary" href="/about">About the branch @icon('arrow')</a>
          <a class="btn btn--ghost" href="/events">See what we run</a>
        </div>
      </div>
      <div class="hero__media rv">
        <picture>
          <source srcset="/assets/img/ieee-iubat-sb.webp" type="image/webp">
          <img src="/assets/img/ieee-iubat-sb.jpg" alt="IEEE IUBAT Student Branch members at a technical activity" width="600" height="400">
        </picture>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <div class="stats rv">
        <x-site.stat-card count="5" label="Chapters &amp; AGs" />
        <x-site.stat-card count="100" label="Student members" suffix="+" />
        <x-site.stat-card count="4" label="Events &amp; workshops" />
        <x-site.stat-card count="1" label="National award" />
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <x-site.section-head eyebrow="What we do" title="Four things the branch is for" lead="Everything we run comes back to one of these." />

      <div class="grid grid--4">
        <x-site.icon-card icon="heart" title="Humanitarian technology" body="Projects aligned with the UN Sustainable Development Goals, designed for communities that usually get left out of engineering." />
        <x-site.icon-card icon="tech" title="Technical activities" body="Electronics, programming, robotics and research &mdash; hands-on work that goes beyond the syllabus." />
        <x-site.icon-card icon="book" title="Skills and training" body="Workshops and volunteer training that turn coursework into things you can build, document and defend." />
        <x-site.icon-card icon="network" title="Network and career" body="Access to IEEE members, researchers and industry professionals across Bangladesh and Region 10." card="card--green" />
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <x-site.section-head eyebrow="Our chapters" title="Chapters &amp; affinity groups" lead="Five officially affiliated IEEE society chapters and groups under the IEEE IUBAT Student Branch." />

      <div class="orbit-stage rv">
        <div class="orbit">
          <div class="orbit__ring"></div>
          <div class="orbit__center">
            <img src="{{ config('site.site.logo') }}" alt="IEEE IUBAT Student Branch" width="120" height="120">
          </div>
@php $i = 0; @endphp
@foreach (config('site.chapters') as $ch)
@php $i++; @endphp
          <div class="orbit__planet orbit__planet--{{ $i }}">
            <picture><source srcset="/assets/img/{{ $ch['img'] }}.webp" type="image/webp"><img src="/assets/img/{{ $ch['img'] }}.jpg" alt="IEEE {{ $ch['name'] }}" width="64" height="64"></picture>
          </div>
          <div class="orbit__label orbit__label--{{ $i }}">{{ $ch['abbr'] }}</div>
@endforeach
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <x-site.section-head eyebrow="What members say" title="From our alumni" />

      <div class="testimonials">
        <div class="testimonial rv">
          <div class="testimonial__av">
            <img src="/assets/img/members/azim-sharkar.jpg" alt="Photo of Azim Sharkar, Student Advisor" width="80" height="80" loading="lazy">
          </div>
          <blockquote class="testimonial__quote">&ldquo;We built SympSIST from nothing &mdash; a full international symposium with exhibitions, competitions and workshops &mdash; and walked away with the Promising Student Branch Award. That experience taught me more than any classroom could.&rdquo;</blockquote>
          <div class="testimonial__info">
            <span class="testimonial__name">Azim Sharkar</span>
            <span class="testimonial__role">Founding Chair &middot; Student Advisor</span>
          </div>
        </div>
        <div class="testimonial rv">
          <div class="testimonial__av">
            <img src="/assets/img/members/abu-huraira-sumon.jpg" alt="Photo of Abu Huraira Sumon, Student Mentor" width="80" height="80" loading="lazy">
          </div>
          <blockquote class="testimonial__quote">&ldquo;Starting the branch and organising our first flagship event taught me how to turn ideas into something real. The Promising Student Branch Award proved that a small team with purpose can achieve something the whole Section notices.&rdquo;</blockquote>
          <div class="testimonial__info">
            <span class="testimonial__name">Abu Huraira Sumon</span>
            <span class="testimonial__role">Founding Secretary &middot; Student Mentor</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--shell">
    <div class="wrap">
      <x-site.section-head eyebrow="Explore" title="Find your way around" />
      <div class="grid grid--3">
        <a class="linkcard rv" href="/about"><h3>Our Student Branch @icon('arrow')</h3><p>Who we are, what we stand for, and how the branch fits into IEEE.</p></a>
        <a class="linkcard rv" href="/committee"><h3>Committees @icon('arrow')</h3><p>The people behind the branch &mdash; faculty advisors, student mentors and the volunteer team.</p></a>
        <a class="linkcard rv" href="/membership"><h3>Membership @icon('arrow')</h3><p>What IEEE student membership gets you and how to sign up.</p></a>
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
