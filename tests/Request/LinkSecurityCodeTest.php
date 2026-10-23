<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use PHPUnit\Framework\TestCase;

class LinkSecurityCodeTest extends TestCase
{
    private function request(): LinkSecurityCode
    {
        return new LinkSecurityCode(
            new Endpoint(Endpoint::MODE_TEST),
            new Auth('vendor', 'key', 'password'),
            'SESSION-KEY',
            'CARD-ID',
            '123'
        );
    }

    public function testPostsToTheCardIdentifiersSecurityCode()
    {
        $request = $this->request();

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(
            'https://sandbox.opayo.eu.elavon.com/api/v1/card-identifiers/CARD-ID/security-code',
            (string) $request->getUri()
        );
        $this->assertSame('{"securityCode":"123"}', (string) $request->getBody());
    }

    /**
     * The gateway answers 415 Unsupported Media Type without the JSON
     * content type, and PSR-7 header values are arrays of strings.
     */
    public function testHeadersAuthoriseWithTheSessionKeyAndDeclareJson()
    {
        $headers = $this->request()->getHeaders();

        $this->assertSame(['Bearer SESSION-KEY'], $headers['Authorization']);
        $this->assertSame(['application/json'], $headers['Content-Type']);
    }
}
