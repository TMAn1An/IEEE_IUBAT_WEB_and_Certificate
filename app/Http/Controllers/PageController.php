<?php

namespace App\Http\Controllers;

use App\Services\SiteContentService;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function __construct(private readonly SiteContentService $siteContent) {}

    public function home(): View
    {
        $event = config('site.event');
        $phase = $this->siteContent->eventPhase($event);

        return view('pages.home', [
            'pageTitle' => 'IEEE IUBAT Student Branch',
            'pageDesc' => 'IEEE IUBAT Student Branch — the official IEEE student community at IUBAT. Join workshops, exhibitions and training programs for engineering students in Bangladesh.',
            'pageUrl' => 'https://ieee.iubat.edu/',
            'pageImage' => config('site.site.origin').config('site.site.branch_photo'),
            'pageImageW' => '1200',
            'pageImageH' => '800',
            'current' => 'home',
            'event' => $event,
            'becithcon' => config('site.becithcon'),
            'registrationOpen' => in_array($phase, ['early', 'regular'], true),
        ]);
    }

    public function about(): View
    {
        return view('pages.about', [
            'pageTitle' => 'Our Student Branch &mdash; IEEE IUBAT Student Branch',
            'pageDesc' => 'About the IEEE IUBAT Student Branch — our story, what we do and how we are organised. Established in 2025 at IUBAT, Dhaka.',
            'pageUrl' => 'https://ieee.iubat.edu/about',
            'pageImage' => config('site.site.origin').config('site.site.branch_photo'),
            'pageImageW' => '1200',
            'pageImageH' => '800',
            'current' => 'about',
        ]);
    }

    public function committee(): View
    {
        $execOrder = [
            'chair', 'vc-activities', 'vc-technical', 'secretary', 'treasurer', 'webmaster',
            'asst-secretary', 'organizing', 'tech-coord', 'designer', 'content', 'photography',
            'logistics', 'photo-content', 'gm-1', 'gm-2', 'gm-3',
        ];

        return view('pages.committee', [
            'pageTitle' => 'Committees &mdash; IEEE IUBAT Student Branch',
            'pageDesc' => 'Meet the IEEE IUBAT Student Branch committee — ExCom leadership, student advisors, advisory committee and student panel members.',
            'pageUrl' => 'https://ieee.iubat.edu/committee',
            'pageImage' => config('site.site.origin').config('site.site.branch_photo'),
            'pageImageW' => '1200',
            'pageImageH' => '800',
            'current' => 'about',
            'execCommittee' => array_map(
                fn (string $key) => $this->siteContent->personByKey($key),
                $execOrder
            ),
        ]);
    }

    public function contact(): View
    {
        return view('pages.contact', [
            'pageTitle' => 'Contact &mdash; IEEE IUBAT Student Branch',
            'pageDesc' => 'Contact the IEEE IUBAT Student Branch — email, phone, social media and office hours.',
            'pageUrl' => 'https://ieee.iubat.edu/contact',
            'current' => 'contact',
        ]);
    }

    public function events(): View
    {
        $event = config('site.event');
        $phase = $this->siteContent->eventPhase($event);

        $upcomingLabel = match ($phase) {
            'live' => ['Happening now', 'Live now'],
            'ended' => ['Recently', 'Just wrapped up'],
            default => ['Upcoming', 'Next up'],
        };

        return view('pages.events', [
            'pageTitle' => 'All Events &mdash; IEEE IUBAT Student Branch',
            'pageDesc' => 'Upcoming and past events by IEEE IUBAT Student Branch — workshops, exhibitions, training programs and more at IUBAT campus.',
            'pageUrl' => 'https://ieee.iubat.edu/events',
            'current' => 'events',
            'event' => $event,
            'upcomingLabel' => $upcomingLabel,
        ]);
    }

    public function membership(): View
    {
        return view('pages.membership', [
            'pageTitle' => 'Membership &mdash; IEEE IUBAT Student Branch',
            'pageDesc' => 'Join IEEE as an IUBAT student member. Access workshops, exhibitions, training programs and professional development resources.',
            'pageUrl' => 'https://ieee.iubat.edu/membership',
            'current' => 'about',
            'event' => config('site.event'),
        ]);
    }
}
