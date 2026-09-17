<?php
require_once __DIR__ . '/includes/components.php';
$pageTitle = 'IEEE BECITHCON 2026 &mdash; IEEE IUBAT Student Branch';
$pageDesc = 'IEEE BECITHCON 2026 — the 4th IEEE International Conference on Biomedical Engineering, Computer and Information Technology for Health, hosted by IUBAT. Full two-day programme, keynotes, technical sessions and venues.';
$pageUrl = 'https://ieee.iubat.edu/event/becithcon-2026';
$current = 'events';
$bodyClass = 'theme-event';
include 'partials/head.php';
include 'partials/header.php';

$site    = config()['site'];
$bc      = config()['becithcon'];
$event   = config()['event'];
$phase   = eventPhase($event);

$bcSubtitle = match ($phase) {
    'ended'      => 'This conference has concluded &mdash; thank you to everyone who took part. Keep an eye on the branch for what&rsquo;s next.',
    'live'       => 'The conference is happening now at IUBAT. See the schedule below for what&rsquo;s underway.',
    default      => 'A two-day international conference on biomedical engineering, computer and information technology for health &mdash; presented as a full programme for participants, authors and session chairs.',
};
?>

<?= crumb([['label' => 'Home', 'url' => '/'], ['label' => 'Events', 'url' => '/events'], ['label' => 'BECITHCON 2026']]) ?>

<main id="main">
  <?= pagehead('IEEE BECITHCON 2026','Biomedical Engineering, Computer &amp; Information Technology for Health','The 4th IEEE International Conference on Biomedical Engineering, Computer and Information Technology for Health 2026, hosted by IUBAT in Dhaka. Two days of keynote talks, invited talks and 31 parallel technical sessions in a hybrid format.', $bcSubtitle) ?>

  <section class="section" id="overview">
    <div class="wrap">
      <div class="grid grid--2" style="gap:52px;align-items:start">
        <div class="rv">
          <span class="eyebrow">About the conference</span>
          <h2>A forum for health technology research</h2>
          <p class="lead">
            BECITHCON brings together researchers, engineers, clinicians and students working
            at the intersection of biomedical engineering, computer science and information
            technology for health.
          </p>
          <p>
            Around 100 papers are presented across 31 parallel technical sessions over the two
            days, alongside keynote talks from international leaders in biomedical engineering and
            invited talks from academia and industry.
          </p>
        </div>

        <div class="rv">
          <div class="card" style="border-left:4px solid var(--ieee-blue)">
            <h3>At a glance</h3>
            <ul class="ticks" style="margin-bottom:0">
              <li><strong>Dates:</strong> <?= $bc['date_label'] ?></li>
              <li><strong>Venue:</strong> <?= $bc['venue'] ?></li>
              <li><strong>Main venue:</strong> <?= $bc['main_venue'] ?></li>
              <li><strong>Format:</strong> Hybrid &mdash; physical and virtual sessions</li>
              <li><strong>Technical sessions:</strong> 31 parallel sessions</li>
              <li><strong>Publication:</strong> IEEE Xplore</li>
              <li><strong>Organised by:</strong> IEEE Bangladesh Section &middot; IEEE EMBS Bangladesh Chapter &middot; IEEE Region 10 &middot; IUBAT</li>
            </ul>
          </div>
        </div>
      </div>

      <div style="margin-top:40px">
        <?= callout('info','Branch co-programme &mdash; Region 10 HTA','<p style="margin-bottom:0">Alongside the main conference, the IEEE IUBAT Student Branch is co-organising a <strong>Region 10 Humanitarian Technologies Activities (HTA) exhibition &amp; volunteer training program</strong> on the second day. See the <a href="/event/hta-2026">HTA exhibition &amp; training page</a> for that programme&rsquo;s schedule, prizes and team details.</p>') ?>
      </div>
    </div>
  </section>

  <section class="section section--shell" id="programme">
    <div class="wrap">
      <?= section_head('Full programme','Two days, morning to night','The official day-by-day schedule. Sessions sharing a time-slot run in parallel &mdash; the room and track code for each is listed.') ?>

      <p style="margin-top:-8px;margin-bottom:24px">
        <a class="btn btn--green" href="<?= $bc['full_program_pdf'] ?>" target="_blank" rel="noopener">Full programme (PDF) <?= svg('arrow') ?></a>
        <a class="btn btn--ghost" href="<?= $bc['sessions_pdf'] ?>" target="_blank" rel="noopener">Session schedule &amp; papers (PDF) <?= svg('arrow') ?></a>
      </p>

      <?php foreach ($bc['days'] as $day): ?>
        <div class="head" style="margin-top:8px">
          <span class="eyebrow"><?= $bc['name'] ?> &middot; <?= $day['day'] ?></span>
          <h3 style="font-size:1.5rem;margin:4px 0 0"><?= $day['heading'] ?></h3>
        </div>

        <div class="tablewrap rv">
          <div class="tablescroll">
            <table>
              <caption class="sr-only"><?= $day['heading'] ?> programme</caption>
              <thead>
                <tr>
                  <th scope="col" style="min-width:150px">Time</th>
                  <th scope="col">Programme &amp; speakers</th>
                </tr>
              </thead>
              <tbody>
