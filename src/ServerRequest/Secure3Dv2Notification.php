<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\ServerRequest;

use Academe\Opayo\Pi\Helper;
use Academe\Opayo\Pi\ServerRequest\AbstractServerRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The ACS POST response that the issuing bank's Access Control System (ACS)
 * or their agent sends the user back with.
 * This will include the optional MD for finding the transaction again, and the hashed
 * PaRes result that is then sent to Sage Pay to complete the transaction.
 */

class Secure3Dv2Notification extends AbstractServerRequest
{
    protected ?string $cRes = null;
    protected ?string $threeDSSessionData = null;

    /**
     * Set from payload data.
     */
    protected function setData(mixed $data): mixed
    {
        $this->cRes = Helper::dataGet($data, 'cres', null);
        $this->threeDSSessionData = Helper::dataGet($data, 'threeDSSessionData', null);

        return $this;
    }

    /**
     * Only needed for debugging or logging.
     */
    public function jsonSerialize(): mixed
    {
        return [
            'cRes' => $this->getCRes(),
            'threeDSSessionData' => $this->getThreeDSSessionData(),
        ];
    }

    public function getCRes(): ?string
    {
        return $this->cRes;
    }

    public function getThreeDSSessionData(): ?string
    {
        return $this->threeDSSessionData;
    }

    /**
     * Determine if this message is a valid 3D Secure v2 ACS server request.
     */
    public function isValid(): bool
    {
        // If cRes is set, then this is [likely to be] the user returning from
        // the bank's 3D Secure challenge.

        return ! empty($this->getCRes());
    }

    /**
     * Determine whether this message is active, i.e. has been sent to the application.
     * $data will be $request->getBody() for most implementations.
     */
    public static function isRequest(mixed $data): bool
    {
        return ! empty(Helper::dataGet($data, 'cres'));
    }
}
