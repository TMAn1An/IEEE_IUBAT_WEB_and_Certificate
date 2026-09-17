<?php

namespace App\Http\Controllers;

use App\Services\SiteContentService;
use Illuminate\Contracts\View\View;

class EventController extends Controller
{
    public function __construct(private readonly SiteContentService $siteContent) {}

    public function becithcon2026(): View
    {
        $event = config('site.event');
        $phase = $this->siteContent->eventPhase($event);

        $bcSubtitle = match ($phase) {
            'ended' => 'This conference has concluded &mdash; thank you to everyone who took part. Keep an eye on the branch for what&rsquo;s next.',
            'live' => 'The conference is happening now at IUBAT. See the schedule below for what&rsquo;s underway.',
            default => 'A two-day international conference on biomedical engineering, computer and information technology for health &mdash; presented as a full programme for participants, authors and session chairs.',
        };

        return view('pages.events.becithcon-2026', [
            'pageTitle' => 'IEEE BECITHCON 2026 &mdash; IEEE IUBAT Student Branch',
            'pageDesc' => 'IEEE BECITHCON 2026 — the 4th IEEE International Conference on Biomedical Engineering, Computer and Information Technology for Health, hosted by IUBAT. Full two-day programme, keynotes, technical sessions and venues.',
            'pageUrl' => 'https://ieee.iubat.edu/event/becithcon-2026',
            'current' => 'events',
            'bodyClass' => 'theme-event',
            'becithcon' => config('site.becithcon'),
            'bcSubtitle' => $bcSubtitle,
        ]);
    }

    public function hta2026(): View
    {
        $event = config('site.event');
        $phase = $this->siteContent->eventPhase($event);

        // Phase-aware hero: eventPhase() is the single source of truth so this
        // block, the [data-clock] box's JS-rendered content, the header alert
        // banner and the fees/registration section below all agree with no
        // manual edit needed through early-bird, regular, closed, the event
        // days themselves, and afterwards. The clock/eyebrow/date default text
        // is set correctly server-side too (not just left for JS to fix on
        // load), so a visitor without JS still sees accurate information.
        $clockFallback = ''; // shown until JS's countdown() takes over; kept in sync with its messages{} map
        switch ($phase) {
            case 'early':
                $clockEyebrow = 'Early-bird registration closes';
                $clockDate = $this->siteContent->fmtDay($event['early_bird']).' 2026';
                $heroPrimary = ['Register your team', $event['register_url'], true];
                $heroSecondary = ['Jump to fees', '#fees'];
                break;
            case 'regular':
                $clockEyebrow = 'Regular registration closes';
                $clockDate = $this->siteContent->fmtDay($event['regular_deadline']).' 2026';
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
                $clockDate = $this->siteContent->fmtDay($event['date']).' 2026';
                $clockFallback = '<p class="clock--over">The event is happening now &mdash; see the schedule below.</p>';
                $heroPrimary = ['View schedule', '#schedule', false];
                $heroSecondary = ['Talk to the organising team', '#event-contact'];
                break;
            default: // ended
                $clockEyebrow = 'Event concluded';
                $clockDate = $this->siteContent->fmtDay($event['date']).' 2026';
                $clockFallback = '<p class="clock--over">This event has concluded. Thank you to everyone who took part.</p>';
                $heroPrimary = ['See other events', '/events', false];
                $heroSecondary = ['Talk to the organising team', '#event-contact', false];
                break;
        }

        $registrationOpen = in_array($phase, ['early', 'regular'], true);

        $feesSubtitle = $registrationOpen
            ? 'Fees are per participant. Early-bird pricing runs until '.$this->siteContent->fmtDay($event['early_bird']).
              ' 2026; regular pricing applies from '.$this->siteContent->fmtDay($event['regular_start']).' to '.$this->siteContent->fmtDay($event['regular_deadline']).' 2026.'
            : 'Fees are per participant. Shown here for reference &mdash; registration for this event has closed.';

        switch ($phase) {
            case 'early':
            case 'regular':
                $ctaTitle = 'Bring your prototype to Uttara';
                $ctaBody = 'Open to medical and engineering students across Bangladesh. Register your team, complete payment, and turn up on 5 September with something that works.';
                $ctaButtons = [
                    ['Open the registration form', $event['register_url'], 'light', true],
                    ['Review the fees', $event['page_url'].'#fees', 'outline-light'],
                ];
                break;
            case 'closed':
                $ctaTitle = 'See you on 5 September';
                $ctaBody = 'Registration has closed, but the HTA exhibition &amp; training runs on 5 September within BECITHCON 2026 at IUBAT, Uttara.';
                $ctaButtons = [
                    ['View the schedule', $event['page_url'].'#schedule', 'light'],
                    ['Talk to the organising team', $event['page_url'].'#event-contact', 'outline-light'],
                ];
                break;
            case 'live':
                $ctaTitle = 'Happening now within BECITHCON 2026';
                $ctaBody = 'The Region 10 HTA exhibition &amp; volunteer training is underway on day two of BECITHCON 2026. See what\'s on right now.';
                $ctaButtons = [
                    ['View the schedule', $event['page_url'].'#schedule', 'light'],
                    ['See the full BECITHCON programme', '/event/becithcon-2026', 'outline-light'],
                ];
                break;
            default: // ended
                $ctaTitle = 'Thank you for taking part';
                $ctaBody = 'This programme has concluded. Keep an eye on the branch for what\'s next.';
                $ctaButtons = [
                    ['See other events', '/events', 'light'],
                    ['Talk to the organising team', $event['page_url'].'#event-contact', 'outline-light'],
                ];
                break;
        }

        return view('pages.events.hta-2026', [
            'pageTitle' => 'IEEE Region 10 HTA Exhibition &amp; Volunteer Training Program &mdash; BECITHCON 2026 &middot; IEEE IUBAT Student Branch',
            'pageDesc' => 'IEEE Region 10 Humanitarian Technologies Activities (HTA) Exhibition & Volunteer Training Program 2026 — co-organised with IEEE BECITHCON 2026. Schedule, prizes, speakers and fees.',
            'pageUrl' => 'https://ieee.iubat.edu/event/hta-2026',
            'current' => 'events',
            'bodyClass' => 'theme-event',
            'voxelQr' => true,
            'pageJsonLd' => $this->siteContent->eventJsonLd(),
            'event' => $event,
            'phase' => $phase,
            'clockEyebrow' => $clockEyebrow,
            'clockDate' => $clockDate,
            'clockFallback' => $clockFallback,
            'heroPrimary' => $heroPrimary,
            'heroSecondary' => $heroSecondary,
            'registrationOpen' => $registrationOpen,
            'feesSubtitle' => $feesSubtitle,
            'ctaTitle' => $ctaTitle,
            'ctaBody' => $ctaBody,
            'ctaButtons' => $ctaButtons,
        ]);
    }
}
