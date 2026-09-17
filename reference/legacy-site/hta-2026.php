<?php
require_once __DIR__ . '/includes/components.php';
$pageTitle = 'IEEE Region 10 HTA Exhibition &amp; Volunteer Training Program &mdash; BECITHCON 2026 &middot; IEEE IUBAT Student Branch';
$pageDesc = 'IEEE Region 10 Humanitarian Technologies Activities (HTA) Exhibition & Volunteer Training Program 2026 — co-organised with IEEE BECITHCON 2026. Schedule, prizes, speakers and fees.';
$pageUrl = 'https://ieee.iubat.edu/event/hta-2026';
$current = 'events';
$bodyClass = 'theme-event';
$voxelQr = true;
$pageJsonLd = eventJsonLd();
$pageScripts = <<<'SCRIPTS'
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js" integrity="sha384-CI3ELBVUz9XQO+97x6nwMDPosPR5XvsxW2ua7N1Xeygeh1IxtgqtCkGfQY9WWdHu" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.0.3/qrcode.min.js" integrity="sha384-U1R6Pw+ZRz1BHheYIgXTC9z9BTBa+Ml3zcLyYB0whlTfF/yJu56l8+xTwLClWtmC" crossorigin="anonymous"></script>
<script>if(typeof THREE==="undefined"){var v=document.querySelector(".vq");if(v)v.innerHTML='<a href="'+(window.IEEE_EVENT&&window.IEEE_EVENT.formUrl||"https://forms.gle/AkKmzLBX8t84c4GE8")+'" target="_blank" rel="noopener" style="display:inline-block;padding:12px 20px;border:1px solid #ccc;border-radius:8px;color:#00629b;font-weight:600">Open registration form</a>';}</script>
<script src="/assets/js/voxel-qr.js"></script>
SCRIPTS;
include 'partials/head.php';
include 'partials/header.php';

$site = config()['site'];
$event = config()['event'];

/* Phase-aware hero: eventPhase() (includes/components.php) is the single
   source of truth so this block, the [data-clock] box's JS-rendered content,
   the header alert banner and the fees/registration section below all agree
   with no manual edit needed through early-bird, regular, closed, the event
   days themselves, and afterwards. The clock/eyebrow/date default text is
   set correctly server-side too (not just left for JS to fix on load), so a
   visitor without JS still sees accurate information. */
$phase = eventPhase($event);
$clockFallback = ''; // shown until JS's countdown() takes over; kept in sync with its messages{} map
switch ($phase) {
    case 'early':
        $clockEyebrow = 'Early-bird registration closes';
        $clockDate = fmtDay($event['early_bird']) . ' 2026';
        $heroPrimary = ['Register your team', $event['register_url'], true];
        $heroSecondary = ['Jump to fees', '#fees'];
        break;
    case 'regular':
        $clockEyebrow = 'Regular registration closes';
        $clockDate = fmtDay($event['regular_deadline']) . ' 2026';
        $heroPrimary = ['Register your team', $event['register_url'], true];
        $heroSecondary = ['Jump to fees', '#fees'];
        break;
    case 'closed':
        $clockEyebrow = 'Registration closed';
        $clockDate = '';
        $clockFallback = '<p class="clock--over">Registration for this event is now closed.</p>';
        $heroPrimary = ['View schedule', '#schedule', false];
        $heroSecondary = ['Talk to the organising team', '#event-contact'];
        break;
    case 'live':
        $clockEyebrow = 'Happening now';
        $clockDate = fmtDay($event['date']) . ' 2026';
        $clockFallback = '<p class="clock--over">The event is happening now &mdash; see the schedule below.</p>';
        $heroPrimary = ['View schedule', '#schedule', false];
        $heroSecondary = ['Talk to the organising team', '#event-contact'];
        break;
    default: // ended
        $clockEyebrow = 'Event concluded';
        $clockDate = fmtDay($event['date']) . ' 2026';
        $clockFallback = '<p class="clock--over">This event has concluded. Thank you to everyone who took part.</p>';
        $heroPrimary = ['See other events', '/events', false];
        $heroSecondary = ['Talk to the organising team', '#event-contact', false];
        break;
}
$registrationOpen = in_array($phase, ['early', 'regular'], true);
?>

