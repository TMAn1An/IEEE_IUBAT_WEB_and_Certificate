<?php
require_once __DIR__ . '/includes/components.php';
$pageTitle = 'IEEE IUBAT Student Branch';
$pageDesc = 'IEEE IUBAT Student Branch — the official IEEE student community at IUBAT. Join workshops, exhibitions and training programs for engineering students in Bangladesh.';
$pageUrl = 'https://ieee.iubat.edu/';
$pageImage = config()['site']['origin'] . config()['site']['branch_photo'];
$pageImageW = '1200';
$pageImageH = '800';
$current = 'home';
include 'partials/head.php';
include 'partials/header.php';

$site = config()['site'];
$event = config()['event'];
$bc = config()['becithcon'];
$phase = eventPhase($event);
$registrationOpen = in_array($phase, ['early', 'regular'], true);
?>

<main id="main">
  <section class="section section--shell theme-event">
    <div class="wrap">
      <?= section_head('Happening now','IEEE BECITHCON 2026','Two days of keynote talks, technical sessions and the branch&rsquo;s Region 10 HTA exhibition &amp; training. Conference schedules are available as PDFs below.') ?>

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
              <li><?= svg('calendar') ?> <?= $bc['date_label'] ?></li>
              <li><?= svg('pin') ?> <?= $bc['venue'] ?></li>
            </ul>
            <div class="hero__cta">
              <a class="btn btn--green" href="<?= $bc['full_program_pdf'] ?>" target="_blank" rel="noopener">Full programme (PDF) <?= svg('arrow') ?></a>
              <a class="btn btn--ghost" href="<?= $bc['sessions_pdf'] ?>" target="_blank" rel="noopener">Session schedule (PDF) <?= svg('arrow') ?></a>
              <a class="btn btn--primary" href="<?= $bc['page_url'] ?>">View details <?= svg('arrow') ?></a>
            </div>
          </article>

          <article class="spotlight">
            <span class="event-banner__flag">Featured &middot; HTA Programme</span>
            <h2 class="spotlight__title"><?= $event['name'] ?></h2>
            <p class="spotlight__lead">
              The branch&rsquo;s Region 10 HTA exhibition &amp; volunteer training, run
              within BECITHCON &mdash; hands-on training, a judged humanitarian project
              exhibition and a closing keynote on the 5th.
            </p>
            <ul class="promo__meta">
              <li><?= svg('calendar') ?> <?= $event['date_label'] ?> &middot; within BECITHCON 2026</li>
              <li><?= svg('pin') ?> <?= $event['venue'] ?></li>
            </ul>
            <div class="hero__cta">
              <a class="btn btn--green" href="<?= $bc['full_program_pdf'] ?>" target="_blank" rel="noopener">Schedule (PDF) <?= svg('arrow') ?></a>
              <a class="btn btn--primary" href="<?= $event['page_url'] ?>">View details <?= svg('arrow') ?></a>
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
          <a class="btn btn--primary" href="/about">About the branch <?= svg('arrow') ?></a>
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
        <?= statCard('5','Chapters &amp; AGs') . "\n" . statCard('100','Student members','+') . "\n" . statCard('4','Events &amp; workshops') . "\n" . statCard('1','National award') ?>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <?= section_head('What we do','Four things the branch is for','Everything we run comes back to one of these.') ?>

      <div class="grid grid--4">
        <?= iconCard('heart','Humanitarian technology','Projects aligned with the UN Sustainable Development Goals, designed for communities that usually get left out of engineering.') . "\n" . iconCard('tech','Technical activities','Electronics, programming, robotics and research &mdash; hands-on work that goes beyond the syllabus.') . "\n" . iconCard('book','Skills and training','Workshops and volunteer training that turn coursework into things you can build, document and defend.') . "\n" . iconCard('network','Network and career','Access to IEEE members, researchers and industry professionals across Bangladesh and Region 10.', ['card' => 'card--green']) ?>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <?= section_head('Our chapters','Chapters &amp; affinity groups','Five officially affiliated IEEE society chapters and groups under the IEEE IUBAT Student Branch.') ?>

      <div class="orbit-stage rv">
        <div class="orbit">
          <div class="orbit__ring"></div>
          <div class="orbit__center">
            <img src="<?= $site['logo'] ?>" alt="IEEE IUBAT Student Branch" width="120" height="120">
          </div>
<?php $i = 0; foreach (config()['chapters'] as $ch): $i++; ?>
          <div class="orbit__planet orbit__planet--<?= $i ?>">
            <picture><source srcset="/assets/img/<?= $ch['img'] ?>.webp" type="image/webp"><img src="/assets/img/<?= $ch['img'] ?>.jpg" alt="IEEE <?= $ch['name'] ?>" width="64" height="64"></picture>
          </div>
          <div class="orbit__label orbit__label--<?= $i ?>"><?= $ch['abbr'] ?></div>
<?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <?= section_head('What members say','From our alumni') ?>

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
      <?= section_head('Explore','Find your way around') ?>
      <div class="grid grid--3">
        <a class="linkcard rv" href="/about"><h3>Our Student Branch <?= svg('arrow') ?></h3><p>Who we are, what we stand for, and how the branch fits into IEEE.</p></a>
        <a class="linkcard rv" href="/committee"><h3>Committees <?= svg('arrow') ?></h3><p>The people behind the branch &mdash; faculty advisors, student mentors and the volunteer team.</p></a>
        <a class="linkcard rv" href="/membership"><h3>Membership <?= svg('arrow') ?></h3><p>What IEEE student membership gets you and how to sign up.</p></a>
      </div>
    </div>
  </section>

  <?= ctaBlock('Become part of the branch','IEEE membership opens the door to the world\'s largest technical professional organization &mdash; and the branch is where you put it to use.', [
      ['How to join', '/membership', 'light'],
      ['Talk to us first', '/contact', 'outline-light'],
  ]) ?>
</main>

<?php include 'partials/footer.php'; ?>