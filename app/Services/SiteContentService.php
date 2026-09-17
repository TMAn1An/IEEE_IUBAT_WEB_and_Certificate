<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Small computed-content helpers for the public site. Direct port of the
 * free functions in reference/legacy-site/includes/components.php that
 * aren't pure markup builders (those became Blade components instead —
 * see resources/views/components/site/). Data itself lives in config/site.php.
 */
class SiteContentService
{
    /** Human "day month" label, e.g. fmtDay('2026-08-28') -> '28 August'. */
    public function fmtDay(string $date): string
    {
        return (new DateTimeImmutable($date))->format('j F');
    }

    /** Short form, e.g. fmtDayShort('2026-08-28') -> '28 Aug'. */
    public function fmtDayShort(string $date): string
    {
        return (new DateTimeImmutable($date))->format('j M');
    }

    /** Escaped src for a member photo from its short name, or null. */
    public function memberPhoto(?string $short): ?string
    {
        return $short !== null ? '/assets/img/members/'.$short.'.jpg' : null;
    }

    /**
     * Single source of truth for "what point in the event lifecycle is it
     * right now", compared in Bangladesh Standard Time regardless of server
     * timezone. Both the header alert banner and the exhibition page's
     * registration CTAs read this so the site needs no manual edit through
     * early-bird, regular, closed, the event days themselves, and afterwards.
     * assets/js/main.js's countdown mirrors these same five phases
     * client-side for its live-ticking clock (kept in sync by hand, like
     * every other config value the JS side falls back to).
     *
     * @param  array<string, mixed>  $event  config('site.event')
     * @return 'early'|'regular'|'closed'|'live'|'ended'
     */
    public function eventPhase(array $event): string
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

    /**
     * The header alert banner's phase-aware text/link, or null once the
     * event has ended (the banner stops rendering entirely at that point).
     * Direct port of the switch in the original partials/header.php.
     *
     * @param  array<string, mixed>  $event
     * @return array{phase: string, text: string, linkUrl: string, linkLabel: string}|null
     */
    public function headerAlert(array $event): ?array
    {
        $phase = $this->eventPhase($event);

        $text = '';
        $linkUrl = $event['page_url'].'#fees';
        $linkLabel = 'Register';

        switch ($phase) {
            case 'early':
                $text = 'early-bird registration closes '.$this->fmtDay($event['early_bird']).'.';
                break;
            case 'regular':
                $text = 'regular registration closes '.$this->fmtDay($event['regular_deadline']).'.';
                break;
            case 'closed':
                $text = 'registration is now closed &mdash; see you on '.$this->fmtDay($event['date']).'.';
                $linkUrl = $event['page_url'];
                $linkLabel = 'Details';
                break;
            case 'live':
                $text = 'is happening now at '.$event['venue'].'.';
                $linkUrl = $event['page_url'].'#schedule';
                $linkLabel = 'View schedule';
                break;
            case 'ended':
                return null;
        }

        return compact('phase', 'text', 'linkUrl', 'linkLabel');
    }

    /**
     * The nav bar's phase-aware call-to-action button. Direct port of the
     * second switch in the original partials/header.php.
     *
     * @param  array<string, mixed>  $event
     * @return array{url: string, label: string, external: bool}
     */
    public function navCta(array $event): array
    {
        $phase = $this->eventPhase($event);

        $url = $event['register_url'];
        $label = 'Register for the Exhibition';
        $external = true;

        switch ($phase) {
            case 'closed':
                $url = $event['page_url'];
                $label = 'Registration closed';
                $external = false;
                break;
            case 'live':
                $url = $event['page_url'].'#schedule';
                $label = 'Happening now';
                $external = false;
                break;
            case 'ended':
                $url = $event['page_url'];
                $label = 'Event details';
                $external = false;
                break;
        }

        return compact('url', 'label', 'external');
    }

    /** A person row from config('site.people') by its key, or null. */
    public function personByKey(string $key): ?array
    {
        foreach (config('site.people') as $person) {
            if ($person['key'] === $key) {
                return $person;
            }
        }

        return null;
    }

    /**
     * Event JSON-LD built from config — keeps the fee table and the
     * structured data from drifting apart.
     */
    public function eventJsonLd(): string
    {
        $e = config('site.event');
        $s = config('site.site');
        $eb = $this->fmtDayShort($e['early_bird']);
        $rs = $this->fmtDayShort($e['regular_start']);
        $rd = $this->fmtDayShort($e['regular_deadline']);

        $offers = [];
        foreach ($e['fees'] as $f) {
            $offers[] = [
                '@type' => 'Offer',
                'name' => $f['cat'],
                'description' => "Early bird {$f['early']} BDT (up to {$eb} 2026); regular {$f['regular']} BDT ({$rs} – {$rd} 2026).",
                'price' => (string) $f['early'],
                'priceCurrency' => 'BDT',
                'url' => $s['origin'].$e['page_url'].'#fees',
                'availability' => 'https://schema.org/LimitedAvailability',
            ];
        }

        $json = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => html_entity_decode($e['long_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'url' => $s['origin'].$e['page_url'],
            'image' => $s['origin'].'/assets/img/poster-exhibition.jpg',
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

    /** The subset of event config pushed to the browser for assets/js/main.js. */
    public function eventClientConfig(): string
    {
        $e = config('site.event');

        return json_encode([
            'earlyBirdDeadline' => $e['early_bird_end'],
            'regularDeadline' => $e['regular_end'],
            'eventStart' => $e['event_start'],
            'eventEnd' => $e['event_end'],
            'formUrl' => $e['register_url'],
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
