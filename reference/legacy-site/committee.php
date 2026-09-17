<?php
require_once __DIR__ . '/includes/components.php';
$pageTitle = 'Committees &mdash; IEEE IUBAT Student Branch';
$pageDesc = 'Meet the IEEE IUBAT Student Branch committee — ExCom leadership, student advisors, advisory committee and student panel members.';
$pageUrl = 'https://ieee.iubat.edu/committee';
$pageImage = config()['site']['origin'] . config()['site']['branch_photo'];
$pageImageW = '1200';
$pageImageH = '800';
$current = 'about';
include 'partials/head.php';
include 'partials/header.php';
?>

<?= crumb([['label' => 'Home', 'url' => '/'], ['label' => 'About', 'url' => '/about'], ['label' => 'Committees']]) ?>

<main id="main">
  <?= pagehead('About','Executive Committee','The volunteers, advisors and faculty who plan, fund and run the branch\'s activities, and who represent IEEE IUBAT to the Bangladesh Section.') ?>

  <section class="section">
    <div class="wrap">
      <?= section_head('Faculty','Advisory Committee','Faculty members who guide the branch\'s direction and act as liaisons between the student body and the university.') ?>

      <div class="grid grid--3">
        <?= personByKey('counsellor') . "\n" . personByKey('adviser-2') . "\n" . personByKey('adviser-3') ?>
      </div>
    </div>
  </section>

  <section class="section section--shell">
    <div class="wrap">
      <?= section_head('Guidance','Student Advisory Panel','Senior student members who mentor the executive committee and help maintain continuity across terms.') ?>

      <div class="grid grid--3">
        <?= personByKey('advisor-1') . "\n" . personByKey('mentor-1') ?>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <?= section_head('2026&ndash;27 term','Who runs the branch','The Executive Committee is responsible for the branch\'s direction, its events and budgets, its reporting to IEEE, and the day-to-day business of keeping members active.') ?>

      <div class="grid grid--3">
        <?php
        $exec = ['chair','vc-activities','vc-technical','secretary','treasurer','webmaster','asst-secretary','organizing','tech-coord','designer','content','photography','logistics','photo-content','gm-1','gm-2','gm-3'];
        $rows = [];
        foreach ($exec as $k) $rows[] = personByKey($k, true, false);
        echo implode("\n", $rows);
        ?>
      </div>
    </div>
  </section>

  <section class="section section--shell">
    <div class="wrap">
      <div class="split">
        <div class="rv">
          <span class="eyebrow">Responsibilities</span>
          <h2>What the committee does</h2>
          <ul class="ticks">
            <li>Sets the branch's direction and annual plan</li>
            <li>Organises events, workshops and competitions</li>
            <li>Handles budgets, sponsorship and expenditure</li>
            <li>Reports to the IEEE Bangladesh Section and Region 10</li>
            <li>Recruits members and runs volunteer development</li>
            <li>Represents IEEE IUBAT to the university and to partners</li>
          </ul>
        </div>

        <div class="rv">
          <?= callout('info','Want to volunteer?','<p>Committee roles open at the start of each term, but volunteering does not wait for an election &mdash; most events need help long before that. Write to the Chair and say what you would like to work on.</p><p style="margin-bottom:0"><a href="/contact">Get in touch with the team</a></p>') ?>
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