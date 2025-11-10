<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

/**
 * The "release" instruction request.
 * Release a deferred transaction so funds can be collected.
 */

use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;

class CreateRelease extends AbstractInstruction
{
    protected string $instructionType = AbstractRequest::INSTRUCTION_TYPE_RELEASE;

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string $transactionId The ID of the transaction to void
     * @param AmountInterface $amount An amount is required, UP TO the total amount deferred.
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        string $transactionId,
        private readonly AmountInterface $amount
    ) {
        parent::__construct($endpoint, $auth, $transactionId);
    }

    /**
     * Get the message body data for serializing.
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        $body = parent::jsonSerialize();

        $body['amount'] = $this->amount->getAmount();

        return $body;
    }
}
