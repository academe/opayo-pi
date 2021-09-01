<?php

namespace Academe\Opayo\Pi\Request;

/**
 * The request for a session key.
 * See https://test.sagepay.com/documentation/#merchant-session-keys
 */

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;

class CreateSessionKey extends AbstractRequest
{
    protected $resource_path = ['merchant-session-keys'];

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     */
    public function __construct(Endpoint $endpoint, Auth $auth)
    {
        $this->endpoint = $endpoint;
        $this->auth = $auth;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'vendorName' => $this->getAuth()->getVendorName(),
        ];
    }
}
