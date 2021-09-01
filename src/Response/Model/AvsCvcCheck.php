<?php

namespace Academe\Opayo\Pi\Response\Model;

/**
 * The results of AVS CVS Checks in a transaction response.
 */

use Academe\Opayo\Pi\Helper;
use JsonSerializable;

class AvsCvcCheck implements JsonSerializable
{
    // The overall status.

    const AVSCVCCHECK_STATUS_ALLMATCHED             = 'AllMatched';
    const AVSCVCCHECK_STATUS_SECURITYCODEMATCHONLY  = 'SecurityCodeMatchOnly';
    const AVSCVCCHECK_STATUS_ADDRESSMATCHONLY       = 'AddressMatchOnly';
    const AVSCVCCHECK_STATUS_NOMATCHES              = 'NoMatches';
    const AVSCVCCHECK_STATUS_NOTCHECKED             = 'NotChecked';

    // These results apply to address, postalCode and securityCode.

    const AVSCVCCHECK_RESULT_MATCHED        = 'Matched';
    const AVSCVCCHECK_RESULT_NOTPROVIDED    = 'NotProvided';
    const AVSCVCCHECK_RESULT_NOTCHECKED     = 'NotChecked';
    const AVSCVCCHECK_RESULT_NOTMATCHED     = 'NotMatched';

    /**
     * @var string|null The overall check result status.
     */
    protected $status;

    /**
     * @var string|null The result of the address check.
     */
    protected $address;

    /**
     * @var string|null The result of the postal code check.
     */
    protected $postalCode;

    /**
     * @var string|null The result of the security code check.
     */
    protected $securityCode;

    /**
     * AvsCvcCheck constructor.
     * @param string|null $status The overall check result status
     * @param string|null $address The result of the address check
     * @param string|null $postalCode The result of the postal code check
     * @param string|null $securityCode The result of the security code check
     */
    public function __construct(
        $status = null,
        $address = null,
        $postalCode = null,
        $securityCode = null
    ) {
        $this->status = $status;
        $this->address = $address;
        $this->postalCode = $postalCode;
        $this->securityCode = $securityCode;
    }

    /**
     * @return string|null The overall check result status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string|null The result of the address check
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @return string|null The result of the postal code check
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * @return string|null The result of the security code check
     */
    public function getSecurityCode()
    {
        return $this->securityCode;
    }

    /**
     * Construct an instance from raw data.
     * @param array|string $data
     * @return AvsCvcCheck
     */
    public static function fromData($data)
    {
        // For convenience.
        if (is_string($data)) {
            $data = json_decode($data);
        }

        // If the data is inside an "avsCvcCheck" wrapper then
        // remove it to make processing easier.
        if ($insideWrapper = Helper::dataGet($data, 'avsCvcCheck')) {
            $data = $insideWrapper;
        }

        return new static(
            Helper::dataGet($data, 'status'),
            Helper::dataGet($data, 'address'),
            Helper::dataGet($data, 'postalCode'),
            Helper::dataGet($data, 'securityCode')
        );
    }

    /**
     * @return array
     */
    public function getData()
    {
        $avsCvcCheck = [];

        if (isset($this->status)) {
            $avsCvcCheck['status'] = $this->status;
        }

        if (isset($this->address)) {
            $avsCvcCheck['address'] = $this->address;
        }

        if (isset($this->postalCode)) {
            $avsCvcCheck['postalCode'] = $this->postalCode;
        }

        if (isset($this->securityCode)) {
            $avsCvcCheck['securityCode'] = $this->securityCode;
        }

        return ['avsCvcCheck' => $avsCvcCheck];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->getData();
    }
}
