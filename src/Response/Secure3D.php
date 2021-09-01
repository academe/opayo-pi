<?php namespace Academe\Opayo\Pi\Response;

/**
 * The 3D Secure response embedded within a Sage Pay transaction
 * or in response to a Secure3DRequest message.
 * It only includes the status, which gives the final 3D Secure
 * result for the transaction.
 */

use Academe\Opayo\Pi\Helper;
use Psr\Http\Message\ResponseInterface;

class Secure3D extends AbstractResponse
{
    /**
     * List of statuses that the 3DSecure object can return.
     */
    const STATUS3D_AUTHENTICATED        = 'Authenticated';
    const STATUS3D_NOTCHECKED           = 'NotChecked';
    const STATUS3D_NOTAUTHENTICATED     = 'NotAuthenticated';
    const STATUS3D_ERROR                = 'Error';
    const STATUS3D_CARDNOTENROLLED      = 'CardNotEnrolled';
    const STATUS3D_ISSUERNOTENROLLED    = 'IssuerNotEnrolled';
    const STATUS3D_MALFORMEDORINVALID   = 'MalformedOrInvalid';
    const STATUS3D_ATTEMPTONLY          = 'AttemptOnly';
    const STATUS3D_INCOMPLETE           = 'Incomplete';

    /**
     * The 3D Secure status.
     */
    protected $status;

    /**
     * @param $data
     * @return $this
     */
    protected function setData($data)
    {
        $this->status = Helper::dataGet($data, 'status', null);
        return $this;
    }

    /**
     * The 3D Secure status.
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @inheritdoc
     * CHECKME: any other statuses considered sucessful? e.g. is "not checked" a success?
     */
    public function isSuccess()
    {
        return $this->getStatus() == static::STATUS3D_AUTHENTICATED;
    }

    /**
     * Convenient serialisation for logging and debugging.
     * @return array
     */
    public function jsonSerialize()
    {
        $return = [];

        $return['status'] = $this->getStatus();

        return $return;
    }
}
