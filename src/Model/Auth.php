<?php namespace Academe\Opayo\Pi\Model;

/**
 * Value object given the account authentication details.
 * Provides the as needed, and the correct base URL.
 */

use Academe\Opayo\Pi\Security\SensitiveValue;

class Auth
{
    /**
     * @var
     */
    protected $vendorName;

    /**
     * @var SensitiveValue
     */
    protected $integrationKey;

    /**
     * @var SensitiveValue
     */
    protected $integrationPassword;

    /**
     * @param string $vendorName The vendor name supplied by Sage Pay owning the API account
     * @param string $integrationKey The integration key generated for the merchant site
     * @param string $integrationPassword The integration password generated for the merchant site
     */
    public function __construct(
        $vendorName,
        $integrationKey,
        $integrationPassword
    ) {
        $this->vendorName = $vendorName;
        $this->integrationKey = new SensitiveValue($integrationKey);
        $this->integrationPassword = new SensitiveValue($integrationPassword);
    }

    /**
     * @return string The vendor name
     */
    public function getVendorName()
    {
        return $this->vendorName;
    }

    /**
     * @return string The integration key
     */
    public function getIntegrationKey()
    {
        return $this->integrationKey ? $this->integrationKey->peek() : $this->integrationKey;
    }

    /**
     * @return string The integration password
     */
    public function getIntegrationPassword()
    {
        return $this->integrationPassword ? $this->integrationPassword->peek() : $this->integrationPassword;
    }
}
