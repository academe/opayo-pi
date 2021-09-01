<?php

namespace Academe\Opayo\Pi\Request;

/**
 * The transaction value object to send a transaction to Sage Pay.
 * See https://test.sagepay.com/documentation/#transactions
 */

use UnexpectedValueException;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Request\Model\PersonInterface;
use Academe\Opayo\Pi\Request\Model\AddressInterface;
use Academe\Opayo\Pi\Request\Model\PaymentMethodInterface;
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;

class CreatePayment extends AbstractRequest
{
    protected $resource_path = ['transactions'];

    protected $transactionType = AbstractRequest::TRANSACTION_TYPE_PAYMENT;

    // Minimum mandatory data (constructor).
    protected $paymentMethod;
    protected $vendorTxCode;
    protected $amount;
    protected $description;
    protected $billingAddress;
    protected $customer;

    // Optional or overridable data.
    protected $entryMethod;
    protected $giftAid = false;
    protected $applyAvsCvcCheck;
    protected $apply3DSecure;
    protected $shippingAddress;
    protected $shippingRecipient;
    protected $referrerId = '3F7A4119-8671-464F-A091-9E59EB47B80C';

    /**
     * @var string The prefix is added to the name fields of the customer.
     */
    protected $customerFieldsPrefix = 'customer';

    /**
     * @var string The prefix is added to the name fields when sending to Sage Pay
     */
    protected $shippingNameFieldPrefix = 'recipient';

    /**
     * @var string The prefix added to address name fields
     */
    protected $shippingAddressFieldPrefix = 'shipping';

    /**
     * @var StrongCustomerAuthentication
     */
    protected $strongCustomerAuthentication;

    /**
     * Valid values for enumerated input types.
     */

    const ENTRY_METHOD_ECOMMERCE                    = 'Ecommerce';
    const ENTRY_METHOD_MAILORDER                    = 'MailOrder';
    const ENTRY_METHOD_TELEPHONEORDER               = 'TelephoneOrder';

    const APPLY_AVS_CVC_CHECK_USEMSPSETTING         = 'UseMSPSetting';
    const APPLY_AVS_CVC_CHECK_FORCE                 = 'Force';
    const APPLY_AVS_CVC_CHECK_DISABLE               = 'Disable';
    const APPLY_AVS_CVC_CHECK_FORCEIGNORINGRULES    = 'ForceIgnoringRules';

    // The numeric values are the Sage Pay Direct equivalents.
    const APPLY_3D_SECURE_USEMSPSETTING             = 'UseMSPSetting'; // 0
    const APPLY_3D_SECURE_FORCE                     = 'Force'; // 1
    const APPLY_3D_SECURE_DISABLE                   = 'Disable'; // 2
    const APPLY_3D_SECURE_FORCEIGNORINGRULES        = 'ForceIgnoringRules'; // 3

    /**
     * Transaction constructor.
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param PaymentMethodInterface $paymentMethod
     * @param string $vendorTxCode
     * @param AmountInterface $amount
     * @param string $description
     * @param AddressInterface $billingAddress
     * @param PersonInterface $customer
     * @param AddressInterface|null $shippingAddress
     * @param PersonInterface|null $shippingRecipient
     * @param array $options Optional transaction options
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        PaymentMethodInterface $paymentMethod,
        $vendorTxCode,
        AmountInterface $amount,
        $description,
        AddressInterface $billingAddress,
        PersonInterface $customer,
        AddressInterface $shippingAddress = null,
        PersonInterface $shippingRecipient = null,
        array $options = []
    ) {
        // Access details.
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);

        $this->setDescription($description);

        // Payment details.
        $this->paymentMethod = $paymentMethod;
        $this->vendorTxCode = $vendorTxCode;
        $this->amount = $amount;

        // Customer details.
        $this->billingAddress = $billingAddress->withFieldPrefix('');
        $this->customer = $customer->withFieldPrefix($this->customerFieldsPrefix);

        // Optional recipient details.
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
     * @param $entryMethod
     * @return $this
     */
    public function setEntryMethod($entryMethod)
    {
        // Get the value from the class constants.
        $value = $this->constantValue('ENTRY_METHOD', $entryMethod);

        if (! $value) {
            throw new UnexpectedValueException(sprintf(
                'Unknown entryMethod "%s"; require one of %s',
                (string)$entryMethod,
                implode(', ', static::getEntryMethods())
            ));
        }

        $this->entryMethod = $value;
        return $this;
    }

    /**
     * @param $entryMethod
     * @return Transaction
     */
    public function withEntryMethod($entryMethod)
    {
        $copy = clone $this;
        return $copy->setEntryMethod($entryMethod);
    }

    /**
     * @return array
     */
    public static function getEntryMethods()
    {
        return static::constantList('ENTRY_METHOD');
    }

