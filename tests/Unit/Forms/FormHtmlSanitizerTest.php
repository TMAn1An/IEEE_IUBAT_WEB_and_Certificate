<?php

namespace Tests\Unit\Forms;

use App\Services\Forms\FormHtmlSanitizer;
use PHPUnit\Framework\TestCase;

class FormHtmlSanitizerTest extends TestCase
{
    private function clean(string $html): string
    {
        return (new FormHtmlSanitizer)->sanitize($html);
    }

    public function test_safe_formatting_survives(): void
    {
        $html = '<h3>Important Notice</h3><p>Please fill this form <strong>carefully</strong>.</p><ul><li>One</li></ul>';

        $this->assertSame($html, $this->clean($html));
    }

    public function test_scripts_iframes_and_forms_are_removed_with_their_content(): void
    {
        $out = $this->clean('<p>ok</p><script>alert(1)</script><iframe src="https://x.test"></iframe><form><input name=a></form><svg><script>alert(2)</script></svg>');

        $this->assertSame('<p>ok</p>', $out);
    }

    public function test_event_handlers_and_style_attributes_are_stripped(): void
    {
        $out = $this->clean('<p onclick="alert(1)" style="position:fixed;inset:0" class="note">x</p><img src="https://x.test/a.png" onerror="alert(2)" alt="a">');

        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringNotContainsString('style=', $out);
        $this->assertStringContainsString('class="note"', $out);
        $this->assertStringContainsString('src="https://x.test/a.png"', $out);
    }

    public function test_dangerous_url_schemes_are_removed(): void
    {
        $out = $this->clean('<a href="javascript:alert(1)">a</a><a href="JaVaScRiPt:alert(1)">b</a><img src="data:image/svg+xml;base64,AAA"><a href="https://ieee.org" target="_blank">ok</a>');

        $this->assertStringNotContainsString('javascript', strtolower($out));
        $this->assertStringNotContainsString('data:', $out);
        $this->assertStringContainsString('href="https://ieee.org"', $out);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $out);
    }

    public function test_unknown_tags_are_unwrapped(): void
    {
        $this->assertSame('kept text', $this->clean('<custom-el>kept text</custom-el>'));
        $this->assertSame('', $this->clean('   '));
    }
}
