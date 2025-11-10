<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\ServerRequest;

/**
 * The ACS POST response that the issuing bank's Access Control System (ACS)
 * or their agent sends the user back with.
 * This will include the optional MD for finding the transaction again, and the hashed
 * PaRes result that is then sent to Sage Pay to complete the transaction.
 */

use Academe\Opayo\Pi\Helper;
use Academe\Opayo\Pi\ServerRequest\AbstractServerRequest;

class Secure3DAcs extends AbstractServerRequest
{
    protected ?string $PaRes = null;
    protected ?string $MD = null;

    /**
     * The 3DSecure resource callback from Sage Pay; $_POST will work here.
     */
    protected function setData(mixed $data): mixed
    {
        $this->PaRes = Helper::dataGet($data, 'PaRes', null);
        $this->MD = Helper::dataGet($data, 'MD', null);

        return $this;
    }

    /**
     * Only needed for debugging or logging.
     */
    public function jsonSerialize(): mixed
    {
        return [
            'PaRes' => $this->getPaRes(),
            'MD' => $this->getMD(),
        ];
    }

    /**
     * The optional Merchant Data (MD) to identify the transaction.
     */
    public function getMD(): ?string
    {
        return $this->MD;
    }

    /**
     * The encrypted 3DSecure result (PaRes) to pass on to Sage Pay for validation.
     */
    public function getPaRes(): ?string
    {
        return $this->PaRes;
    }

    /**
     * Determine if this message is a valid 3D Secure ACS server request.
     */
    public function isValid(): bool
    {
        // If paRes is set, then this is [likely to be] the user returning from
        // the bank's 3D Secure password entry.
        return ! empty($this->getPaRes());
    }

    /**
     * Determine whether this message is active, i.e. has been sent to the application.
     * $data will be $request->getBody() for most implementations.
     */
    public static function isRequest(mixed $data): bool
    {
        return ! empty(Helper::dataGet($data, 'PaRes'));
    }
}