    public function setStrongCustomerAuthentication(StrongCustomerAuthentication $strongCustomerAuthentication)
    {
        $this->strongCustomerAuthentication = $strongCustomerAuthentication;
        return $this;
    }

    public function withStrongCustomerAuthentication(StrongCustomerAuthentication $strongCustomerAuthentication)
    {
        $copy = clone $this;
        return $copy->setStrongCustomerAuthentication($strongCustomerAuthentication);
    }

    public function getStrongCustomerAuthentication()
    {
        return $this->strongCustomerAuthentication;
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
     * @param $applyAvsCvcCheck
     * @return $this
     */
    protected function setApplyAvsCvcCheck($applyAvsCvcCheck)
    {
        // Get the value from the class constants.
        $value = $this->constantValue('APPLY_AVS_CVC_CHECK', $applyAvsCvcCheck);

        if (! $value) {
            throw new UnexpectedValueException(sprintf(
                'Unknown applyAvsCvcCheck "%s"; require one of %s',
                (string)$applyAvsCvcCheck,
                implode(', ', static::getApplyAvsCvcChecks())
            ));
        }

        $this->applyAvsCvcCheck = $value;
        return $this;
    }

    /**
     * @param $applyAvsCvcCheck
     * @return Transaction
     */
    public function withApplyAvsCvcCheck($applyAvsCvcCheck)
    {
        $copy = clone $this;
        return $copy->setApplyAvsCvcCheck($applyAvsCvcCheck);
    }

    /**
     * @return array
     */
    public static function getApplyAvsCvcChecks()
    {
        return static::constantList('APPLY_AVS_CVC_CHECK');
    }

    /**
     * @param $apply3DSecure
     * @return $this
     */
    protected function setApply3DSecure($apply3DSecure)
    {
        // Get the value from the class constants.
        $value = $this->constantValue('APPLY_3D_SECURE', $apply3DSecure);

        if (! $value) {
            throw new UnexpectedValueException(sprintf(
                'Unknown apply3DSecure "%s"; require one of %s',
                (string)$apply3DSecure,
                implode(', ', static::getApply3DSecures())
            ));
        }

        $this->apply3DSecure = $value;
        return $this;
    }

    /**
     * @param $apply3DSecure
     * @return Transaction
     */
    public function withApply3DSecure($apply3DSecure)
    {
        $copy = clone $this;
        return $copy->setApply3DSecure($apply3DSecure);
    }

    /**
     * @return array
     */
    public static function getApply3DSecures()
    {
        return static::constantList('APPLY_3D_SECURE');
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
     * @return Transaction
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
     * @return Transaction
     */
    public function withDescription($description)
    {
        $copy = clone $this;
        return $copy->setDescription($description);
    }

    /**
     * @param $referrerId
     * @return $this
     */
    protected function setReferrerId($referrerId)
    {
        $this->referrerId = $referrerId;
        return $this;
    }

    /**
     * @param $referrerId
     * @return Transaction
     */
    public function withReferrerId($referrerId)
    {
        $copy = clone $this;
        return $copy->setReferrerId($referrerId);
    }

    /**
     * Get the message body data for serializing.
     * @return array
     */
    public function jsonSerialize()
    {
        // The mandatory fields.
        // The amount must be cast to an int. Sending an integer as a string will result in
        // a complaint from the remote gateway.

        $result = [
            'transactionType' => $this->transactionType,
            'paymentMethod' => $this->paymentMethod,
            'vendorTxCode' => $this->vendorTxCode,
            'amount' => (int)$this->amount->getAmount(),
            'currency' => $this->amount->getCurrencyCode(),
            'description' => $this->description,
            'billingAddress' => $this->billingAddress,
        ];

        // The customer details.
        // The customer firstname and lastname are mandatory, while the customer
        // email and phone number are optional.
        $result = array_merge($result, $this->customer->jsonSerialize());

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

        // Add remaining optional parameters.

        if (! empty($this->entryMethod)) {
            $result['entryMethod'] = $this->entryMethod;
        }

        if (! empty($this->giftAid)) {
            $result['giftAid'] = $this->giftAid;
        }

        if (! empty($this->applyAvsCvcCheck)) {
            $result['applyAvsCvcCheck'] = $this->applyAvsCvcCheck;
        }

        if (! empty($this->apply3DSecure)) {
            $result['apply3DSecure'] = $this->apply3DSecure;
        }

        if (! empty($this->referrerId)) {
            $result['referrerId'] = $this->referrerId;
        }

        if (! empty($this->strongCustomerAuthentication)) {
            $result['strongCustomerAuthentication'] = $this->strongCustomerAuthentication->jsonSerialize();
        }

        return $result;
    }
}
