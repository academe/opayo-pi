<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Money\Money;

/**
 * The "release" instruction request.
 * Release a deferred transaction so funds can be collected.
 */

class CreateRelease extends AbstractInstruction
{
    use AmountNormaliserTrait;

    protected string $instructionType = AbstractRequest::INSTRUCTION_TYPE_RELEASE;

    private readonly AmountInterface $amount;

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string $transactionId The ID of the transaction to release
     * @param AmountInterface|Money $amount An amount is required, UP TO the total amount deferred.
     *        Either the package's own Amount, or a moneyphp/money Money.
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        string $transactionId,
        AmountInterface|Money $amount
    ) {
        $this->amount = self::normaliseAmount($amount);
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
