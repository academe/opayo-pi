<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use UnexpectedValueException;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Request\Enums\Apply3DSecure;
use Academe\Opayo\Pi\Request\Enums\ApplyAvsCvcCheck;
use Academe\Opayo\Pi\Request\Enums\EntryMethod;
use Academe\Opayo\Pi\Request\Model\CredentialType;
use Academe\Opayo\Pi\Request\Model\PersonInterface;
use Academe\Opayo\Pi\Request\Model\AddressInterface;
use Academe\Opayo\Pi\Request\Model\PaymentMethodInterface;
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;
use Money\Money;

/**
 * The transaction value object to send a transaction to Sage Pay.
 * See https://test.sagepay.com/documentation/#transactions
 */

class CreatePayment extends AbstractRequest
{
    use AmountNormaliserTrait;

    protected array $resource_path = ['transactions'];

    protected string $transactionType = AbstractRequest::TRANSACTION_TYPE_PAYMENT;

    // Minimum mandatory data (constructor).
    protected PaymentMethodInterface $paymentMethod;
    protected string $vendorTxCode;
    protected AmountInterface $amount;
    protected string $description;
    protected AddressInterface $billingAddress;
    protected PersonInterface $customer;

    // Optional or overridable data.
    protected ?string $entryMethod = null;
    protected bool $giftAid = false;
    protected ?string $applyAvsCvcCheck = null;
    protected ?string $apply3DSecure = null;
    protected ?AddressInterface $shippingAddress = null;
    protected ?PersonInterface $shippingRecipient = null;
    protected string $referrerId = '3F7A4119-8671-464F-A091-9E59EB47B80C';

    /**
     * The prefix is added to the name fields of the customer.
     */
    protected string $customerFieldsPrefix = 'customer';

    /**
     * The prefix is added to the name fields when sending to Sage Pay
     */
    protected string $shippingNameFieldPrefix = 'recipient';

    /**
     * The prefix added to address name fields
     */
    protected string $shippingAddressFieldPrefix = 'shipping';

    protected ?StrongCustomerAuthentication $strongCustomerAuthentication = null;

    protected ?CredentialType $credentialType = null;

    /**
     * Valid values for enumerated input types.
     */

    public const ENTRY_METHOD_ECOMMERCE                    = 'Ecommerce';
    public const ENTRY_METHOD_MAILORDER                    = 'MailOrder';
    public const ENTRY_METHOD_TELEPHONEORDER               = 'TelephoneOrder';

    public const APPLY_AVS_CVC_CHECK_USEMSPSETTING         = 'UseMSPSetting';
    public const APPLY_AVS_CVC_CHECK_FORCE                 = 'Force';
    public const APPLY_AVS_CVC_CHECK_DISABLE               = 'Disable';
    public const APPLY_AVS_CVC_CHECK_FORCEIGNORINGRULES    = 'ForceIgnoringRules';

    // The numeric values are the Sage Pay Direct equivalents.
    public const APPLY_3D_SECURE_USEMSPSETTING             = 'UseMSPSetting'; // 0
    public const APPLY_3D_SECURE_FORCE                     = 'Force'; // 1
    public const APPLY_3D_SECURE_DISABLE                   = 'Disable'; // 2
    // @deprecated removed from the API spec 2023-10-26
    public const APPLY_3D_SECURE_FORCEIGNORINGRULES        = 'ForceIgnoringRules'; // 3

