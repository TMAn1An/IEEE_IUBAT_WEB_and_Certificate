<?php
$pageTitle = 'Contact &mdash; IEEE IUBAT Student Branch';
$pageDesc = 'Contact the IEEE IUBAT Student Branch — email, phone, social media and office hours.';
$pageUrl = 'https://ieee.iubat.edu/contact';
$current = 'contact';
include 'partials/head.php';
include 'partials/header.php';

$site = config()['site'];
?>

<?= crumb([['label' => 'Home', 'url' => '/'], ['label' => 'Contact']]) ?>

<main id="main">
  <?= pagehead('Contact','Contact the branch','Questions about membership, events, sponsorship or volunteering &mdash; here is who to ask.') ?>

  <section class="section">
    <div class="wrap">
      <div class="grid grid--4">
        <div class="card rv" style="text-align:center;padding:32px 24px">
          <div class="card__icon" style="margin:0 auto 14px"><?= svg('mail') ?></div>
          <h3>Email</h3>
          <a href="mailto:<?= $site['email'] ?>" style="font-size:1.05rem;font-weight:600"><?= $site['email'] ?></a>
        </div>
        <div class="card rv" style="text-align:center;padding:32px 24px">
          <div class="card__icon" style="margin:0 auto 14px"><?= svg('phone') ?></div>
          <h3>Phone</h3>
          <a href="tel:<?= $site['phone_dial'] ?>" style="font-size:1.05rem;font-weight:600"><?= $site['phone'] ?></a>
        </div>
        <div class="card rv" style="text-align:center;padding:32px 24px">
          <div class="card__icon" style="margin:0 auto 14px"><?= svg('facebook') ?></div>
          <h3>Facebook</h3>
          <a href="<?= $site['facebook'] ?>" target="_blank" rel="noopener" style="font-size:1.05rem;font-weight:600">ieeeiubatsb</a>
        </div>
        <div class="card rv" style="text-align:center;padding:32px 24px">
          <div class="card__icon" style="margin:0 auto 14px"><?= svg('linkedin') ?></div>
          <h3>LinkedIn</h3>
          <a href="<?= $site['linkedin'] ?>" target="_blank" rel="noopener" style="font-size:1.05rem;font-weight:600">IEEE IUBAT SB</a>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--shell">
    <div class="wrap">
      <div class="split">
        <div class="rv">
          <span class="eyebrow">Where we are</span>
          <h2>Visit the branch</h2>
          <p>
            IUBAT &mdash; International University of Business Agriculture and Technology<br>
            4 Embankment Drive Road, Sector 10<br>
            Uttara Model Town, Dhaka 1230<br>
            Bangladesh
          </p>
          <ul class="ticks" style="margin-bottom:16px">
            <li><strong>Days:</strong> Saturday through Wednesday</li>
            <li><strong>Hours:</strong> 9:00 AM &ndash; 5:00 PM</li>
            <li><strong>Dept:</strong> Department of EEE, IUBAT campus</li>
          </ul>
          <iframe class="map-embed" src="<?= $site['maps_embed'] ?>" title="Map showing the IUBAT campus" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </div>

        <div class="rv">
          <span class="eyebrow">Write to us</span>
          <h2>Send us a message</h2>
          <p class="contact-form__intro">Add a subject and your message, then send &mdash; your mail app opens ready to email <strong><?= $site['email'] ?></strong>.</p>
          <form class="contact-form" data-mailto="<?= $site['email'] ?>">
            <div class="field">
              <label class="field__label" for="cf-subject">Subject</label>
              <input class="field__input" type="text" id="cf-subject" name="subject" required>
            </div>
            <div class="field">
              <label class="field__label" for="cf-message">Message</label>
              <textarea class="field__input field__input--area" id="cf-message" name="message" rows="5" maxlength="1500" required></textarea>
            </div>
            <div>
<button class="btn btn--primary" type="submit">Send email</button>
          </div>
          </form>
        </div>
      </div>
    </div>
  </section>

  <?= ctaBlock('Become part of the branch','IEEE membership opens the door to the world\'s largest technical professional organization &mdash; and the branch is where you put it to use.', [
      ['How to join', '/membership', 'light'],
      ['Join IEEE', 'https://www.ieee.org/join', 'outline-light', true],
  ]) ?>
</main>

<?php include 'partials/footer.php'; ?>