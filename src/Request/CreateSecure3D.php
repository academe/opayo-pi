<?php

declare(strict_types=1);

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
    protected array $resource_path = ['transactions', '{transactionId}', '3d-secure'];
    private readonly string $paRes;

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string|Secure3DAcs $paRes The PA Result returned by the user's bank (or their agent)
     * @param string $transactionId The ID that Sage Pay gave to the transaction in its intial response
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        string|Secure3DAcs $paRes,
        protected readonly string $transactionId
    ) {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);

        $this->paRes = $paRes instanceof Secure3DAcs
            ? $paRes->getPaRes()
            : $paRes;
    }

    /**
     * Get the message body data for serializing.
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return [
            'paRes' => $this->getPaRes(),
        ];
    }

    /**
     * @return string
     */
    public function getPaRes(): string
    {
        return $this->paRes;
    }

    /**
     * Getter used to construct the URL.
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }
}
