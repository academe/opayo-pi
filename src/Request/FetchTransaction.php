<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

/**
 * Request the result of a transaction, stored on Sage Pay servers.
 * See "Retrieve and Transaction" https://test.sagepay.com/documentation/#transactions
 */

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;

class FetchTransaction extends AbstractRequest
{
    protected array $resource_path = ['transactions', '{transactionId}'];
    protected string $method = 'GET';

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string $transactionId The ID that Sage Pay gave to the transaction
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        protected readonly string $transactionId
    ) {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);
    }

    /**
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * Get the message body data for serializing.
     * There is no body data for this message.
     */
    public function jsonSerialize(): mixed
    {
        return null;
    }
}
