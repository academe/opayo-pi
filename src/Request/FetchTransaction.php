<?php

namespace Academe\Opayo\Pi\Request;

/**
 * Request the result of a transaction, stored on Sage Pay servers.
 * See "Retrieve and Transaction" https://test.sagepay.com/documentation/#transactions
 */

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;

class FetchTransaction extends AbstractRequest
{
    protected $resource_path = ['transactions', '{transactionId}'];
    protected $method = 'GET';
    protected $transactionId;

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string $transactionId The ID that Sage Pay gave to the transaction
     */
    public function __construct(Endpoint $endpoint, Auth $auth, $transactionId)
    {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);
        $this->transactionId = $transactionId;
    }

    /**
     * @return string
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * Get the message body data for serializing.
     * There is no body data for this message.
     */
    public function jsonSerialize()
    {
    }
}
