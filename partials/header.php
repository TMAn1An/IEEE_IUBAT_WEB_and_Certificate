<?php
/* Shared page chrome: <body>, skip-link, site-notice banner, the IEEE
   enterprise meta-navigation, and the masthead (identifier, nav, IEEE
   Master Brand). Set these variables before including:
   - $current   ('home' | 'about' | 'events' | 'contact') — which nav item
                is highlighted; subpages under About/Events use that panel.
   - $bodyClass (optional string, e.g. 'theme-event' on the exhibition page)
   Data comes from includes/config.php (nav, current event, contact details).
   The IEEE brand-lock items below are required: the meta-navigation links
   must never be reordered or renamed, and the Master Brand must stay
   min 100x33px, white or black only, alt text exactly "IEEE", linking to
   www.ieee.org.
*/
require_once __DIR__ . '/../includes/components.php';

$current   = $current ?? '';
$bodyClass = $bodyClass ?? '';
$isCur     = fn(string $key): string => $current === $key ? ' is-current' : '';
$ariaCur   = fn(string $key): string => $current === $key ? ' aria-current="page"' : '';

$site  = config()['site'];
$nav   = config()['nav'];
$event = config()['event'];

$navRows = '';
foreach ($nav as $key => $item) {
    if (!empty($item['panel'])) {
        $links = '';
        foreach ($item['panel'] as $p) {
            $links .= '<li><a href="' . $p['url'] . '">' . $p['label'] . '<small>' . $p['sub'] . '</small></a></li>';
        }
        $navRows .= '<li class="nav__item has-panel' . $isCur($key) . '"><button class="nav__link" type="button" aria-expanded="false">' . $item['label'] . svg('caret', 'class="nav__caret"') . '</button><ul class="nav__panel">' . $links . '</ul></li>' . "\n        ";
    } else {
        $navRows .= '<li class="nav__item' . $isCur($key) . '"><a class="nav__link" href="' . $item['url'] . '"' . $ariaCur($key) . '>' . $item['label'] . '</a></li>' . "\n        ";
    }
}
// Alert phrase is phase-aware via eventPhase() (includes/components.php) —
// early-bird -> regular -> closed -> live (the event days themselves) ->
// ended, so the banner needs no manual edit at any point, including through
// and after the event itself. Once 'ended' the banner stops rendering
// entirely rather than showing a stale notice indefinitely.
$alertPhase = eventPhase($event);
$alertText = '';
$alertLinkUrl = $event['page_url'] . '#fees';
$alertLinkLabel = 'Register';
switch ($alertPhase) {
    case 'early':
        $alertText = 'early-bird registration closes ' . fmtDay($event['early_bird']) . '.';
        break;
    case 'regular':
        $alertText = 'regular registration closes ' . fmtDay($event['regular_deadline']) . '.';
        break;
    case 'closed':
        $alertText = 'registration is now closed &mdash; see you on ' . fmtDay($event['date']) . '.';
        $alertLinkUrl = $event['page_url'];
        $alertLinkLabel = 'Details';
        break;
    case 'live':
        $alertText = 'is happening now at ' . $event['venue'] . '.';
        $alertLinkUrl = $event['page_url'] . '#schedule';
        $alertLinkLabel = 'View schedule';
        break;
}

// Nav CTA is phase-aware too — same phase as the alert banner above, so it
// never invites registration for an event whose registration has closed
// (it would otherwise keep pointing at a closed Google Form through the
// event days and afterwards).
$navCtaUrl = $event['register_url'];
$navCtaLabel = 'Register for the Exhibition';
$navCtaExternal = true;
switch ($alertPhase) {
    case 'closed':
        $navCtaUrl = $event['page_url'];
        $navCtaLabel = 'Registration closed';
        $navCtaExternal = false;
        break;
    case 'live':
        $navCtaUrl = $event['page_url'] . '#schedule';
        $navCtaLabel = 'Happening now';
        $navCtaExternal = false;
        break;
    case 'ended':
        $navCtaUrl = $event['page_url'];
        $navCtaLabel = 'Event details';
        $navCtaExternal = false;
        break;
}
?>
<body class="<?= $bodyClass ?>">
<a class="skip-link" href="#main">Skip to content</a>

<?php if ($alertPhase !== 'ended'): ?>
<!-- Alert banner. IEEE asks that sites keep a prominent slot for
     time-sensitive notices. Self-clearing once eventPhase() reaches
     'ended' — see includes/components.php. -->
<div class="alert" role="region" aria-label="Site notice">
  <div class="wrap alert__in">
    <?= svg('bell') ?>
    <p class="alert__text"><strong><?= $event['name'] ?></strong> &mdash; <?= $alertText ?> <a href="<?= $alertLinkUrl ?>"><?= $alertLinkLabel ?></a></p>
    <button class="alert__x" type="button" aria-label="Dismiss notice">&times;</button>
  </div>
</div>
<?php endif; ?>

<!-- IEEE enterprise meta-navigation. Required on all IEEE websites.
     Do not reorder or rename these links. -->
<div class="metanav">
  <div class="wrap metanav__in">
    <ul>
      <li><a href="https://www.ieee.org/">IEEE.org</a></li>
      <li><a href="https://ieeexplore.ieee.org/">IEEE <em>Xplore</em>&reg; Digital Library</a></li>
      <li><a href="https://standards.ieee.org/">IEEE Standards</a></li>
      <li><a href="https://spectrum.ieee.org/">IEEE Spectrum</a></li>
      <li><a href="https://www.ieee.org/sitemap.html">More Sites</a></li>
    </ul>
    <ul>
      <li><a href="https://www.ieee.org/join">Join IEEE</a></li>
      <li><a href="https://www.ieee.org/give">Donate</a></li>
    </ul>
  </div>
</div>

<header class="header">
  <div class="wrap header__bar">

    <!-- Site identifier: upper left, links to the home page, contains IEEE,
         and is larger than the IEEE Master Brand. -->
    <a class="identifier" href="/">
      <img src="<?= $site['logo'] ?>" alt="">
      <span class="identifier__txt">
        <span class="identifier__name"><?= $site['name'] ?></span>
        <span class="identifier__tag"><?= $site['tagline'] ?></span>
      </span>
    </a>

    <button class="burger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="site-nav">
      <span></span>
    </button>

    <div class="header__right">
      <nav aria-label="Main">
        <ul class="nav" id="site-nav">
        <?= $navRows ?>
          <li class="nav__cta">
            <a class="btn btn--green btn--sm" href="<?= $navCtaUrl ?>"<?= $navCtaExternal ? ' target="_blank" rel="noopener"' : '' ?>><?= $navCtaLabel ?></a>
          </li>
        </ul>
      </nav>

      <!-- IEEE Master Brand: upper right, minimum 100x33px, white or black
           only, alt text exactly "IEEE", links to www.ieee.org. -->
      <a class="masterbrand" href="https://www.ieee.org" aria-label="IEEE">
        <img src="/assets/img/ieee-masterbrand-black.png" alt="IEEE" width="113" height="33">
      </a>
    </div>

  </div>
</header>