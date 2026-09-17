<?php
/*
 * Small reusable render helpers. Each returns (or echoes) a well-known block
 * of the site's markup so pages describe *content* while the markup stays in
 * one place. The output CSS class names must stay in sync with
 * assets/css/style.css — the helpers only produce that contract.
 *
 * Data comes from config() (includes/config.php). When a DB layer lands, the
 * data source changes there; these functions and the pages keep working.
 */

require_once __DIR__ . '/config.php';

/** Human "day month" label, e.g. fmtDay('2026-08-28') -> '28 August'. */
function fmtDay(string $date): string
{
    return (new DateTimeImmutable($date))->format('j F');
}

/** Short form, e.g. fmtDayShort('2026-08-28') -> '28 Aug'. */
function fmtDayShort(string $date): string
{
    return (new DateTimeImmutable($date))->format('j M');
}

/** Escaped src for a member photo from its short name, or null. */
function memberPhoto(?string $short): ?string
{
    return $short !== null ? '/assets/img/members/' . $short . '.jpg' : null;
}

/**
 * Single source of truth for "what point in the event lifecycle is it right
 * now", compared in Bangladesh Standard Time regardless of server timezone.
 * Both the header alert banner and the exhibition page's registration CTAs
 * read this so the site needs no manual edit through early-bird, regular,
 * closed, the event days themselves, and afterwards — see the event_start/
 * event_end comment in config.php. assets/js/main.js's countdown mirrors
 * these same five phases client-side for its live-ticking clock (kept in
 * sync by hand, like every other config value the JS side falls back to).
 *
 * @param array<string,mixed> $event config()['event']
 * @return 'early'|'regular'|'closed'|'live'|'ended'
 */
function eventPhase(array $event): string
{
    $bst = new DateTimeZone('Asia/Dhaka');
    $now = new DateTimeImmutable('now', $bst);

    if ($now < new DateTimeImmutable($event['early_bird_end'], $bst)) {
        return 'early';
    }
    if ($now <= new DateTimeImmutable($event['regular_end'], $bst)) {
        return 'regular';
    }
    if ($now < new DateTimeImmutable($event['event_start'], $bst)) {
        return 'closed';
    }
    if ($now <= new DateTimeImmutable($event['event_end'], $bst)) {
        return 'live';
    }
    return 'ended';
}

/* --------------------------------------------------------------- icons */
/* One map per icon. Names are used by iconCard()/callout() and anywhere a
   shared glyph is needed. Stroke icons carry their original attrs (including
   stroke-width 2 vs 2.4 and round/join), fill icons are `fill=currentColor`. */
