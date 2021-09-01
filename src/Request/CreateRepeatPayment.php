<?php namespace Academe\Opayo\Pi\Request;

/**
 * The repeat payment value object to send a transaction to Sage Pay.
 * See https://test.sagepay.com/documentation/#transactions
 * This does not seem to positively identify a payment as apposed to an
 * authorisation. Sage Pay Direct/Server allows a repeat to be either an
 * authorisation or a payment.
 */

use UnexpectedValueException;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Model\AddressInterface;
use Academe\Opayo\Pi\Model\PersonInterface;

class CreateRepeatPayment extends AbstractRequest
{
    // Supports the URL "api/v1/transactions/<transactionId>"
    protected $resource_path = ['transactions'];

    // Minimum mandatory data (constructor).
    protected $transactionId;
    protected $vendorTxCode;
    protected $amount;
    protected $description;

    // Optional or overridable data.
    protected $shippingAddress;
    protected $shippingRecipient;

    /**
     * @var string The prefix is added to the name fields when sending to Sage Pay
     */
    protected $shippingNameFieldPrefix = 'recipient';

    /**
     * @var string The prefix added to address name fields
     */
    protected $shippingAddressFieldPrefix = 'shipping';

    /**
     * Repeat payment constructor.
     *
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string $transactionId The transacation ID of the original reference payment
     * @param string $vendorTxCode The merchant site vnedor code for the repeat payment
     * @param AmountInterface $amount
     * @param string $description
     * @param AddressInterface|null $shippingAddress
     * @param PersonInterface|null $shippingRecipient
     * @param array $options Optional transaction options
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        $transactionId,
        $vendorTxCode,
        AmountInterface $amount,
        $description,
        Model\AddressInterface $shippingAddress = null,
        Model\PersonInterface $shippingRecipient = null,
        array $options = []
    ) {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);
        $this->setDescription($description);

        $this->setTransactionId($transactionId);
        $this->vendorTxCode = $vendorTxCode;
        $this->amount = $amount;

        if (isset($shippingAddress)) {
            $this->shippingAddress = $shippingAddress->withFieldPrefix($this->shippingAddressFieldPrefix);
        }
        if (isset($shippingRecipient)) {
            $this->shippingRecipient = $shippingRecipient->withFieldPrefix($this->shippingNameFieldPrefix);
        }

        // Additional options.
        $this->setOptions($options);
    }

    /**
     * @param ShippingAddress $shippingAddress
     * @return Transaction
     */
    public function withShippingAddress(ShippingAddress $shippingAddress)
    {
        $copy = clone $this;
        $copy->shippingAddress = $shippingAddress;
        return $copy;
    }

    /**
     * @param ShippingRecipient $shippingRecipient
     * @return Repeat
     */
    public function withShippingRecipient(ShippingRecipient $shippingRecipient)
    {
        $copy = clone $this;
        $copy->shippingRecipient = $shippingRecipient;
        return $copy;
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
     * @param $giftAid
     * @return $this
     */
    protected function setGiftAid($giftAid)
    {
        $this->giftAid = ! empty($giftAid);
        return $this;
    }

    /**
     * @param $giftAid
     * @return Transaction
     */
    public function withGiftAid($giftAid)
    {
        $copy = clone $this;
        return $copy->setGiftAid($giftAid);
    }

    /**
     * Get the message body data for serializing.
     * @return array
     */
    public function jsonSerialize()
    {
        // The mandatory fields.
        $result = [
            'transactionType' => static::TRANSACTION_TYPE_REPEAT,
            'referenceTransactionId' => $this->getTransactionId(),
            'vendorTxCode' => $this->vendorTxCode,
            'amount' => $this->amount->getAmount(),
            'currency' => $this->amount->getCurrencyCode(),
            'description' => $this->getDescription(),
        ];

        $shippingDetails = [];

        if (! empty($this->shippingAddress)) {
            $shippingDetails = array_merge($shippingDetails, $this->shippingAddress->jsonSerialize());
        }

        if (! empty($this->shippingRecipient)) {
            // We only want the names from the recipient details.
            $shippingDetails = array_merge($shippingDetails, $this->shippingRecipient->getNamesBody());
        }

        // If there are shipping details, then merge it in:
        if (! empty($shippingAddress)) {
            $result['shippingDetails'] = $shippingDetails;
        }

        if (! empty($this->giftAid)) {
            $result['giftAid'] = $this->giftAid;
        }

        return $result;
    }
}
