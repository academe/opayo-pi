<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use UnexpectedValueException;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Model\AddressInterface;
use Academe\Opayo\Pi\Model\PersonInterface;

/**
 * The refund payment value object to send a transaction to Sage Pay.
 * See https://test.sagepay.com/documentation/#transactions
 */

class CreateRefund extends AbstractRequest
{
    // Supports the URL "api/v1/transactions/<transactionId>"
    protected array $resource_path = ['transactions'];

    // Minimum mandatory data (constructor).
    protected string $transactionId;
    protected string $description;

    /**
     * Repeat payment constructor.
     *
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string $transactionId The reference transaction ID.
     * @param string $vendorTxCode The new merchent site ID for this refund.
     * @param AmountInterface $amount
     * @param string $description
     * @param AddressInterface|null $shippingAddress
     * @param PersonInterface|null $shippingRecipient
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        string $transactionId,
        protected readonly string $vendorTxCode,
        protected readonly AmountInterface $amount,
        string $description
    ) {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);
        $this->setDescription($description);
        $this->setTransactionId($transactionId);
    }

    /**
     * @param string $description
     * @return $this
     */
    protected function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @param string $description
     * @return static
     */
    public function withDescription(string $description): static
    {
        $copy = clone $this;
        return $copy->setDescription($description);
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $transactionId
     * @return $this
     */
    protected function setTransactionId(string $transactionId): static
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    /**
     * @param string $transactionId
     * @return static
     */
    public function withTransactionId(string $transactionId): static
    {
        $copy = clone $this;
        return $copy->setTransactionId($transactionId);
    }

    /**
     * @return string $transactionId
     */
    protected function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * Get the message body data for serializing.
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        // The mandatory fields.
        $result = [
            'transactionType' => static::TRANSACTION_TYPE_REFUND,
            'referenceTransactionId' => $this->getTransactionId(),
            'vendorTxCode' => $this->vendorTxCode,
            'amount' => $this->amount->getAmount(),
            'description' => $this->getDescription(),
        ];

        return $result;
    }
}
