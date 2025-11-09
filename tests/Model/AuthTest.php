<?php

namespace Academe\Opayo\Pi\Model;

use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function testConstruct()
    {
        $auth = new Auth('vendorName', 'integrationKey', 'integrationPassword');

        $this->assertEquals('vendorName', $auth->getVendorName());
        $this->assertEquals('integrationKey', $auth->getIntegrationKey());
        $this->assertEquals('integrationPassword', $auth->getIntegrationPassword());
    }

    public function testGetVendorName()
    {
        $auth = new Auth('myVendor', 'key123', 'pass456');

        $this->assertEquals('myVendor', $auth->getVendorName());
    }

    public function testSensitiveValuesAreRetrievable()
    {
        $auth = new Auth('vendor', 'secretKey', 'secretPass');

        // Sensitive values should still be retrievable via peek()
        $this->assertEquals('secretKey', $auth->getIntegrationKey());
        $this->assertEquals('secretPass', $auth->getIntegrationPassword());
    }
}
