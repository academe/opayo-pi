<?php namespace Academe\Opayo\Pi\ServerRequest;

/**
 * The ACS POST response that the issuing bank’s Access Control System (ACS)
 * or their agent sends the user back with.
 * This will include the optional MD for finding the transaction again, and the hashed
 * PaRes result that is then sent to Sage Pay to complete the transaction.
 */

use Academe\Opayo\Pi\Helper;
use Academe\Opayo\Pi\ServerRequest\AbstractServerRequest;
use Psr\Http\Message\ServerRequestInterface;

class Secure3Dv2Notification extends AbstractServerRequest
{
    protected $cRes;
    protected $threeDSSessionData;

    /**
     * Set from payload data.
     *
     * @param array|object $data
     * @return void
     */
    protected function setData($data)
    {
        $this->cRes = Helper::dataGet($data, 'cres', null);
        $this->threeDSSessionData = Helper::dataGet($data, 'threeDSSessionData', null);
        
        return $this;
    }

    /**
     * Only needed for debugging or logging.
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'cRes' => $this->getCRes(),
            'threeDSSessionData' => $this->getThreeDSSessionData(),
        ];
    }

    public function getCRes()
    {
        return $this->cRes;
    }

    public function getThreeDSSessionData()
    {
        return $this->threeDSSessionData;
    }

    /**
     * Determine if this message is a valid 3D Secure v2 ACS server request.
     * 
     * @return boolean
     */
    public function isValid()
    {
        // If cRes is set, then this is [likely to be] the user returning from
        // the bank's 3D Secure challenge.

        return ! empty($this->getCRes());
    }

    /**
     * Determine whether this message is active, i.e. has been sent to the application.
     * $data will be $request->getBody() for most implementations.
     *
     * @param array|object $data The ServerRequest body data.
     */
    public static function isRequest($data)
    {
        return ! empty(Helper::dataGet($data, 'cres'));
    }
}
