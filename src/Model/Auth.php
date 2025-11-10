<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Model;

/**
 * Value object given the account authentication details.
 * Provides the as needed, and the correct base URL.
 */

use Academe\Opayo\Pi\Security\SensitiveValue;

class Auth
{
    private readonly SensitiveValue $integrationKey;
    private readonly SensitiveValue $integrationPassword;

    /**
     * @param string $vendorName The vendor name supplied by Sage Pay owning the API account
     * @param string $integrationKey The integration key generated for the merchant site
     * @param string $integrationPassword The integration password generated for the merchant site
     */
    public function __construct(
        private readonly string $vendorName,
        string $integrationKey,
        string $integrationPassword
    ) {
        $this->integrationKey = new SensitiveValue($integrationKey);
        $this->integrationPassword = new SensitiveValue($integrationPassword);
    }

    public function getVendorName(): string
    {
        return $this->vendorName;
    }

    public function getIntegrationKey(): ?string
    {
        return $this->integrationKey?->peek();
    }

    public function getIntegrationPassword(): ?string
    {
        return $this->integrationPassword?->peek();
    }
}