function svg(string $name, string $extraAttrs = ''): string
{
    static $icons = [
        'arrow'    => [false, 2.4, 'round', '', '<path d="M5 12h14M13 6l6 6-6 6"/>'],
        'calendar' => [false, 2, 'round', '', '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
        'pin'      => [false, 2, 'round', '', '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'],
        'mail'     => [false, 2, 'round', '', '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>'],
        'phone'    => [false, 2, 'round', '', '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>'],
        'facebook' => [true, 0, '', '', '<path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12z"/>'],
        'linkedin' => [true, 0, '', '', '<path d="M4.98 3.5A2.5 2.5 0 1 1 0 3.5a2.5 2.5 0 0 1 4.98 0zM.5 8.5h4V24h-4zM8 8.5h3.8v2.1h.06c.53-1 1.83-2.1 3.77-2.1 4.03 0 4.77 2.65 4.77 6.1V24h-4v-6.6c0-1.57-.03-3.6-2.2-3.6-2.2 0-2.53 1.72-2.53 3.5V24H8z"/>'],
        'heart'    => [false, 2, 'round', 'round', '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8z"/>'],
        'tech'     => [false, 2, 'round', 'round', '<rect x="6" y="6" width="12" height="12" rx="1"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>'],
        'book'     => [false, 2, 'round', 'round', '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>'],
        'network'  => [false, 2, 'round', 'round', '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>'],
        'user'     => [false, 2, 'round', '', '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
        'users'    => [false, 2, 'round', 'round', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>'],
        'ticket'   => [false, 2, 'round', 'round', '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'],
        'trophy'   => [false, 2, 'round', 'round', '<circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/>'],
        'globe'    => [false, 2, 'round', 'round', '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/>'],
        'monitor'  => [false, 2, 'round', 'round', '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>'],
        'info'     => [false, 2, 'round', '', '<circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/>'],
        'bell'     => [false, 2, 'round', '', '<path d="M10.3 3.3a2 2 0 0 1 3.4 0l8 13.4A2 2 0 0 1 20 20H4a2 2 0 0 1-1.7-3.3z"/><path d="M12 9v4M12 17h.01"/>'],
        'caret'    => [false, 3, 'round', '', '<path d="m6 9 6 6 6-6"/>'],
        'zoom'     => [false, 2, 'round', '', '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>'],
        'star'     => [true, 0, '', '', '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>'],
    ];

    if (!isset($icons[$name])) {
        return '<!-- missing icon: ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' -->';
    }

    [$fill, $stroke, $linecap, $linejoin, $body] = $icons[$name];
    $attrs = 'viewBox="0 0 24 24" ' . ($fill ? 'fill="currentColor" ' : 'fill="none" stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="' . $linecap . '"' . ($linejoin ? ' stroke-linejoin="' . $linejoin . '"' : '') . ' ') . 'aria-hidden="true"' . ($extraAttrs ? ' ' . $extraAttrs : '');
    return '<svg ' . $attrs . '>' . $body . '</svg>';
}

/* ------------------------------------------------------------ blocks */
/* Breadcrumb bar. $trail: list of ['label' => .., 'url' => ..?]; the last item
   without a url is the current page. */
/** @param array<int, array{label: string, url?: string}> $trail */
function crumb(array $trail): string
{
    $items = '';
    $last = array_key_last($trail);
    foreach ($trail as $i => $step) {
        if ($i === $last) {
            $items .= '<li aria-current="page">' . $step['label'] . '</li>';
        } else {
            $items .= '<li><a href="' . ($step['url'] ?? '') . '">' . $step['label'] . '</a></li>';
        }
    }
    return '<nav class="crumbs" aria-label="Breadcrumb">' . "\n" . '  <div class="wrap"><ol>' . $items . '</ol></div>' . "\n" . '</nav>';
}

/* Page masthead. $extra is output raw after the intro paragraph (stats,
   timers, CTA rows — see the exhibition page). */
function pagehead(string $eyebrow, string $title, string $lead, string $extra = ''): string
{
    return '<section class="pagehead">' . "\n" .
        '  <div class="wrap">' . "\n" .
        '    <span class="eyebrow">' . $eyebrow . '</span>' . "\n" .
        '    <h1>' . $title . '</h1>' . "\n" .
        '    <p>' . $lead . '</p>' . "\n" .
        ($extra !== '' ? '    ' . $extra . "\n" : '') .
        '  </div>' . "\n" . '</section>';
}

/* Recurring section heading (eyebrow + h2 + optional lead). */
function section_head(string $eyebrow, string $title, string $lead = ''): string
{
    $leadHtml = $lead !== '' ? "\n" . '        <p>' . $lead . '</p>' : '';
    return '<div class="head rv">' . "\n" .
        '  <span class="eyebrow">' . $eyebrow . '</span>' . "\n" .
        '  <h2>' . $title . '</h2>' . $leadHtml . "\n" .
        '</div>';
}

/* Icon card: <article class="card">. $opt: 'card' extra classes on the card
   (e.g. 'card--green'), 'title' tag (h3 default), 'rv' reveal default true.
   Pass '' as the icon for a plain text card. */
/** @param array{card?: string, title?: string, rv?: bool} $opt */
function iconCard(string $icon, string $title, string $body, array $opt = []): string
{
    $extra = $opt['card'] ?? '';
    $tag = $opt['title'] ?? 'h3';
    $rv = ($opt['rv'] ?? true) ? ' rv' : '';
    $iconHtml = $icon !== '' ? '  <div class="card__icon">' . svg($icon) . '</div>' . "\n" : '';
    return '<article class="card' . ($extra ? ' ' . $extra : '') . $rv . '">' . "\n" .
        $iconHtml .
        '  <' . $tag . '>' . $title . '</' . $tag . '>' . "\n" .
        '  <p>' . $body . '</p>' . "\n" .
        '</article>';
}

/* Person card. $p keys: photo (short name) | initials, role, name, detail
   (optional), tel (optional — personal number; only rendered when $tel is
   true, because committee.php never shows phone numbers while the exhibition
   organising-team block does), email (optional — institutional role address,
   rendered unconditionally, unlike tel). */
/** @param array{key: string, photo?: string, initials?: string, role: string, name: string, detail?: string, tel?: string, email?: string} $p */
function person(array $p, bool $rv = true, bool $tel = true): string
{
    $reveal = $rv ? ' rv' : '';
    $line   = "<div class=\"person{$reveal}\">\n";

    $photo = memberPhoto($p['photo'] ?? null);
    if ($photo !== null) {
        $line .= '  <img class="person__av" src="' . $photo . '" alt="Photo of ' . $p['name'] . '" width="80" height="80">' . "\n";
    } else {
        $line .= '  <div class="person__av">' . ($p['initials'] ?? '--') . '</div>' . "\n";
    }

    $line .= '  <div class="person__role">' . $p['role'] . '</div>' . "\n";
    $line .= '  <h3>' . $p['name'] . '</h3>' . "\n";
    if (!empty($p['detail'])) {
        $line .= '  <p class="person__detail">' . $p['detail'] . '</p>' . "\n";
    }
    if ($tel && !empty($p['tel'])) {
        $dial = preg_replace('/\s+/', '', $p['tel']);
        $line .= '  <a class="person__link" href="tel:' . $dial . '">' . svg('phone') . ' ' . $p['tel'] . '</a>' . "\n";
    }
    if (!empty($p['email'])) {
        $line .= '  <a class="person__link" href="mailto:' . $p['email'] . '">' . svg('mail') . ' ' . $p['email'] . '</a>' . "\n";
    }
    $line .= '</div>';
    return $line;
}

/* person() by config key, so pages can reference a specific member. $tel
   suppresses the personal phone link (committee.php passes false). */
function personByKey(string $key, bool $rv = true, bool $tel = true): string
{
    foreach (config()['people'] as $p) {
        if ($p['key'] === $key) {
            return person($p, $rv, $tel);
        }
    }
    return '<!-- person not found: ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . ' -->';
}

/* The closing CTA band. $buttons: list of [label, href, type, target?] where
   type is 'light' | 'outline-light'. */
/** @param array<int, array{0: string, 1: string, 2: string, 3?: bool}> $buttons */
function ctaBlock(string $title, string $sub, array $buttons): string
{
    $row = '';
    foreach ($buttons as $btn) {
        $target = $btn[3] ?? null;
        $ext = $target ? ' target="_blank" rel="noopener"' : '';
        $row .= '        <a class="btn btn--' . $btn[2] . '" href="' . $btn[1] . '"' . $ext . '>' . $btn[0] . '</a>' . "\n";
    }
    return '<section class="cta">' . "\n" .
        '  <div class="wrap">' . "\n" .
        '    <h2>' . $title . '</h2>' . "\n" .
        '    <p>' . $sub . '</p>' . "\n" .
        '    <div class="cta__row">' . "\n" . $row . '    </div>' . "\n" .
        '  </div>' . "\n" . '</section>';
}

/* Green info callout. $body is raw HTML (one or more <p>). */
/** @param array{h?: string, green?: bool} $opt */
function callout(string $icon, string $title, string $body, array $opt = []): string
{
    $tag = $opt['h'] ?? 'h3';
    $cls = $opt['green'] ?? true;
    return '<div class="callout' . ($cls ? ' callout--green' : '') . '">' . "\n" .
        '  ' . svg($icon) . "\n" .
        '  <div>' . "\n" .
        '    <' . $tag . '>' . $title . '</' . $tag . '>' . "\n" .
        '    ' . $body . "\n" .
        '  </div>' . "\n" .
        '</div>';
}

/* Count-up stat. Text shown is {$count}{$suffix}. */
function statCard(string $count, string $label, string $suffix = '', string $extraClass = ''): string
{
    $sufAttr = $suffix !== '' ? ' data-suffix="' . $suffix . '"' : '';
    $cls = $extraClass !== '' ? ' class="' . $extraClass . '"' : '';
    return '<div class="stat' . $cls . '"><div class="stat__n"><span data-count="' . $count . '"' . $sufAttr . '>' . $count . $suffix . '</span></div><div class="stat__l">' . $label . '</div></div>';
}

/* Event JSON-LD built from config — keeps the fee table and the structured
   data from drifting apart. */
function eventJsonLd(): string
{
    $e  = config()['event'];
    $s  = config()['site'];
    $eb = fmtDayShort($e['early_bird']);
    $rs = fmtDayShort($e['regular_start']);
    $rd = fmtDayShort($e['regular_deadline']);

    $offers = [];
    foreach ($e['fees'] as $f) {
        $offers[] = [
            '@type' => 'Offer',
            'name' => $f['cat'],
            'description' => "Early bird {$f['early']} BDT (up to {$eb} 2026); regular {$f['regular']} BDT ({$rs} – {$rd} 2026).",
            'price' => (string) $f['early'],
            'priceCurrency' => 'BDT',
            'url' => $s['origin'] . $e['page_url'] . '#fees',
            'availability' => 'https://schema.org/LimitedAvailability',
        ];
    }

    $json = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => html_entity_decode($e['long_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'url' => $s['origin'] . $e['page_url'],
        'image' => $s['origin'] . '/assets/img/poster-exhibition.jpg',
        'description' => 'A one-day exhibition and volunteer training program for medical and engineering students in Bangladesh, focused on low-cost humanitarian medical and healthcare technology.',
        'startDate' => $e['date'],
        'endDate' => $e['date'],
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'location' => [
            '@type' => 'Place',
            'name' => 'IUBAT — International University of Business Agriculture and Technology',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => '4 Embankment Drive Road, Sector 10, Uttara Model Town',
                'addressLocality' => 'Dhaka',
                'postalCode' => '1230',
                'addressCountry' => 'BD',
            ],
        ],
        'organizer' => ['@type' => 'Organization', 'name' => $s['name'], 'url' => $s['origin']],
        'offers' => $offers,
    ];

    return json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
}

/* The subset of event config pushed to the browser for assets/js/main.js. */
function eventClientConfig(): string
{
    $e  = config()['event'];
    return json_encode([
        'earlyBirdDeadline' => $e['early_bird_end'],
        'regularDeadline'   => $e['regular_end'],
        'eventStart'        => $e['event_start'],
        'eventEnd'          => $e['event_end'],
        'formUrl'           => $e['register_url'],
    ], JSON_UNESCAPED_SLASHES) ?: '{}';
}