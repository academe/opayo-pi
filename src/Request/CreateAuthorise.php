<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use UnexpectedValueException;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Request\Enums\ApplyAvsCvcCheck;
use Academe\Opayo\Pi\Security\SensitiveValue;
use Money\Money;

/**
 * An Authorise transaction: take funds against an earlier Authenticate
 * transaction (CreateAuthenticate).
 *
 * Several can be sent against the one Authenticate, for example as parts of
 * an order ship. Opayo allows up to 115% of the authenticated amount in
 * total. Once the Authenticate is used up, or has been cancelled, the gateway
 * refuses with error 1017. The currency is that of the Authenticate
 * transaction.
 */

class CreateAuthorise extends AbstractRequest
{
    use AmountNormaliserTrait;

    protected array $resource_path = ['transactions'];

    protected readonly AmountInterface $amount;

    protected ?string $applyAvsCvcCheck = null;
    protected ?SensitiveValue $cv2 = null;

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string $transactionId The ID of the Authenticate transaction to authorise against
     * @param string $vendorTxCode Your own new reference for this authorisation
     * @param AmountInterface|Money $amount The package's own Amount, or a moneyphp/money Money
     * @param string $description
     * @param array $options Optional: applyAvsCvcCheck, cv2
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        protected readonly string $transactionId,
        protected readonly string $vendorTxCode,
        AmountInterface|Money $amount,
        protected readonly string $description,
        array $options = []
    ) {
        $this->amount = self::normaliseAmount($amount);
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);
        $this->setOptions($options);
    }

    protected function setApplyAvsCvcCheck(string|ApplyAvsCvcCheck $applyAvsCvcCheck): static
    {
        $enum = $applyAvsCvcCheck instanceof ApplyAvsCvcCheck
            ? $applyAvsCvcCheck
            : ApplyAvsCvcCheck::tryFrom($applyAvsCvcCheck);

        if ($enum === null) {
            throw new UnexpectedValueException(sprintf(
                'Unknown applyAvsCvcCheck "%s"; require one of %s',
                $applyAvsCvcCheck,
                implode(', ', array_column(ApplyAvsCvcCheck::cases(), 'value'))
            ));
        }

        $this->applyAvsCvcCheck = $enum->value;
        return $this;
    }

    /**
     * Override the account's AVS/CVC rules for this authorisation.
     */
    public function withApplyAvsCvcCheck(string|ApplyAvsCvcCheck $applyAvsCvcCheck): static
    {
        $copy = clone $this;
        return $copy->setApplyAvsCvcCheck($applyAvsCvcCheck);
    }

    protected function setCv2(string $cv2): static
    {
        $this->cv2 = new SensitiveValue($cv2);
        return $this;
    }

    /**
     * The card security code, if the shopper has supplied it again for this
     * authorisation, so that it can be checked.
     */
    public function withCv2(string $cv2): static
    {
        $copy = clone $this;
        return $copy->setCv2($cv2);
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * Get the message body data for serializing.
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        $result = [
            'transactionType' => static::TRANSACTION_TYPE_AUTHORISE,
            'referenceTransactionId' => $this->transactionId,
            'vendorTxCode' => $this->vendorTxCode,
            'amount' => $this->amount->getAmount(),
            'description' => $this->description,
        ];

        if ($this->applyAvsCvcCheck !== null) {
            $result['applyAvsCvcCheck'] = $this->applyAvsCvcCheck;
        }

        if ($this->cv2 !== null) {
            $result['cv2'] = $this->cv2->peek();
        }

        return $result;
    }
}
