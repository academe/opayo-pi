<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;

/**
 * The request for a session key.
 * See https://test.sagepay.com/documentation/#merchant-session-keys
 */

class CreateSessionKey extends AbstractRequest
{
    protected array $resource_path = ['merchant-session-keys'];

    public function __construct(
        Endpoint $endpoint,
        Auth $auth
    ) {
        $this->endpoint = $endpoint;
        $this->auth = $auth;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'vendorName' => $this->getAuth()->getVendorName(),
        ];
    }
}