<nav class="subnav" aria-label="Event sections">
  <div class="wrap subnav__in">
    <span class="subnav__title">Region 10 HTA Exhibition 2026</span>
    <ul class="subnav__links"><li><a href="#overview" class="is-active">Overview</a></li><li><a href="#segments">Segments</a></li><li><a href="#prizes">Prizes</a></li><li><a href="#fees">Registration</a></li><li><a href="#schedule">Schedule</a></li><li><a href="#event-contact">Contact</a></li></ul>
  </div>
</nav>

<?= crumb([['label' => 'Home', 'url' => '/'], ['label' => 'Events', 'url' => '/events'], ['label' => 'BECITHCON 2026', 'url' => '/event/becithcon-2026'], ['label' => 'Region 10 HTA Exhibition 2026']]) ?>

<main id="main">
  <?php
  $heroCta = '      <div class="hero__cta" style="margin-top:24px">' . "\n";
  [$primLabel, $primUrl, $primExternal] = $heroPrimary;
  $heroCta .= '        <a class="btn btn--green" href="' . $primUrl . '"' . ($primExternal ? ' target="_blank" rel="noopener"' : '') . '>' . $primLabel . ' ' . svg('arrow') . '</a>' . "\n";
  [$secLabel, $secUrl] = $heroSecondary;
  $heroCta .= '        <a class="btn btn--outline-light" href="' . $secUrl . '">' . $secLabel . '</a>' . "\n";
  $heroCta .= '      </div>';
  ?>
  <?= pagehead('<a href="/event/becithcon-2026" style="color:inherit;border-bottom:1px solid currentColor">IEEE BECITHCON 2026</a> &middot; IEEE Region 10 HTA','Humanitarian Technology Exhibition &amp; Volunteer Training','A day of hands-on training, a judged exhibition and an awards ceremony within <a href="/event/becithcon-2026" style="color:inherit">IEEE BECITHCON 2026</a> &mdash; built around low-cost medical and healthcare technology for underserved communities.',
      '<div class="pagehead__stats stats rv">' . "\n" .
      '        ' . statCard('3','Event segments') . "\n" .
      '        ' . statCard('5','Expert speakers') . "\n" .
      '        ' . statCard('10','Focus areas') . "\n" .
      '        ' . statCard('10000','BDT prize pool') . "\n" .
      '      </div>' . "\n" .
      '      <div class="pagehead__timer">' . "\n" .
      '        <div>' . "\n" .
      '          <span class="eyebrow" data-clock-title>' . $clockEyebrow . '</span>' . "\n" .
      '          <h3 style="margin:0" data-clock-date>' . $clockDate . '</h3>' . "\n" .
      '        </div>' . "\n" .
      '        <div class="clock" data-clock>' . $clockFallback . '</div>' . "\n" .
      '      </div>' . "\n" .
      $heroCta) ?>

  <section class="section section--shell" id="posters">
    <div class="wrap">
      <?= section_head('Promotion','Event posters','Click any poster to view it full size and share it with your team.') ?>

      <div class="poster-stage">
        <div class="poster-carousel">
          <figure class="poster-card poster-card--left" data-zoom="/assets/img/poster-keynote.jpg" role="button" tabindex="0" aria-label="Enlarge keynote poster">
            <img src="/assets/img/poster-keynote.jpg" alt="Poster for the keynote talk and panel discussion segment" loading="lazy" width="1200" height="1200">
            <figcaption class="poster-card__cap">Keynote talk &amp; panel discussion</figcaption>
          </figure>
          <figure class="poster-card poster-card--center" data-zoom="/assets/img/poster-exhibition.jpg" role="button" tabindex="0" aria-label="Enlarge exhibition poster">
            <img src="/assets/img/poster-exhibition.jpg" alt="Poster for the humanitarian project exhibition segment" loading="lazy" width="1200" height="1200">
            <figcaption class="poster-card__cap">Humanitarian project exhibition</figcaption>
          </figure>
          <figure class="poster-card poster-card--right" data-zoom="/assets/img/poster-workshop.jpg" role="button" tabindex="0" aria-label="Enlarge workshop poster">
            <img src="/assets/img/poster-workshop.jpg" alt="Poster for the volunteer training program workshop segment" loading="lazy" width="1200" height="1200">
            <figcaption class="poster-card__cap">Volunteer training program</figcaption>
          </figure>
        </div>
        <p class="poster-stage__hint rv"><?= svg('zoom', 'style="width:18px;height:18px;vertical-align:middle;margin-right:4px"') ?> Click any poster to zoom in</p>
      </div>

    </div>
  </section>

  <section class="section" id="overview">
    <div class="wrap">
      <div class="grid grid--2" style="gap:52px;align-items:start">
        <div class="rv">
          <span class="eyebrow">Why this event exists</span>
          <h2>Healthcare technology that reaches people</h2>
          <p class="lead">
            Many communities across Bangladesh still face limited access to healthcare services,
            diagnostic facilities and medical awareness. This IEEE Region 10 Humanitarian
            Technologies Activities project asks students to close that gap with practical,
            low-cost, community-oriented engineering.
          </p>
          <p>
            The work sits under the UN Sustainable Development Goals, and SDG&nbsp;3 &mdash; good health
            and well-being &mdash; in particular. Projects are judged on whether they could genuinely
            improve health outcomes, widen access to care, or address a public health problem in
            an underserved setting.
          </p>
        </div>

        <div class="rv">
          <div class="card" style="border-left:4px solid var(--ieee-blue)">
            <h3>At a glance</h3>
            <ul class="ticks" style="margin-bottom:0">
              <li><strong>Date:</strong> <?= $event['date_label'] ?> &mdash; day two of <a href="/event/becithcon-2026">BECITHCON 2026</a></li>
              <li><strong>Venue:</strong> IUBAT, 11th Floor Open Space Area &amp; Room 324, 4 Embankment Drive Road, Sector 10, Uttara Model Town, Dhaka 1230</li>
              <li><strong>Jointly organised by:</strong> IEEE Region 10 &middot; IEEE Bangladesh Section &middot; IEEE EMBS Bangladesh Chapter</li>
              <li><strong>Co-organised by:</strong> IEEE IUBAT Student Branch</li>
              <li><strong>Conference:</strong> Held within <a href="/event/becithcon-2026">IEEE BECITHCON 2026</a></li>
              <li><strong>Team size:</strong> 1 to 5 members</li>
              <li><strong>Focus:</strong> Medical and healthcare humanitarian technologies</li>
            </ul>
          </div>
        </div>
      </div>

      <div style="margin-top:48px">
        <?= section_head('Who it\'s for','Why take part','Open to medical and engineering students with an interest in healthcare innovation and humanitarian work &mdash; whether you have a medical device prototype, a digital health solution or an assistive technology project.') ?>

        <div class="grid grid--3">
          <?= iconCard('monitor','Show your work','Present to academics, industry experts and humanitarian professionals, and get judged feedback you can act on.') . "\n" . iconCard('network','Share ideas','Trade approaches with students and researchers from universities across Bangladesh working on similar problems.') . "\n" . iconCard('book','Learn from experts','Technical workshops and industry-focused sessions delivered by distinguished speakers from IEEE and BUET.') . "\n" . iconCard('trophy','Win prizes','Prize money, crests and certificates for the champion and both runners-up, awarded at the closing ceremony.', ['card' => 'card--green']) . "\n" . iconCard('ticket','Get certified','Every participant receives an official certificate recognising their participation.') . "\n" . iconCard('globe','Build your network','Meet IEEE leaders, humanitarian professionals, researchers and students from across Region 10.') ?>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--shell" id="segments">
    <div class="wrap">
      <?= section_head('Programme','Three segments, in order','The workshop teaches the method, the exhibition puts your work in front of judges, and a closing keynote wraps things up.') ?>

      <ol class="rail">
        <li class="seg rv">
          <span class="seg__no">1</span>
          <div class="seg__kicker">Segment one</div>
          <h3>Volunteer training program</h3>

          <div class="talk talk--green">
            <div class="talk__top"><span class="talk__no">Session I</span></div>
            <h4>Humanitarian project design</h4>
            <p>The key aspects of designing a humanitarian project that is both innovative and sustainable &mdash; from problem framing to a solution a community can keep running.</p>
            <p class="talk__by"><?= svg('user') ?> <strong>Dr. Shaikh Anowarul Fattah</strong> &mdash; Professor, Dept. of EEE, BUET</p>
          </div>

          <div class="talk talk--green">
            <div class="talk__top"><span class="talk__no">Session II</span></div>
            <h4>An AI-driven humanitarian project for underserved communities</h4>
            <p>Using AI, low-cost digital tools and community training to improve education, agriculture, healthcare awareness, disaster resilience and digital literacy.</p>
            <p class="talk__by"><?= svg('user') ?> <strong>Dr. Celia Shahnaz</strong> &mdash; Professor, Dept. of EEE, BUET</p>
          </div>
        </li>

        <li class="seg rv">
          <span class="seg__no">2</span>
          <div class="seg__kicker">Segment two</div>
          <h3>Humanitarian project exhibition</h3>
          <p style="max-width:720px">
            For teams exhibiting a working prototype, model or project that tackles a medical or
            healthcare challenge in an underserved community. A panel of judges evaluates every
            project; certificates go to all participants and prizes to the top three teams.
          </p>

          <div class="grid grid--3" style="gap:16px;margin-top:22px">
            <div class="card" style="padding:20px"><h4>Presentation mode</h4><p style="margin:0">Oral presentation, plus a live demonstration where the project allows it.</p></div>
            <div class="card" style="padding:20px"><h4>Team size</h4><p style="margin:0">Minimum 1 member, maximum 5 members per team.</p></div>
            <div class="card" style="padding:20px"><h4>On the day</h4><p style="margin:0">Bring a fully functional prototype. Winners are announced at the closing ceremony.</p></div>
          </div>

          <div style="margin-top:28px">
            <h4 style="margin-bottom:12px">Focus areas</h4>
            <ul class="tags rv">
              <li>Low-cost diagnostic devices for rural areas</li>
              <li>Wearable health monitoring systems</li>
              <li>Telemedicine and mobile health (mHealth)</li>
              <li>Assistive technologies for persons with disabilities</li>
              <li>Biomedical devices for maternal and child health</li>
              <li>Point-of-care testing and screening tools</li>
              <li>Health data management and community informatics</li>
              <li>Emergency and disaster medical response</li>
              <li>Rehabilitation and physiotherapy devices</li>
              <li>Mental health support tools and platforms</li>
            </ul>
          </div>
        </li>

        <li class="seg rv">
          <span class="seg__no">3</span>
          <div class="seg__kicker">Segment three</div>
          <h3>Keynote &amp; invited talks</h3>

          <div class="talk">
            <div class="talk__top"><span class="talk__no">Keynote I</span></div>
            <h4>Introduction to Humanitarian Technologies</h4>
            <p>How the IEEE Humanitarian Technology Board inspires and equips volunteers worldwide to run humanitarian technology activities at a local level.</p>
            <p class="talk__by"><?= svg('user') ?> <strong>Grayson Randall</strong> &mdash; Chair, IEEE Humanitarian Technology Board</p>
          </div>

          <div class="talk">
            <div class="talk__top"><span class="talk__no">Keynote II</span></div>
            <h4>Empowering IEEE Volunteers</h4>
            <p>Operations of 2026 IEEE SIGHT &mdash; using technology for sustainable development in partnership with underserved communities and local organisations.</p>
            <p class="talk__by"><?= svg('user') ?> <strong>Amira Ouerfell</strong> &mdash; 2026 IEEE SIGHT Chair</p>
          </div>

          <div class="talk">
            <div class="talk__top"><span class="talk__no">Keynote III</span></div>
            <h4>Impact of humanitarian technologies on local communities</h4>
            <p>Designing scalable solutions aligned with the UN SDGs by strengthening grassroots capacity and building a supportive ecosystem around it.</p>
            <p class="talk__by"><?= svg('user') ?> <strong>Saurabh Jagdish Soni</strong> &mdash; 2026 IEEE R10 HTA Chair</p>
          </div>
        </li>
      </ol>
    </div>
  </section>

  <section class="section" id="prizes">
    <div class="wrap">
      <?= section_head('Awards','Prizes for the top three teams','Every winning team receives prize money, a crest and a certificate. All participants receive a certificate of participation.') ?>

      <div class="podium rv">
        <div class="step step--2">
          <div class="step__stars">
            <?= svg('star') . "\n            " . svg('star') ?>
          </div>
          <div class="step__medal">2</div>
          <div class="step__rank">1st Runner-up</div>
          <div class="step__amt">3,000 <span>BDT</span></div>
          <p class="step__note">Prize money, crest &amp; certificate</p>
        </div>
        <div class="step step--1">
          <div class="step__stars">
            <?= svg('star') . "\n            " . svg('star') . "\n            " . svg('star') ?>
          </div>
          <div class="step__trophy">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
          </div>
          <div class="step__medal">1</div>
          <div class="step__rank">Champion</div>
          <div class="step__amt">5,000 <span>BDT</span></div>
          <p class="step__note">Prize money, crest &amp; certificate</p>
        </div>
        <div class="step step--3">
          <div class="step__stars">
            <?= svg('star') ?>
          </div>
          <div class="step__medal">3</div>
          <div class="step__rank">2nd Runner-up</div>
          <div class="step__amt">2,000 <span>BDT</span></div>
          <p class="step__note">Prize money, crest &amp; certificate</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section" id="fees">
    <div class="wrap">
      <?php
      $feesSubtitle = $registrationOpen
          ? 'Fees are per participant. Early-bird pricing runs until ' . fmtDay($event['early_bird']) .
            ' 2026; regular pricing applies from ' . fmtDay($event['regular_start']) . ' to ' . fmtDay($event['regular_deadline']) . ' 2026.'
          : 'Fees are per participant. Shown here for reference &mdash; registration for this event has closed.';
      echo section_head('Registration', 'Fees by category', $feesSubtitle);
      ?>

      <div class="tablewrap rv">
        <div class="tablescroll">
          <table>
            <caption class="sr-only">Registration fees by participant category</caption>
            <thead>
              <tr>
                <th scope="col">Participant category</th>
                <th scope="col">Early bird &mdash; up to <?= fmtDayShort($event['early_bird']) ?></th>
                <th scope="col">Regular &mdash; <?= fmtDayShort($event['regular_start']) ?> to <?= fmtDayShort($event['regular_deadline']) ?></th>
                <th scope="col">Eligibility</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($event['fees'] as $f): ?>
              <tr><td class="cat"><?= $f['cat'] ?></td><td><span class="fee fee--early"><?= $f['early'] ?></span> BDT</td><td><span class="fee"><?= $f['regular'] ?></span> BDT</td><td><?= $f['eligibility'] ?></td></tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="note">Fees shown are per individual and are final. Teams may have up to 5 members; each member registers separately.</p>
      </div>

