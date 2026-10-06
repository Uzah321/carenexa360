<?php

namespace Tests\Unit;

use App\Support\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_allowed_formatting_is_kept(): void
    {
        $this->assertSame(
            '<p><b>Bold</b> <i>italic</i> <u>under</u></p><ul><li>One</li></ul>',
            RichText::sanitize('<p><b>Bold</b> <i>italic</i> <u>under</u></p><ul><li>One</li></ul>'),
        );
    }

    public function test_scripts_attributes_and_unknown_tags_are_removed(): void
    {
        $this->assertSame(
            '<p>Hello world</p>',
            RichText::sanitize('<p onclick="steal()" class="x">Hello <a href="javascript:alert(1)">world</a><script>alert(1)</script></p>'),
        );
        $this->assertSame('<b>x</b>', RichText::sanitize('<b style="color:red" onmouseover="bad()">x</b><img src=x onerror=alert(1)>'));
    }

    public function test_highlight_spans_become_mark(): void
    {
        $this->assertSame('<mark>key point</mark>', RichText::sanitize('<span style="background-color: rgb(255, 255, 0);">key point</span>'));
    }

    public function test_text_is_escaped_and_utf8_survives(): void
    {
        $this->assertSame('Café &lt;3 &amp; tea — 5°C', RichText::sanitize('Café &lt;3 &amp; tea — 5°C'));
    }

    public function test_empty_markup_is_null(): void
    {
        $this->assertNull(RichText::sanitize(null));
        $this->assertNull(RichText::sanitize('   '));
        $this->assertNull(RichText::sanitize('<div><br></div>'));
    }
}
