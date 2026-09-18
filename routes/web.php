<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\VerificationController;
use App\Services\Certificates\VerificationCodewordService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site — canonical routes
|--------------------------------------------------------------------------
| Exact URL set the original plain-PHP site served (see reference/legacy-
| site/.htaccess and docs/MIGRATION_PLAN.md). Do not change these paths
| without updating docs/MIGRATION_PLAN.md and adding a redirect for the old
| one — see CLAUDE.md's "preserve existing public URLs" rule.
*/

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/committee', [PageController::class, 'committee'])->name('committee');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/events', [PageController::class, 'events'])->name('events');
Route::get('/membership', [PageController::class, 'membership'])->name('membership');

Route::get('/event/becithcon-2026', [EventController::class, 'becithcon2026'])->name('events.becithcon2026');
Route::get('/event/hta-2026', [EventController::class, 'hta2026'])->name('events.hta2026');

/*
|--------------------------------------------------------------------------
| Public certificate verification (Phase 6)
|--------------------------------------------------------------------------
| No auth, no admin layout -- see docs/CERTIFICATE_SYSTEM.md §Public
| verification. The `where()` pattern is shared with the codeword generator
| and the Excel importer's format check (VerificationCodewordService::
| ACCEPTED_PATTERN) so this route never rejects a legitimately-preserved
| historical codeword. throttle:60,1 (60 req/min/IP) deters brute-force
| codeword enumeration without being annoying for normal QR scanning.
*/
Route::get('/certificate/verify/{codeword}', [VerificationController::class, 'show'])
    ->where('codeword', VerificationCodewordService::ACCEPTED_PATTERN)
    ->middleware('throttle:60,1')
    ->name('certificate.verify');

/*
|--------------------------------------------------------------------------
| Legacy URL redirects
|--------------------------------------------------------------------------
| Every row in docs/MIGRATION_PLAN.md's "legacy URLs that must 301-redirect"
| table. The original site hid .php behind mod_rewrite and had been through
| three slugs for the HTA exhibition page on its way to /event/hta-2026;
| none of those old links should break now that this is Laravel instead of
| rewritten Apache. All permanent (301) redirects, matching the original
| .htaccess's [R=301,L] rules.
*/

// Pre-PHP-conversion .html URLs (still indexed by search engines).
Route::redirect('/index.html', '/', 301);
Route::redirect('/about.html', '/about', 301);
Route::redirect('/committee.html', '/committee', 301);
Route::redirect('/contact.html', '/contact', 301);
Route::redirect('/events.html', '/events', 301);
Route::redirect('/membership.html', '/membership', 301);

// The HTA exhibition page's three prior slugs, oldest first.
Route::redirect('/humanitarian-project-exhibition-2026', '/event/hta-2026', 301);
Route::redirect('/humanitarian-project-exhibition-2026.html', '/event/hta-2026', 301);
Route::redirect('/humanitarian-project-exhibition-2026.php', '/event/hta-2026', 301);
Route::redirect('/hpe-2026', '/event/hta-2026', 301);
Route::redirect('/hpe-2026.php', '/event/hta-2026', 301);
Route::redirect('/event/hpe-2026', '/event/hta-2026', 301);

// The .php filenames every page used to be reachable at directly.
Route::redirect('/index.php', '/', 301);
Route::redirect('/about.php', '/about', 301);
Route::redirect('/committee.php', '/committee', 301);
Route::redirect('/contact.php', '/contact', 301);
Route::redirect('/events.php', '/events', 301);
Route::redirect('/membership.php', '/membership', 301);
Route::redirect('/becithcon-2026.php', '/event/becithcon-2026', 301);
Route::redirect('/hta-2026.php', '/event/hta-2026', 301);