<?php foreach ($day['tracks'] as $t): ?>
                <tr>
                  <td class="cat"><?= $t['time'] ?></td>
                  <td>
                    <strong><?= $t['title'] ?></strong>
<?php if (!empty($t['detail'])): ?>
                    <div style="margin-top:4px;font-size:.9rem;color:var(--muted)"><?= $t['detail'] ?></div>
<?php endif; ?>
                  </td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>

      <p class="note">Times are in Bangladesh Standard Time (BST). The HTA entries above are the branch&rsquo;s co-organised Region 10 Humanitarian Technologies Activities programme &mdash; full details on the <a href="/event/hta-2026">HTA page</a>.</p>
    </div>
  </section>

  <section class="section" id="talks">
    <div class="wrap">
      <?= section_head('Keynotes &amp; invited talks','Recognised voices in health technology','Keynote and invited talks from international researchers and practitioners in biomedical engineering and health informatics.') ?>

      <div class="grid grid--3">
        <div class="card rv" style="border-left:4px solid var(--ieee-blue)"><span class="eyebrow">Keynote</span><h3>Ram Bilas Pachori</h3><p class="person__detail">Fellow IEEE &middot; Professor, Indian Institute of Technology (IIT) Indore, India</p></div>
        <div class="card rv"><span class="eyebrow">Keynote</span><h3>Pingkun Yan</h3><p class="person__detail">Fellow AIMBE &middot; Professor &amp; Head, Dept. of Biomedical Engineering, Rensselaer Polytechnic Institute, USA</p></div>
        <div class="card rv"><span class="eyebrow">Invited Talk-1</span><h3>Marianna Semprini</h3><p class="person__detail">University of Genova, Italy</p></div>
        <div class="card rv"><span class="eyebrow">Invited</span><h3>Shuvomoy Mondol</h3><p class="person__detail">School of Medical Sciences, IIT Kharagpur, India</p></div>
        <div class="card rv"><span class="eyebrow">Invited</span><h3>Tonmoy Ghosh</h3><p class="person__detail">AI Engineer, Ingram Content Group, USA</p></div>
        <div class="card rv"><span class="eyebrow">Invited</span><h3>Dipayan Saha</h3><p class="person__detail">Senior R&amp;D Engineer, Caspia Tech, USA</p></div>
        <div class="card rv"><span class="eyebrow">Invited</span><h3>Ruwan Gopura</h3><p class="person__detail">University of Moratuwa, Sri Lanka</p></div>
        <div class="card rv"><span class="eyebrow">Invited</span><h3>Hugo Silva</h3><p class="person__detail">University of Lisbon, Portugal</p></div>
        <div class="card rv"><span class="eyebrow">Invited</span><h3>Hasan Al-Nashash</h3><p class="person__detail">American University of Sharjah, UAE</p></div>
        <div class="card rv"><span class="eyebrow">Invited</span><h3>Yuan Yang</h3><p class="person__detail">University of Illinois Urbana-Champaign, USA</p></div>
      </div>
    </div>
  </section>

  <section class="section section--shell" id="organisers">
    <div class="wrap">
      <?= section_head('Organisers','Jointly organised &amp; hosted','') ?>
      <div class="grid grid--3">
        <div class="card rv"><h3>IEEE Bangladesh Section</h3><p>Regional IEEE body for Bangladesh, supporting technical conferences and student activities nationwide.</p></div>
        <div class="card rv"><h3>IEEE EMBS Bangladesh Chapter</h3><p>The Engineering in Medicine and Biology Society chapter in Bangladesh &mdash; advancing biomedical engineering professionally.</p></div>
        <div class="card rv"><h3>IEEE Region 10</h3><p>The IEEE Region covering Asia-Pacific, supporting the conference and the co-organised HTA humanitarian programme.</p></div>
      </div>
      <div style="margin-top:28px">
        <?= callout('info','Hosted by IUBAT','<p style="margin-bottom:0">The conference is hosted by IUBAT &mdash; International University of Business Agriculture and Technology, 4 Embankment Drive Road, Sector&nbsp;10, Uttara Model Town, Dhaka&nbsp;1230.</p>', ['green' => false]) ?>
      </div>
    </div>
  </section>

  <?= ctaBlock('Become part of the branch','IEEE membership opens the door to the world\'s largest technical professional organization &mdash; and the branch is where you put it to use.', [
      ['Region 10 HTA exhibition &amp; training', '/event/hta-2026', 'light'],
      ['All events', '/events', 'outline-light'],
  ]) ?>
</main>

<?php include 'partials/footer.php'; ?>
