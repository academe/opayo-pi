<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use Academe\Opayo\Pi\Helper;
use Psr\Http\Message\ResponseInterface;

/**
 * The 3D Secure response embedded within a Sage Pay transaction
 * or in response to a Secure3DRequest message.
 * It only includes the status, which gives the final 3D Secure
 * result for the transaction.
 */

class Secure3D extends AbstractResponse
{
    /**
     * List of statuses that the 3DSecure object can return.
     */
    public const STATUS3D_AUTHENTICATED        = 'Authenticated';
    public const STATUS3D_NOTCHECKED           = 'NotChecked';
    public const STATUS3D_NOTAUTHENTICATED     = 'NotAuthenticated';
    public const STATUS3D_ERROR                = 'Error';
    public const STATUS3D_CARDNOTENROLLED      = 'CardNotEnrolled';
    public const STATUS3D_ISSUERNOTENROLLED    = 'IssuerNotEnrolled';
    public const STATUS3D_MALFORMEDORINVALID   = 'MalformedOrInvalid';
    public const STATUS3D_ATTEMPTONLY          = 'AttemptOnly';
    public const STATUS3D_INCOMPLETE           = 'Incomplete';

    /**
     * The 3D Secure status.
     */
    protected ?string $status = null;

    /**
     * @param mixed $data
     * @return mixed
     */
    protected function setData(mixed $data): mixed
    {
        $this->status = Helper::dataGet($data, 'status', null);
        return $this;
    }

    /**
     * The 3D Secure status.
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @inheritdoc
     * CHECKME: any other statuses considered sucessful? e.g. is "not checked" a success?
     */
    public function isSuccess(): bool
    {
        return $this->getStatus() == static::STATUS3D_AUTHENTICATED;
    }

    /**
     * Convenient serialisation for logging and debugging.
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        $return = [];

        $return['status'] = $this->getStatus();

        return $return;
    }
}