    /**
     * @param AmountInterface|Money $amount The package's own Amount, or a moneyphp/money Money
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        PaymentMethodInterface $paymentMethod,
        string $vendorTxCode,
        AmountInterface|Money $amount,
        string $description,
        AddressInterface $billingAddress,
        PersonInterface $customer,
        ?AddressInterface $shippingAddress = null,
        ?PersonInterface $shippingRecipient = null,
        array $options = []
    ) {
        // Access details.
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);

        $this->setDescription($description);

        // Payment details.
        $this->paymentMethod = $paymentMethod;
        $this->vendorTxCode = $vendorTxCode;
        $this->amount = self::normaliseAmount($amount);

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

    public function setEntryMethod(string|EntryMethod $entryMethod): static
    {
        if ($entryMethod instanceof EntryMethod) {
            $this->entryMethod = $entryMethod->value;
            return $this;
        }

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

    public function withEntryMethod(string|EntryMethod $entryMethod): static
    {
        $copy = clone $this;
        return $copy->setEntryMethod($entryMethod);
    }

    public static function getEntryMethods(): array
    {
        return static::constantList('ENTRY_METHOD');
    }

    public function setStrongCustomerAuthentication(StrongCustomerAuthentication $strongCustomerAuthentication): static
    {
        $this->strongCustomerAuthentication = $strongCustomerAuthentication;
        return $this;
    }

    public function withStrongCustomerAuthentication(StrongCustomerAuthentication $strongCustomerAuthentication): static
    {
        $copy = clone $this;
        return $copy->setStrongCustomerAuthentication($strongCustomerAuthentication);
    }

    public function getStrongCustomerAuthentication(): ?StrongCustomerAuthentication
    {
        return $this->strongCustomerAuthentication;
    }

    protected function setGiftAid(bool $giftAid): static
    {
        $this->giftAid = ! empty($giftAid);
        return $this;
    }

    public function withGiftAid(bool $giftAid): static
    {
        $copy = clone $this;
        return $copy->setGiftAid($giftAid);
    }

    protected function setApplyAvsCvcCheck(string|ApplyAvsCvcCheck $applyAvsCvcCheck): static
    {
        if ($applyAvsCvcCheck instanceof ApplyAvsCvcCheck) {
            $this->applyAvsCvcCheck = $applyAvsCvcCheck->value;
            return $this;
        }

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

    public function withApplyAvsCvcCheck(string|ApplyAvsCvcCheck $applyAvsCvcCheck): static
    {
        $copy = clone $this;
        return $copy->setApplyAvsCvcCheck($applyAvsCvcCheck);
    }

    public static function getApplyAvsCvcChecks(): array
    {
        return static::constantList('APPLY_AVS_CVC_CHECK');
    }

    protected function setApply3DSecure(string|Apply3DSecure $apply3DSecure): static
    {
        if ($apply3DSecure instanceof Apply3DSecure) {
            $this->apply3DSecure = $apply3DSecure->value;
            return $this;
        }

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

    public function withApply3DSecure(string|Apply3DSecure $apply3DSecure): static
    {
        $copy = clone $this;
        return $copy->setApply3DSecure($apply3DSecure);
    }

    public static function getApply3DSecures(): array
    {
        return static::constantList('APPLY_3D_SECURE');
    }

    public function withShippingAddress(AddressInterface $shippingAddress): static
    {
        $copy = clone $this;
        $copy->shippingAddress = $shippingAddress;
        return $copy;
    }

    public function withShippingRecipient(PersonInterface $shippingRecipient): static
    {
        $copy = clone $this;
        $copy->shippingRecipient = $shippingRecipient;
        return $copy;
    }

    protected function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function withDescription(string $description): static
    {
        $copy = clone $this;
        return $copy->setDescription($description);
    }

    protected function setReferrerId(string $referrerId): static
    {
        $this->referrerId = $referrerId;
        return $this;
    }

    public function withReferrerId(string $referrerId): static
    {
        $copy = clone $this;
        return $copy->setReferrerId($referrerId);
    }

    public function setCredentialType(CredentialType $credentialType): static
    {
        $this->credentialType = $credentialType;
        return $this;
    }

    public function withCredentialType(CredentialType $credentialType): static
    {
        $copy = clone $this;
        return $copy->setCredentialType($credentialType);
    }

    /**
     * Get the message body data for serializing.
     */
    public function jsonSerialize(): mixed
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
        if (! empty($shippingDetails)) {
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

        if (! empty($this->credentialType)) {
            $result['credentialType'] = $this->credentialType->jsonSerialize();
        }

        return $result;
    }
}
