<?php

namespace Academe\Opayo\Pi\Request;

/**
 * Send the 3DS v2 cres, returned by the ACS, to Opayo,
 * to get the final transaction result.
 */

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\ServerRequest\Secure3DAcs;
use Academe\Opayo\Pi\ServerRequest\Secure3Dv2Notification;

class CreateSecure3Dv2Challenge extends AbstractRequest
{
    protected $cRes;
    protected $transactionId;

    protected $resource_path = ['transactions', '{transactionId}', '3d-secure-challenge'];

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string|Secure3Dv2Notification $cRes The  Result returned by the user's bank (or their agent)
     * @param string $transactionId The ID that Sage Pay gave to the transaction in its intial response
     */
    public function __construct(Endpoint $endpoint, Auth $auth, $cRes, $transactionId)
    {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);

        if ($cRes instanceof Secure3Dv2Notification) {
            $this->cRes = $cRes->getCRes();
        } else {
            $this->cRes = $cRes;
        }

        $this->transactionId = $transactionId;
    }

    /**
     * Get the message body data for serializing.
     * 
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'cRes' => $this->getCRes(),
        ];
    }

    /**
     * @return Secure3Dv2Notification|string
     */
    public function getCRes()
    {
        return $this->cRes;
    }

    /**
     * Used to construct the URL.
     * 
     * @return string
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }
}