<?php if ($registrationOpen): ?>
      <h3 style="margin-top:40px">How to register</h3>

      <div class="grid grid--3">
        <div class="pay rv">
          <span class="eyebrow">Step one</span>
          <h3>Pay with bKash</h3>
          <p>Use <strong>Send Money</strong> to the number below, then keep the transaction ID &mdash; you will need it on the form.</p>
          <span class="bkash">bKash &middot; Send Money</span>
          <span class="pay__num"><?= $site['phone'] ?></span>
          <button class="copy" type="button" data-copy="<?= $site['phone_dial'] ?>">Copy number</button>
        </div>

        <div class="pay rv">
          <span class="eyebrow">Step two</span>
          <h3>Submit the form</h3>
          <ul class="ticks" style="margin-bottom:20px">
            <li>Enter your bKash transaction ID on the registration form</li>
            <li>Bring a fully functional prototype on the event day</li>
            <li>Each project is evaluated by a panel of expert judges</li>
            <li>Winners are awarded during the closing ceremony</li>
          </ul>
          <a class="btn btn--green" href="<?= $event['register_url'] ?>" target="_blank" rel="noopener">Open the registration form</a>
        </div>

        <div class="pay pay--qr rv" style="text-align:center">
          <p style="margin:0 0 16px;font-size:.9rem;color:var(--muted)">Or scan the QR to open the form on your phone</p>
          <div class="pay--qr__code">
            <div class="vq"
                 data-voxel-qr
                 data-url="<?= $event['register_url'] ?>"
                 data-delay="1800"
                 data-fall="1500"></div>
            <noscript><a href="<?= $event['register_url'] ?>" target="_blank" rel="noopener" style="display:inline-block;padding:12px 20px;border:1px solid var(--line);border-radius:8px;color:var(--ieee-blue);font-weight:600">Open registration form</a></noscript>
          </div>
          <h3 style="margin:20px 0 0;font-size:1.6rem">Scan Me!</h3>
        </div>
      </div>

      <?= callout('info','Registration deadline','<p style="margin-bottom:0">Early-bird registration closes on <strong>' . fmtDay($event['early_bird']) . ' 2026</strong>. Regular registration runs from ' . fmtDay($event['regular_start']) . ' to ' . fmtDay($event['regular_deadline']) . ' 2026. Register early &mdash; exhibition places are limited by venue capacity.</p>') ?>
