<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Minimal smoke coverage for the public-site migration (Phase 1). Not
 * exhaustive by design — see docs/MIGRATION_PLAN.md for the full URL map
 * this is checking against, and CLAUDE.md for why static-page tests stay
 * light while the certificate system (Phase 2+) gets full coverage.
 */
class PublicSiteTest extends TestCase
{
    /**
     * Every canonical public URL responds and renders content specific to
     * that page (not just a bare 200).
     */
    public function test_every_canonical_page_renders_expected_content(): void
    {
        $cases = [
            '/' => 'Engineering that reaches',
            '/about' => 'Our Student Branch',
            '/committee' => 'Executive Committee',
            '/contact' => 'Contact the branch',
            '/events' => 'All Events',
            '/membership' => 'Membership',
            '/event/becithcon-2026' => 'IEEE BECITHCON 2026',
            '/event/hta-2026' => 'Humanitarian Technology Exhibition',
        ];

        foreach ($cases as $url => $expectedText) {
            $this->get($url)
                ->assertOk()
                ->assertSee($expectedText);
        }
    }

    /**
     * The legacy .html/.php/old-slug URLs from the original site all
     * 301-redirect to their current canonical URL — see
     * docs/MIGRATION_PLAN.md's full redirect table.
     */
    public function test_legacy_urls_redirect_to_canonical_urls(): void
    {
        $cases = [
            '/index.html' => '/',
            '/about.html' => '/about',
            '/about.php' => '/about',
            '/committee.php' => '/committee',
            '/contact.php' => '/contact',
            '/events.php' => '/events',
            '/membership.php' => '/membership',
            '/becithcon-2026.php' => '/event/becithcon-2026',
            '/hta-2026.php' => '/event/hta-2026',
            '/humanitarian-project-exhibition-2026' => '/event/hta-2026',
            '/humanitarian-project-exhibition-2026.html' => '/event/hta-2026',
            '/hpe-2026' => '/event/hta-2026',
            '/hpe-2026.php' => '/event/hta-2026',
            '/event/hpe-2026' => '/event/hta-2026',
        ];

        foreach ($cases as $legacyUrl => $canonicalUrl) {
            $this->get($legacyUrl)
                ->assertRedirect($canonicalUrl)
                ->assertStatus(301);
        }
    }

    /**
     * IEEE brand-lock requirements (CLAUDE.md): the enterprise meta-nav
     * links and the Master Brand logo must be present, unmodified, on
     * every page — checked here on the home page as a representative case.
     */
    public function test_ieee_brand_lock_elements_are_present(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('IEEE.org', false);
        $response->assertSee('IEEE Standards', false);
        $response->assertSee('IEEE Spectrum', false);
        $response->assertSee('alt="IEEE"', false);
    }

    /**
     * Key static assets carried over from the original site (CSS/JS/robots/
     * sitemap) exist at their original public paths, unchanged. These are
     * served directly by the web server, not through Laravel's router, so
     * this checks the files on disk rather than an HTTP response — actual
     * HTTP serving was verified manually against the running Sail server
     * (see the Phase 1 completion report).
     */
    public function test_static_assets_exist_at_their_original_paths(): void
    {
        foreach ([
            'assets/css/style.css',
            'assets/js/main.js',
            'assets/js/particles.js',
            'assets/js/voxel-qr.js',
            'assets/css/voxel-qr.css',
            'robots.txt',
            'sitemap.xml',
            'site.webmanifest',
        ] as $relativePath) {
            $this->assertFileExists(public_path($relativePath));
        }
    }
}
