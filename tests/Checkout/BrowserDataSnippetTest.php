<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

use PHPUnit\Framework\TestCase;

/**
 * The JavaScript snippet and BrowserData must agree on field names, or the
 * server silently falls back to defaults for every shopper.
 */
class BrowserDataSnippetTest extends TestCase
{
    public function testSnippetFillsExactlyTheFieldsBrowserDataReads()
    {
        $js = @file_get_contents(__DIR__ . '/../../resources/js/browser-data.js');

        $this->assertNotFalse($js, 'resources/js/browser-data.js is missing');

        preg_match_all('/^\s*(browser[A-Za-z]+):/m', $js, $matches);

        $this->assertSame(BrowserData::FIELDS, $matches[1]);
    }

    public function testSnippetIsSafeToInlineInAScriptElement()
    {
        // A literal "</script" anywhere, even in a comment, ends an inline
        // <script> element early and spills the rest onto the page.
        $js = file_get_contents(__DIR__ . '/../../resources/js/browser-data.js');

        $this->assertStringNotContainsStringIgnoringCase('</script', $js);
    }
}
