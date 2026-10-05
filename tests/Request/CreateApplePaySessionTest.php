<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use PHPUnit\Framework\TestCase;

class CreateApplePaySessionTest extends TestCase
{
    protected function request(): CreateApplePaySession
    {
        return new CreateApplePaySession(
            new Endpoint(Endpoint::MODE_TEST),
            new Auth('vendor', 'key', 'password'),
            'www.example.com'
        );
    }

    public function testBody()
    {
        // The gateway requires "domainName" (the published OpenAPI spec says "domain",
        // which the sandbox rejects with "Missing mandatory field: domainName").
        $this->assertSame(
            ['vendorName' => 'vendor', 'domainName' => 'www.example.com'],
            $this->request()->jsonSerialize()
        );
    }

    public function testUrlAndMethod()
    {
        $request = $this->request();

        $this->assertSame('https://sandbox.opayo.eu.elavon.com/api/v1/applepay/sessions', $request->getUrl());
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('www.example.com', $request->getDomainName());
    }

    public function testUsesBasicAuthLikeSessionKeys()
    {
        $headers = $this->request()->getHeaders();

        $this->assertSame(['Basic ' . base64_encode('key:password')], $headers['Authorization']);
    }
}
