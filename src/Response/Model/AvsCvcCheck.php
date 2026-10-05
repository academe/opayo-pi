<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Model;

use Academe\Opayo\Pi\Helper;
use Academe\Opayo\Pi\Response\Enums\AvsCvcCheckResult;
use Academe\Opayo\Pi\Response\Enums\AvsCvcCheckStatus;
use JsonSerializable;

/**
 * The results of AVS CVS Checks in a transaction response.
 */

class AvsCvcCheck implements JsonSerializable
{
    // The overall status.

    public const AVSCVCCHECK_STATUS_ALLMATCHED             = 'AllMatched';
    public const AVSCVCCHECK_STATUS_SECURITYCODEMATCHONLY  = 'SecurityCodeMatchOnly';
    public const AVSCVCCHECK_STATUS_ADDRESSMATCHONLY       = 'AddressMatchOnly';
    public const AVSCVCCHECK_STATUS_NOMATCHES              = 'NoMatches';
    public const AVSCVCCHECK_STATUS_NOTCHECKED             = 'NotChecked';

    // These results apply to address, postalCode and securityCode.

    public const AVSCVCCHECK_RESULT_MATCHED        = 'Matched';
    public const AVSCVCCHECK_RESULT_NOTPROVIDED    = 'NotProvided';
    public const AVSCVCCHECK_RESULT_NOTCHECKED     = 'NotChecked';
    public const AVSCVCCHECK_RESULT_NOTMATCHED     = 'NotMatched';

    /**
     * AvsCvcCheck constructor.
     * @param string|null $status The overall check result status
     * @param string|null $address The result of the address check
     * @param string|null $postalCode The result of the postal code check
     * @param string|null $securityCode The result of the security code check
     */
    public function __construct(
        protected readonly ?string $status = null,
        protected readonly ?string $address = null,
        protected readonly ?string $postalCode = null,
        protected readonly ?string $securityCode = null
    ) {
    }

    /**
     * @return string|null The overall check result status
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @return string|null The result of the address check
     */
    public function getAddress(): ?string
    {
        return $this->address;
    }

    /**
     * @return string|null The result of the postal code check
     */
    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    /**
     * @return string|null The result of the security code check
     */
    public function getSecurityCode(): ?string
    {
        return $this->securityCode;
    }

    /**
     * The overall check result as an enum (preferred for new code).
     * Returns null when not set or when the gateway returns a value this
     * package does not know yet; the string getters carry the raw values.
     */
    public function getStatusEnum(): ?AvsCvcCheckStatus
    {
        return AvsCvcCheckStatus::tryFromInsensitive($this->status);
    }

    /**
     * The address check result as an enum (preferred for new code).
     */
    public function getAddressEnum(): ?AvsCvcCheckResult
    {
        return AvsCvcCheckResult::tryFromInsensitive($this->address);
    }

    /**
     * The postal code check result as an enum (preferred for new code).
     */
    public function getPostalCodeEnum(): ?AvsCvcCheckResult
    {
        return AvsCvcCheckResult::tryFromInsensitive($this->postalCode);
    }

    /**
     * The security code check result as an enum (preferred for new code).
     */
    public function getSecurityCodeEnum(): ?AvsCvcCheckResult
    {
        return AvsCvcCheckResult::tryFromInsensitive($this->securityCode);
    }

    /**
     * Construct an instance from raw data.
     * @param array|object|string $data
     * @return static
     */
    public static function fromData(array|object|string $data): static
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
     * @return array<string, array<string, string>>
     */
    public function getData(): array
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
    public function jsonSerialize(): mixed
    {
        return $this->getData();
    }
}
