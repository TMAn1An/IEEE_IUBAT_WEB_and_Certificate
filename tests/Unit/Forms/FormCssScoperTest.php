<?php

namespace Tests\Unit\Forms;

use App\Services\Forms\FormCssScoper;
use PHPUnit\Framework\TestCase;

class FormCssScoperTest extends TestCase
{
    private function scope(string $css): string
    {
        return (new FormCssScoper)->scope($css, '#ff-form-7');
    }

    public function test_every_selector_is_prefixed_with_the_form_scope(): void
    {
        $this->assertSame('#ff-form-7 .title, #ff-form-7 h3{color: red;}', $this->scope('.title, h3 { color: red; }'));
        $this->assertSame('#ff-form-7 h3:is(.a, .b){color: red;}', $this->scope('h3:is(.a, .b) { color: red }'));
        $this->assertSame('#ff-form-7 input[name="a,b"]{x: 1;}', $this->scope('input[name="a,b"] { x: 1 }'));
    }

    public function test_page_level_selectors_map_to_the_form_wrapper(): void
    {
        $this->assertSame('#ff-form-7{background: red;}', $this->scope('body { background: red }'));
        $this->assertSame('#ff-form-7, #ff-form-7 .x{a:1;}', $this->scope(':root, html body .x { a:1 }'));
        $this->assertSame('#ff-form-7.dark .y{b:2;}', $this->scope('body.dark .y { b:2 }'));
        $this->assertSame('#ff-form-7 > .z{c:3;}', $this->scope('body > .z { c:3 }'));
    }

    public function test_another_forms_id_is_still_nested_inside_this_form(): void
    {
        $this->assertSame('#ff-form-7 #ff-form-8 .q{e:5;}', $this->scope('#ff-form-8 .q { e:5 }'));
        $this->assertSame('#ff-form-7 .q{e:5;}', $this->scope('#ff-form-7 .q { e:5 }'));
    }

    public function test_media_queries_are_scoped_recursively_and_other_at_rules_dropped(): void
    {
        $css = '@import url("https://evil.test/x.css");
@media (max-width: 600px) { .row { display: block } }
@font-face { font-family: x; src: url(x.woff) }
@keyframes spin { from { opacity: 0 } to { opacity: 1 } }';

        $out = $this->scope($css);

        $this->assertStringNotContainsString('@import', $out);
        $this->assertStringNotContainsString('@font-face', $out);
        $this->assertStringContainsString('@media (max-width: 600px){#ff-form-7 .row{display: block;}}', $out);
        $this->assertStringContainsString('@keyframes spin{', $out);
    }

    public function test_nested_rules_cannot_escape_the_scope(): void
    {
        $this->assertSame('#ff-form-7 .a{color: red;}', $this->scope('.a { color: red; .b { color: blue } &:hover { color: pink } }'));
    }

    public function test_style_tag_breakout_and_script_vectors_are_neutralized(): void
    {
        $out = $this->scope('.y::after { content: "</style><script>alert(1)</script>" } .z { background: url(javascript:alert(1)); width: expression(alert(1)); behavior: url(x.htc) }');

        $this->assertStringNotContainsString('<', $out);
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringNotContainsString('expression(', $out);
        $this->assertStringNotContainsString('behavior:', $out);
    }

    public function test_comments_and_blank_input(): void
    {
        $this->assertSame('', $this->scope('   '));
        $this->assertSame('#ff-form-7 p{a:1;}', $this->scope('/* body { x } */ p { a:1 }'));
    }
}
