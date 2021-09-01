<?php

namespace Academe\Opayo\Pi\Request;

/**
 * The 3DSecure request sent to Sage Pay, after the user is returned
 * from entering their 3D Secure authentication details.
 * Creates a 3D Secure object and returns the status.
 * See https://test.sagepay.com/documentation/#3-d-secure
 */

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\ServerRequest\Secure3DAcs;

class CreateSecure3D extends AbstractRequest
{
    protected $paRes;
    protected $transactionId;

    protected $resource_path = ['transactions', '{transactionId}', '3d-secure'];

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string|Secure3DAcsResponse $paRes The PA Result returned by the user's bank (or their agent)
     * @param string $transactionId The ID that Sage Pay gave to the transaction in its intial response
     */
    public function __construct(Endpoint $endpoint, Auth $auth, $paRes, $transactionId)
    {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);

        if ($paRes instanceof Secure3DAcs) {
            $this->paRes = $paRes->getPaRes();
        } else {
            $this->paRes = $paRes;
        }

        $this->transactionId = $transactionId;
    }

    /**
     * Get the message body data for serializing.
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'paRes' => $this->getPaRes(),
        ];
    }

    /**
     * @return Secure3DAcsResponse|string
     */
    public function getPaRes()
    {
        return $this->paRes;
    }

    /**
     * Getter used to construct the URL.
     * @return string
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }
}
