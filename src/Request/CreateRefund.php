<?php

namespace Academe\Opayo\Pi\Request;

/**
 * The refund payment value object to send a transaction to Sage Pay.
 * See https://test.sagepay.com/documentation/#transactions
 */

use UnexpectedValueException;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Model\AddressInterface;
use Academe\Opayo\Pi\Model\PersonInterface;

class CreateRefund extends AbstractRequest
{
    // Supports the URL "api/v1/transactions/<transactionId>"
    protected $resource_path = ['transactions'];

    // Minimum mandatory data (constructor).
    protected $transactionId;
    protected $vendorTxCode;
    protected $amount;
    protected $description;

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
        $transactionId,
        $vendorTxCode,
        AmountInterface $amount,
        $description
    ) {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);
        $this->setDescription($description);

        $this->setTransactionId($transactionId);

        $this->vendorTxCode = $vendorTxCode;
        $this->amount = $amount;
    }

    /**
     * @param $description
     * @return $this
     */
    protected function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @param $description
     * @return Repeat
     */
    public function withDescription($description)
    {
        $copy = clone $this;
        return $copy->setDescription($description);
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param $transactionId
     * @return $this
     */
    protected function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    /**
     * @param $transactionId
     * @return Repeat
     */
    public function withTransactionId($transactionId)
    {
        $copy = clone $this;
        return $copy->setTransactionId($transactionId);
    }

    /**
     * @return string $transactionId
     */
    protected function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * Get the message body data for serializing.
     * @return array
     */
    public function jsonSerialize()
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
