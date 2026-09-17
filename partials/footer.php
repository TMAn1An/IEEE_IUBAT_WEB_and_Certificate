<?php
require_once __DIR__ . '/../includes/components.php';

$site  = config()['site'];
$nav   = config()['nav'];
$event = config()['event'];

$footerCols = '';
foreach ($nav as $key => $item) {
    if (empty($item['panel'])) continue;
    $links = '';
    foreach ($item['panel'] as $p) {
        $links .= '          <li><a href="' . $p['url'] . '">' . $p['label'] . '</a></li>' . "\n";
    }
    $footerCols .= '      <div>' . "\n" . '        <h4>' . $item['label'] . '</h4>' . "\n" . '        <ul>' . "\n" . $links . '        </ul>' . "\n" . '      </div>' . "\n";
}
?><footer class="footer">
  <div class="footer__main">
    <div class="wrap footer__grid">
      <div>
        <div class="footer__id">
          <img src="<?= $site['logo'] ?>" alt="">
          <span><?= $site['short'] ?></span>
        </div>
        <p>Advancing technology for humanity &mdash; a student branch of IEEE at IUBAT, Dhaka.</p>
        <div class="social">
          <a href="<?= $site['facebook'] ?>" target="_blank" rel="noopener" aria-label="Facebook"><?= svg('facebook') ?></a>
          <a href="<?= $site['linkedin'] ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><?= svg('linkedin') ?></a>
        </div>
      </div>

<?= $footerCols ?>
      <div>
        <h4>Contact</h4>
        <ul>
          <li class="footer__line"><?= svg('pin') ?><span><?= $site['address'] ?></span></li>
          <li class="footer__line"><a href="mailto:<?= $site['email'] ?>"><?= svg('mail') ?><span><?= $site['email'] ?></span></a></li>
          <li class="footer__line"><a href="tel:<?= $site['phone_dial'] ?>"><?= svg('phone') ?><span><?= $site['phone'] ?></span></a></li>
        </ul>
      </div>
    </div>
  </div>

  <!-- IEEE-required administrative footer links. Link directly to the IEEE
       policies; do not replicate their text on this site. -->
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

<script>window.IEEE_EVENT = <?= eventClientConfig() ?>;</script>
<script src="/assets/js/particles.js"></script>
<script src="/assets/js/main.js"></script>

<?php if (!empty($pageScripts)): ?>
<?= $pageScripts ?>
<?php endif; ?>

</body>
</html>