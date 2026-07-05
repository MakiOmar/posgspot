<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\StorefrontHtmlSanitizer;
use Tests\TestCase;

class StorefrontHtmlSanitizerTest extends TestCase
{
    public function test_strips_script_tags_and_event_handlers(): void
    {
        $sanitizer = new StorefrontHtmlSanitizer();
        $dirty = '<p>Hello</p><script>alert(1)</script><img src=x onerror="alert(1)"><a href="javascript:alert(1)">x</a>';

        $clean = $sanitizer->sanitize($dirty);

        $this->assertStringContainsString('<p>Hello</p>', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }
}