<?php elseif ($phase === 'closed'): ?>
      <?= callout('info','Registration closed','<p style="margin-bottom:0">Registration for this event is now closed. See the <a href="#schedule">schedule</a> below, or <a href="#event-contact">talk to the organising team</a> with any questions.</p>') ?>
<?php elseif ($phase === 'live'): ?>
      <?= callout('info','Happening now','<p style="margin-bottom:0">This HTA programme runs on day two of <a href="/event/becithcon-2026">BECITHCON 2026</a> at IUBAT. See the <a href="#schedule">schedule</a> below or the <a href="/event/becithcon-2026">full conference programme</a>.</p>') ?>
<?php else: ?>
      <?= callout('info','Event concluded','<p style="margin-bottom:0">This event has concluded &mdash; thank you to everyone who took part. See <a href="/events">upcoming events</a> from the branch.</p>') ?>
<?php endif; ?>
    </div>
  </section>

  <section class="section" id="schedule">
    <div class="wrap">
      <?= section_head('Timeline', 'Schedule for the day', '5 September 2026 &mdash; the branch\'s R10-HTA training, exhibition and awards on day two of <a href="/event/becithcon-2026">BECITHCON 2026</a>.') ?>

      <ol class="timeline">
        <li class="timeline__item rv">
          <span class="timeline__time">9:30 &ndash; 10:30 AM</span>
          <div class="timeline__body">
            <h3>HTA training session</h3>
            <p>Hands-on training &mdash; <strong>Room 324</strong> &mdash; equipping participants to design and deliver humanitarian technology.</p>
          </div>
        </li>
        <li class="timeline__item rv">
          <span class="timeline__time">10:45 &ndash; 11:45 AM</span>
          <div class="timeline__body">
            <h3>HTA training session (continued)</h3>
            <p>Second training block &mdash; <strong>Room 324</strong>.</p>
          </div>
        </li>
        <li class="timeline__item rv">
          <span class="timeline__time">10:00 AM &ndash; 12:15 PM</span>
          <div class="timeline__body">
            <h3>Interactive workshops &amp; project exhibition</h3>
            <p>Project showcasing and the technical expert forum on the <strong>11th Floor Open Space Area</strong> &mdash; open to all attendees.</p>
          </div>
        </li>
        <li class="timeline__item rv">
          <span class="timeline__time">12:45 &ndash; 2:00 PM</span>
          <div class="timeline__body">
            <h3>Lunch and prayer break</h3>
          </div>
        </li>
        <li class="timeline__item rv">
          <span class="timeline__time">2:00 &ndash; 2:30 PM</span>
          <div class="timeline__body">
            <h3>HTA talks</h3>
            <p>Invited speaker session for participants and exhibitors.</p>
          </div>
        </li>
        <li class="timeline__item rv">
          <span class="timeline__time">4:00 &ndash; 6:00 PM</span>
          <div class="timeline__body">
            <h3>HTA project presentation</h3>
            <p>Teams present their prototypes to the judging panel on the <strong>11th Floor Open Space Area</strong>. Open showcase for all attendees.</p>
          </div>
        </li>
        <li class="timeline__item rv">
          <span class="timeline__time">5:30 &ndash; 6:00 PM</span>
          <div class="timeline__body">
            <h3>HTA award &amp; closing</h3>
            <p>Winner announcements and prize distribution, ahead of the conference-wide closing ceremony.</p>
          </div>
        </li>
        <li class="timeline__item rv">
          <span class="timeline__time">7:00 &ndash; 9:30 PM</span>
          <div class="timeline__body">
            <h3>Award ceremony, cultural gala &amp; dinner</h3>
            <p>BECITHCON 2026 closing ceremony and networking dinner &mdash; see the <a href="/event/becithcon-2026">full BECITHCON programme</a>.</p>
          </div>
        </li>
      </ol>

    </div>
  </section>

  <section class="section" id="event-contact">
    <div class="wrap">
      <?= section_head('Questions','Talk to the organising team','Reach out any time with a question about the event.') ?>

      <div class="grid grid--4">
        <?= personByKey('chair') . "\n" . personByKey('vc-technical') . "\n" . personByKey('secretary') . "\n" . personByKey('webmaster') ?>
      </div>
    </div>
  </section>


  <?php
  switch ($phase) {
      case 'early':
      case 'regular':
          $ctaTitle = 'Bring your prototype to Uttara';
          $ctaBody = 'Open to medical and engineering students across Bangladesh. Register your team, complete payment, and turn up on 5 September with something that works.';
          $ctaButtons = [
              ['Open the registration form', $event['register_url'], 'light', true],
              ['Review the fees', $event['page_url'] . '#fees', 'outline-light'],
          ];
          break;
      case 'closed':
          $ctaTitle = 'See you on 5 September';
          $ctaBody = 'Registration has closed, but the HTA exhibition &amp; training runs on 5 September within BECITHCON 2026 at IUBAT, Uttara.';
          $ctaButtons = [
              ['View the schedule', $event['page_url'] . '#schedule', 'light'],
              ['Talk to the organising team', $event['page_url'] . '#event-contact', 'outline-light'],
          ];
          break;
      case 'live':
          $ctaTitle = 'Happening now within BECITHCON 2026';
          $ctaBody = 'The Region 10 HTA exhibition &amp; volunteer training is underway on day two of BECITHCON 2026. See what\'s on right now.';
          $ctaButtons = [
              ['View the schedule', $event['page_url'] . '#schedule', 'light'],
              ['See the full BECITHCON programme', '/event/becithcon-2026', 'outline-light'],
          ];
          break;
      default: // ended
          $ctaTitle = 'Thank you for taking part';
          $ctaBody = 'This programme has concluded. Keep an eye on the branch for what\'s next.';
          $ctaButtons = [
              ['See other events', '/events', 'light'],
              ['Talk to the organising team', $event['page_url'] . '#event-contact', 'outline-light'],
          ];
          break;
  }
  echo ctaBlock($ctaTitle, $ctaBody, $ctaButtons);
  ?>
</main>

<?php include 'partials/footer.php'; ?>