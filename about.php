<?php
require_once __DIR__ . '/includes/components.php';
$pageTitle = 'Our Student Branch &mdash; IEEE IUBAT Student Branch';
$pageDesc = 'About the IEEE IUBAT Student Branch — our story, what we do and how we are organised. Established in 2025 at IUBAT, Dhaka.';
$pageUrl = 'https://ieee.iubat.edu/about';
$pageImage = config()['site']['origin'] . config()['site']['branch_photo'];
$pageImageW = '1200';
$pageImageH = '800';
$current = 'about';
include 'partials/head.php';
include 'partials/header.php';
?>

<?= crumb([['label' => 'Home', 'url' => '/'], ['label' => 'Our Student Branch']]) ?>

<main id="main">
  <?= pagehead('About','Our Student Branch','A technical subunit of IEEE at IUBAT, run by students, for students &mdash; with a standing commitment to technology that serves people.') ?>

  <section class="section">
    <div class="wrap">
      <div class="grid grid--2" style="gap:52px;align-items:start">
        <div class="rv">
          <span class="eyebrow">Who we are</span>
          <h2>Students first, engineers throughout</h2>
          <p class="lead">
            The IEEE IUBAT Student Branch gives students at the International University of
            Business Agriculture and Technology a route into IEEE &mdash; the world's largest
            technical professional organization &mdash; while they are still studying.
          </p>
          <p>
            Membership is where it starts, but the branch is what makes it useful. We organise
            workshops, technical sessions, competitions and volunteer programmes so that
            members leave with work they have actually built and people they actually know.
          </p>
          <p>
            We sit within the IEEE Bangladesh Section, which sits within IEEE Region 10 &mdash;
            the Asia-Pacific region and the largest of IEEE's ten regions. That structure is
            what lets a student project in Uttara end up in front of a Region 10 committee.
          </p>
        </div>

        <div class="rv">
          <div class="card" style="border-left:4px solid var(--ieee-blue)">
            <h3>At a glance</h3>
            <ul class="ticks" style="margin-bottom:0">
              <li><strong>Institution:</strong> IUBAT &mdash; International University of Business Agriculture and Technology</li>
              <li><strong>Location:</strong> Sector 10, Uttara Model Town, Dhaka 1230</li>
              <li><strong>Section:</strong> IEEE Bangladesh Section</li>
              <li><strong>Region:</strong> IEEE Region 10 (Asia-Pacific)</li>
              <li><strong>Open to:</strong> IUBAT students across engineering and allied disciplines</li>
              <li><strong>Flagship event 2026:</strong> IEEE BECITHCON 2026 &amp; the Region 10 HTA exhibition</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <?= section_head('Structure','How we\'re organised','The student branch is the parent unit. Five specialist groups operate under it, each with its own focus.') ?>

      <div class="grid grid--2" style="gap:40px;align-items:start">

        <div class="rv">
          <div class="card" style="border-top:4px solid var(--ieee-blue)">
            <h3>Main student branch</h3>
            <p>The IEEE IUBAT Student Branch is the governing body. Our Executive Committee is responsible for:</p>
            <ul class="ticks" style="margin-bottom:0">
              <li>Overall management of all branch operations and finances</li>
              <li>Submitting the Annual Activity Plan and Financial Report to IEEE by 15 March each year</li>
              <li>Maintaining an updated officer roster on IEEE vTools</li>
              <li>Organising at least four branch-level events per year</li>
              <li>Coordinating with the IEEE Bangladesh Section and Region 10</li>
              <li>Supervising and supporting all chapters and affinity groups under the branch</li>
            </ul>
          </div>
        </div>

        <div class="rv">
          <div class="card" style="border-top:4px solid var(--ieee-green)">
            <h3>Chapters &amp; affinity group</h3>
            <p>Each chapter is a specialist subunit of the main branch. They run their own technical or community programmes while the branch ExCom provides oversight. Each chapter must:</p>
            <ul class="ticks" style="margin-bottom:0">
              <li>Hold at least two events per year (technical events for chapters)</li>
              <li>Maintain at least five active IEEE Student or Graduate Student Members</li>
              <li>Elect a Chair who serves on the branch Executive Committee</li>
              <li>Keep an updated officer roster on IEEE vTools</li>
            </ul>
            <p style="margin-top:16px;margin-bottom:0"><strong>Bonus:</strong> Chapters can access additional funding, distinguished lecturers and awards directly from their parent IEEE Society.</p>
          </div>
        </div>

      </div>

      <div class="grid grid--3" style="margin-top:32px;gap:24px">
        <?= iconCard('','IEEE Computer Society Chapter','Computer science, software engineering, cybersecurity and emerging computing technologies.', ['card' => 'card--sm', 'title' => 'h4']) . "\n" . iconCard('','IEEE RAS Chapter','Robotics, automation, artificial intelligence, control systems and intelligent technologies.', ['card' => 'card--sm', 'title' => 'h4']) . "\n" . iconCard('','IEEE EMBS Chapter','Interdisciplinary work at the intersection of engineering, healthcare and biomedical science.', ['card' => 'card--sm', 'title' => 'h4']) . "\n" . iconCard('','IEEE CIS Chapter','Artificial intelligence, machine learning, neural networks and evolutionary computing.', ['card' => 'card--sm', 'title' => 'h4']) . "\n" . iconCard('','IEEE WIE Affinity Group','Inspiring, supporting and empowering women in engineering through inclusive leadership and professional development.', ['card' => 'card--sm card--green', 'title' => 'h4']) ?>
      </div>
    </div>
  </section>

  <section class="section section--shell">
    <div class="wrap">
      <?= section_head('Activities','What the branch runs','Five strands of work, all of them volunteer-led.') ?>

      <div class="grid grid--3">
        <?= iconCard('tech','Technical projects','Electronics design, programming, robotics and applied research, built in teams rather than alone.') . "\n" . iconCard('book','Workshops and seminars','Sessions led by academics and industry practitioners on the tools and methods our members actually need.') . "\n" . iconCard('trophy','Competitions','Exhibitions and contests judged by expert panels, with certificates and prizes that mean something on a CV.') . "\n" . iconCard('heart','Humanitarian activities','Work under IEEE HTA and SIGHT aimed at health, access and resilience in underserved communities.', ['card' => 'card--green']) . "\n" . iconCard('network','Networking','Introductions to IEEE volunteers, researchers and alumni across Bangladesh and the wider region.') . "\n" . iconCard('globe','Volunteer development','Training in project design, documentation and leadership &mdash; the parts of engineering nobody teaches you.') ?>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <div class="grid grid--2" style="gap:52px;align-items:center">
        <div class="rv">
          <span class="eyebrow">Our focus</span>
          <h2>Technology for the benefit of humanity</h2>
          <p>
            That phrase is IEEE's, and we take it literally. Many communities across Bangladesh
            still face limited access to healthcare services, diagnostic facilities and basic
            medical awareness. Engineering can close part of that gap, but only if it is cheap
            enough to deploy and simple enough to keep running.
          </p>
          <p>
            That is the brief we set our members: practical, low-cost, community-oriented work
            aligned with the UN Sustainable Development Goals, and with SDG&nbsp;3 &mdash; good health
            and well-being &mdash; in particular.
          </p>
          <a class="btn btn--primary" href="/event/hta-2026">See this year's HTA exhibition <?= svg('arrow') ?></a>
        </div>
        <div class="rv">
          <img src="/assets/img/poster-workshop.jpg" data-zoom="/assets/img/poster-workshop.jpg"
               role="button" tabindex="0" aria-label="Enlarge volunteer training poster"
               alt="Poster for the volunteer training program, listing the workshop sessions"
               style="border:1px solid var(--line);border-radius:8px" loading="lazy" width="1200" height="1200">
        </div>
      </div>
    </div>
  </section>


  <?= ctaBlock('Become part of the branch','IEEE membership opens the door to the world\'s largest technical professional organization &mdash; and the branch is where you put it to use.', [
      ['How to join', '/membership', 'light'],
      ['Talk to us first', '/contact', 'outline-light'],
  ]) ?>
</main>

<?php include 'partials/footer.php'; ?>