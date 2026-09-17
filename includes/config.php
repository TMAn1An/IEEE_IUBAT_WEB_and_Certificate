<?php
/*
 * Site-wide configuration — the single source of truth for the data the pages
 * and partials render. Pages only need page-specific vars ($pageTitle,
 * $pageDesc, $pageUrl, $current, ...) plus include of the partials; everything
 * shared lives here.
 *
 * Plain PHP arrays today. When an admin/DB layer lands, swap the internals of
 * config() to read rows instead of literals — the rest of the site keeps
 * calling config() and does not change.
 */

/** @return array{site: array<string,string>, nav: array<string,array<string,mixed>>, chapters: array<int,array<string,string>>, event: array<string,mixed>, becithcon: array<string,mixed>, people: array<int,array{key: string, photo?: string, initials?: string, role: string, name: string, detail?: string, tel?: string, email?: string}>} */
function config(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }

    $c = [

        /* ----------------------------------------------------- site identity */
        'site' => [
            'name'        => 'IEEE IUBAT Student Branch',
            'short'       => 'IEEE IUBAT<br>Student Branch',
            'tagline'     => 'Advancing technology for humanity',
            'origin'      => 'https://ieee.iubat.edu',
            'email'       => 'ieeeiubatsb@iubat.edu',
            'phone'       => '+880 1872 626934',          // pretty, for display
            'phone_dial'  => '+8801872626934',            // for tel: links
            'address'     => 'IUBAT &mdash; International University of Business Agriculture and Technology, 4 Embankment Drive Road, Sector&nbsp;10, Uttara Model Town, Dhaka&nbsp;1230, Bangladesh',
            'facebook'    => 'https://www.facebook.com/ieeeiubatsb',
            'linkedin'    => 'https://www.linkedin.com/company/ieeeiubatsb',
            'maps_embed'  => 'https://maps.google.com/maps?q=IUBAT+International+University+of+Business+Agriculture+and+Technology+Uttara+Dhaka&z=15&hl=en&output=embed',
            // Default Open Graph image (1200x1200). Pages may override with $pageImage.
            'og_image'    => 'https://ieee.iubat.edu/assets/img/poster-exhibition.jpg',
            'og_image_w'  => '1200',
            'og_image_h'  => '1200',
            'theme_color' => '#00629B',
            'favicon'     => '/assets/img/favicon.png',
            'logo'        => '/assets/img/logo.png',
            'branch_photo' => '/assets/img/ieee-iubat-sb.jpg',
        ],

        /* ---------------------------------------------------- main navigation */
        /* Keys are the $current values pages pass to header.php. `panel`
           (optional) renders the dropdown beneath a top-level item. */
        'nav' => [
            'home' => [
                'label' => 'Home',
                'url'   => '/',
            ],
            'about' => [
                'label' => 'About',
                'url'   => '/about',
                'panel' => [
                    ['label' => 'Our Student Branch', 'url' => '/about',        'sub' => 'Who we are and what we do'],
                    ['label' => 'Committees',         'url' => '/committee',    'sub' => 'The people behind the branch'],
                    ['label' => 'Membership',         'url' => '/membership',   'sub' => 'Join IEEE and the branch'],
                ],
            ],
            'events' => [
                'label' => 'Events',
                'url'   => '/events',
                'panel' => [
                    ['label' => 'All Events',                       'url' => '/events',            'sub' => 'Everything we run'],
                    ['label' => 'BECITHCON 2026',                   'url' => '/event/becithcon-2026','sub' => '4&ndash;5 September 2026 &middot; Full schedule'],
                    ['label' => 'Region 10 HTA Exhibition 2026',    'url' => '/event/hta-2026',    'sub' => '5 September 2026 &middot; BECITHCON 2026'],
                ],
            ],
            'contact' => [
                'label' => 'Contact',
                'url'   => '/contact',
            ],
        ],

        /* ------------------------------------------------- chapters / affinity */
        'chapters' => [
            ['name' => 'Robotics and Automation Society Chapter',        'abbr' => 'RAS',  'img' => 'chapter-ras'],
            ['name' => 'Engineering in Medicine and Biology Society Chapter', 'abbr' => 'EMBS', 'img' => 'chapter-embs'],
            ['name' => 'Computational Intelligence Society Chapter',     'abbr' => 'CIS',  'img' => 'chapter-cis'],
            ['name' => 'Women in Engineering Affinity Group',            'abbr' => 'WIE',  'img' => 'chapter-wie'],
            ['name' => 'Computer Society Chapter',                       'abbr' => 'CS',   'img' => 'chapter-cs'],
        ],

        /* ----------------------------------------------------- current event */
        /* The exhibition page, the header alert, the countdown clocks and the
           JS side all read these. The footer also exposes the same values to
           window.IEEE_EVENT so assets/js/main.js has one source of truth.
           Times are in Dhaka time (UTC+06:00). */
        'event' => [
            'name'          => 'IEEE Region 10 HTA Exhibition 2026',
            'long_name'     => 'Region 10 Humanitarian Technologies Activities (HTA) Exhibition &amp; Volunteer Training Program',
            'page_url'      => '/event/hta-2026',
            'becithcon_url' => '/event/becithcon-2026',
            'register_url'  => 'https://forms.gle/AkKmzLBX8t84c4GE8',
            'date'          => '2026-09-05',
            'date_label'    => '5 September 2026',
            'venue'         => 'IUBAT, Uttara',
            'early_bird'    => '2026-08-28',
            'regular_start' => '2026-08-29',
            'regular_deadline' => '2026-09-01',
            'early_bird_end'  => '2026-08-28T23:59:59+06:00',   // countdown target
            'regular_end'     => '2026-09-01T23:59:59+06:00',
            // BECITHCON 2026 runs 4-5 Sept; the branch's own R10-HTA training/
            // exhibition/awards day is the 5th. event_start/event_end drive the
            // "live now" / "concluded" phases (see eventPhase() in
            // components.php) so the alert banner, countdown and registration
            // CTAs self-correct through and after the event with no manual
            // edits. event_end is generously end-of-day on the 5th rather than
            // the schedule's literal 6pm finish, so the site doesn't flip to
            // "concluded" while the programme is still informally wrapping up.
            'event_start'     => '2026-09-04T00:00:00+06:00',
            'event_end'       => '2026-09-05T23:59:59+06:00',
            'fees'          => [
                ['cat' => 'IEEE Student Member',         'early' => 250, 'regular' => 300, 'eligibility' => 'Must be a registered IEEE Student Member'],
                ['cat' => 'Non-IEEE Student',            'early' => 375, 'regular' => 500, 'eligibility' => 'Open to all non-IEEE students'],
                ['cat' => 'IEEE SB Executive Committee', 'early' => 100, 'regular' => 200, 'eligibility' => 'Chair, Vice Chair, Secretary or Treasurer of an IEEE Student Branch or Student Branch Chapter'],
                ['cat' => 'BECITHCON 2026 participant',  'early' => 100, 'regular' => 200, 'eligibility' => 'Must already be registered for BECITHCON 2026'],
            ],
        ],

        /* --------------------------------------------------- BECITHCON 2026 */
        /* The umbrella conference our branch co-organises. Its registration
           and paper submission closed long before this site launched, so it is
           presented here as an informational page (programme, keynotes, venues)
           with no registration flow — the branch's own R10-HTA exhibition /
           training register separately (see config()['event'] above). The
           programme below is transcribed from the official two-day schedule;
           parallel sessions share the same time-slot, so each slot may carry
           several tracks. */
        'becithcon' => [
            'name'          => 'IEEE BECITHCON 2026',
            'long_name'     => 'IEEE International Conference on Biomedical Engineering, Computer and Information Technology for Health 2026',
            'edition'       => '4th',
            'page_url'      => '/event/becithcon-2026',
            'sessions_pdf'  => '/assets/pdf/BECITHCON2026_Session_Schedule.pdf',
            'full_program_pdf' => '/assets/pdf/BECITHCON2026_Full_Program_Schedule.pdf',
            'date_label'    => '4&ndash;5 September 2026',
            'venue'         => 'IUBAT Campus, Dhaka',
            'main_venue'    => 'Room 227 (Conference Room)',
            'date_start'    => '2026-09-04',
            'date_end'      => '2026-09-05',
            'organisers'    => 'Jointly organised by IEEE Region 10, IEEE Bangladesh Section and IEEE EMBS Bangladesh Chapter; hosted by IUBAT.',
            'days' => [
                [
                    'day'       => 'Day 1',
                    'heading'   => 'Friday, 04 September 2026',
                    'tracks'    => [
                        ['time' => '8:30 – 9:30 AM', 'title' => 'Registration'],
                        ['time' => '09:00 – 10:30 AM', 'title' => 'Parallel Technical Sessions (1–5)', 'detail' => 'TS1 (Room 1208) BSP1 &middot; TS2 (Room 1207) MIA1 &middot; TS3 (Room 1209) WRB1 &middot; TS4 (Virtual VR1-R209) BSP2 &middot; TS5 (Virtual VR2-R224) NDA'],
                        ['time' => '10:30 – 11:00 AM', 'title' => 'Tea Break'],
                        ['time' => '11:00 AM – 12:15 PM', 'title' => 'Inaugural Ceremony', 'detail' => 'Room 227 &mdash; detailed order of inaugural session as per protocol'],
                        ['time' => '12:15 – 1:00 PM', 'title' => 'Keynote Talk-01', 'detail' => 'Prof. Ram Bilas Pachori &mdash; Fellow IEEE, Professor, Indian Institute of Technology (IIT) Indore, India'],
                        ['time' => '01:00 – 2:30 PM', 'title' => 'Lunch and Prayer Break'],
                        ['time' => '02:30 – 4:00 PM', 'title' => 'Parallel Technical Sessions (6–11)', 'detail' => 'TS6 (R1210) CAT1 &middot; TS7 (R1207) MIA2 &middot; TS8 (R1208) RSD1 &middot; TS9 (R1209) SKD &middot; TS10 (VR2-R209) BSP3 &middot; TS11 (VR1-R224) CAT2'],
                        ['time' => '04:00 – 4:15 PM', 'title' => 'Tea Break'],
                        ['time' => '04:00 – 5:30 PM', 'title' => 'Invited Talks', 'detail' => 'Room 227 &mdash; Shuvomoy Mondol (School of Med. Sci., IIT Kharagpur, India) 4:00&ndash;4:30 PM &middot; Tonmoy Ghosh (AI Engineer, Ingram Content Group, USA) &amp; Dipayan Saha (Senior R&amp;D Engineer, Caspia Tech, USA) 4:30&ndash;5:00 PM'],
                        ['time' => '04:30 – 6:00 PM', 'title' => 'Parallel Technical Sessions (12–18)', 'detail' => 'TS12 (R1210) AIH1 &middot; TS13 (R1208) CLP &middot; TS14 (R1207) HIF1 &middot; TS15 (R1209) MNH1 &middot; TS16-18 (Virtual) CAT2 / MIA5 / RSD3'],
                        ['time' => '06:00 – 7:00 PM', 'title' => 'Keynote Talk-02', 'detail' => 'Dr. Pingkun Yan &mdash; Fellow AIMBE, Professor &amp; Head, Dept. of BME, Rensselaer Polytechnic Institute, USA'],
                    ],
                ],
                [
                    'day'       => 'Day 2',
                    'heading'   => 'Saturday, 05 September 2026',
                    'tracks'    => [
                        ['time' => '09:00 – 10:30 AM', 'title' => 'Parallel Technical Sessions (19–24)', 'detail' => 'TS19 (R1209) BIF &middot; TS20 (R1208) HIF2 &middot; TS21 (R1210) MIA3 &middot; TS21 (R1207) RSD2 &middot; TS22-24 (Virtual) BMD2 / ESD / MNH2'],
                        ['time' => '09:30 – 10:30 AM', 'title' => 'HTA Training Session (Physical) — Room 324', 'detail' => 'Part of the Region 10 HTA program (&lsquo;see the <a href="/event/hta-2026">HTA exhibition &amp; training page</a>&rsquo;)'],
                        ['time' => '10:30 – 10:45 AM', 'title' => 'Tea Break'],
                        ['time' => '10:00 AM – 12:15 PM', 'title' => 'Interactive Workshops &amp; Project Exhibition', 'detail' => 'Project Showcasing &amp; Technical Expert Forum &mdash; 11th Floor Open Space Area (&lsquo;see the <a href="/event/hta-2026">HTA page</a>&rsquo;)'],
                        ['time' => '10:45 – 11:45 AM', 'title' => 'HTA Training Session (Physical) — Room 324'],
                        ['time' => '12:15 – 12:45 PM', 'title' => 'Invited Talk-1', 'detail' => 'Marianna Semprini &mdash; University of Genova, Italy'],
                        ['time' => '12:45 – 2:00 PM', 'title' => 'Lunch and Prayer Break'],
                        ['time' => '02:00 – 2:30 PM', 'title' => 'HTA Talks Speaker-01', 'detail' => 'Branch co-organised, part of the <a href="/event/hta-2026">Region 10 HTA program</a>'],
                        ['time' => '02:30 – 4:00 PM', 'title' => 'Parallel Technical Sessions (25–31)', 'detail' => 'TS25 (R1208) BMD1 &middot; TS26 (R1207) CRD &middot; TS27 (R1209) HIF3 &middot; TS28 (R1210) MIA4 &middot; TS29-31 (Virtual) AIH2 / AST / CBI'],
                        ['time' => '04:00 – 4:15 PM', 'title' => 'Tea Break'],
                        ['time' => '04:00 – 6:00 PM', 'title' => 'HTA Project Presentation', 'detail' => '11th Floor Open Space Area (&lsquo;Region 10 HTA program&rsquo;)'],
                        ['time' => '04:00 – 6:45 PM', 'title' => 'Invited Talks Series', 'detail' => 'Room 227 &mdash; Dr. Ruwan Gopura (Univ. of Moratuwa, Sri Lanka) 4:00&ndash;4:45 PM &middot; Hugo Silva (Univ. of Lisbon, Portugal) 4:45&ndash;5:15 PM &middot; Hasan Al-Nashash (American Univ. of Sharjah, UAE) 5:30&ndash;6:00 PM &middot; Yuan Yang (Univ. of Illinois Urbana-Champaign, USA) 6:00&ndash;6:30 PM'],
                        ['time' => '05:30 – 6:00 PM', 'title' => 'HTA Award &amp; Closing', 'detail' => '11th Floor Open Space Area (&lsquo;Region 10 HTA program&rsquo;)'],
                        ['time' => '07:00 – 9:30 PM', 'title' => 'Award Ceremony, Closing Ceremony, Cultural Gala &amp; Dinner', 'detail' => 'Official concluding session and networking dinner'],
                    ],
                ],
            ],
        ],

        /* ------------------------------------------------------- committee */
        /* One row per person card on committee.php and the exhibition page.
           `photo` renders an <img> (with alt from name); otherwise `initials`
           fall back to the monogram avatar. `tel` carries a space-formatted
           personal number, but is never rendered on committee.php — pages opt
           in explicitly via person()/personByKey() `$tel` flag (see CLAUDE.md). */
        'people' => [
            /* Faculty advisors — no photos, no personal tel links; official
               role-based email addresses (public-safe, unlike personal tel) */
            ['key' => 'counsellor',    'initials' => 'KA', 'role' => 'Counsellor',                 'name' => 'Dr. Khadiza Akter',            'detail' => 'Associate Professor &amp; Coordinator, Dept. of EEE', 'email' => 'khadiza@iubat.edu'],
            ['key' => 'adviser-2',     'initials' => 'UD', 'role' => 'Advisor',                    'name' => 'Dr. Utpal Kanti Das',          'detail' => 'Professor &amp; Dean, Chair, IUBAT School of Computer Science and Engineering', 'email' => 'ukd@iubat.edu'],
            ['key' => 'adviser-3',     'initials' => 'AB', 'role' => 'Advisor',                    'name' => 'Engr. Md Abul Bashar',         'detail' => 'Associate Professor, Dept. of EEE', 'email' => 'mabashar@iubat.edu'],
            /* Student advisory panel */
            ['key' => 'advisor-1',     'photo' => 'azim-sharkar',         'role' => 'Student Advisor',  'name' => 'Azim Sharkar',            'detail' => 'IEEE ID: 100982277'],
            ['key' => 'mentor-1',      'photo' => 'abu-huraira-sumon',    'role' => 'Mentor',           'name' => 'Abu Huraira Sumon',       'detail' => 'IEEE ID: 100982394'],
            /* Executive committee (2026–27) — tel public for exec leadership only */
            ['key' => 'chair',              'photo' => 'ibrahim-kholil',       'role' => 'Chair',                        'name' => 'Ibrahim Kholil',                'detail' => 'IEEE ID: 101619688', 'tel' => '+880 1872 626934', 'email' => 'chair@ieee.iubat.edu'],
            ['key' => 'vc-activities',      'photo' => 'atikur-rahman-atik',   'role' => 'Vice-Chair (Activities)',      'name' => 'Md. Atikur Rahman Atik',        'detail' => 'IEEE ID: 101647465', 'tel' => '+880 1726 892171', 'email' => 'vc-actv@ieee.iubat.edu'],
            ['key' => 'vc-technical',       'photo' => 'mainul-islam',         'role' => 'Vice-Chair (Technical)',       'name' => 'Md. Mainul Islam',                  'detail' => 'IEEE ID: 102177803', 'tel' => '+880 1837 837834', 'email' => 'vc-tech@ieee.iubat.edu'],
            ['key' => 'secretary',          'photo' => 'imtiaz-sarkar',        'role' => 'Secretary',                    'name' => 'MD. Imtiaz Sarkar',              'detail' => 'IEEE ID: 101647528', 'tel' => '+880 1568 016429', 'email' => 'secretary@ieee.iubat.edu'],
            ['key' => 'asst-secretary',     'photo' => 'sazzad-majumder',      'role' => 'Assistant Secretary',          'name' => 'Sazzad Majumder',                'detail' => 'IEEE ID: 101647433'],
            ['key' => 'treasurer',          'photo' => 'sazzadul-islam',       'role' => 'Treasurer',                    'name' => 'Md Sazzadul Islam',              'detail' => 'IEEE ID: 101647476', 'email' => 'treasurer@ieee.iubat.edu'],
            ['key' => 'webmaster',          'photo' => 'yasin-ahmed-shuvo',    'role' => 'Webmaster',                    'name' => 'Yasin Ahmed Shuvo',              'detail' => 'IEEE ID: 102179712', 'tel' => '+880 1624 816426', 'email' => 'webmaster@ieee.iubat.edu'],
            ['key' => 'organizing',         'photo' => 'saffana-islam-shreosi','role' => 'Organizing Secretary',         'name' => 'Saffana Islam Shreosi',           'detail' => 'IEEE ID: 101944856'],
            ['key' => 'tech-coord',         'photo' => 'md-akash',              'role' => 'Technical Activities Coordinator', 'name' => 'Md Akash',               'detail' => 'IEEE ID: 101647720'],
            ['key' => 'designer',           'photo' => 'azadur-rahman-rana',   'role' => 'Graphics Designer',            'name' => 'Md. Azadur Rahman Rana',         'detail' => 'IEEE ID: 101647623'],
            ['key' => 'content',            'photo' => 'asrafi-islam-orpita',  'role' => 'Content, Media and Publicity Coordinator', 'name' => 'Asrafi Islam Orpita', 'detail' => 'IEEE ID: 102184988'],
            ['key' => 'photography',        'photo' => 'abdullah-al-nasif',    'role' => 'Photography Lead',             'name' => 'Md.Abdullah Al Nasif Asif',      'detail' => 'IEEE ID: 102181921'],
            ['key' => 'logistics',          'photo' => 'tangel-hossen-sehan', 'role' => 'Logistic Secretary',              'name' => 'Tangel Hossen Sehan',            'detail' => 'IEEE ID: 102275243'],
            ['key' => 'photo-content',      'photo' => 'fahim-sarker',        'role' => 'Photography and Content Coordinator', 'name' => 'MD. Fahim Sarker',            'detail' => 'IEEE ID: 102581429'],
            ['key' => 'gm-1', 'initials' => 'MR', 'role' => 'General Member',  'name' => 'Md Rifat Hasan',                'detail' => ''],
            ['key' => 'gm-2', 'initials' => 'MH', 'role' => 'General Member',  'name' => 'Md Mehedi Hasan',               'detail' => ''],
            ['key' => 'gm-3', 'initials' => 'AS', 'role' => 'General Member',  'name' => 'Ali Asif Shahrear',             'detail' => ''],
        ],
    ];

    return $c;
}