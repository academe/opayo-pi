<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

/**
 * Shared transaction response abstract.
 */

use Academe\Opayo\Pi\Money\CurrencyInterface;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Helper;

abstract class AbstractTransaction extends AbstractResponse
{
    /**
     * Transaction status from Sage Pay.
     */
    public const STATUS_OK         = 'Ok';
    public const STATUS_NOTAUTHED  = 'NotAuthed';
    public const STATUS_REJECTED   = 'Rejected';
    public const STATUS_3DAUTH     = '3DAuth';
    public const STATUS_MALFORMED  = 'Malformed';
    public const STATUS_INVALID    = 'Invalid';
    public const STATUS_ERROR      = 'Error';

    /**
     * The status, statusCode and statusReason are used in all transaction responses.
     */
    protected ?string $status = null;
    protected ?string $statusCode = null;
    protected ?string $statusDetail = null;

    protected ?string $transactionId = null;
    protected ?string $transactionType = null;

    protected ?int $retrievalReference = null;
    protected ?string $bankResponseCode = null;
    protected ?string $bankAuthorisationCode = null;

    protected ?Secure3D $secure3D = null;
    protected ?Model\Card $paymentMethod = null;

    protected ?CurrencyInterface $currency = null;

    protected ?Model\Amount $amount = null;

    protected ?Model\AvsCvcCheck $avsCvcCheck = null;

    /**
     * @param mixed $data
     * @return self
     */
    protected function setData(mixed $data): mixed
    {
        // Note the resource is called "3DSecure" and not "Secure3D" as used
        // for valid class, method and variable names.

        $this->transactionId = Helper::dataGet($data, 'transactionId', null);
        $this->transactionType = Helper::dataGet($data, 'transactionType', null);

        $this->retrievalReference = Helper::dataGet($data, 'retrievalReference', null);
        $this->bankResponseCode = Helper::dataGet($data, 'bankResponseCode', null);
        $this->bankAuthorisationCode = Helper::dataGet($data, 'bankAuthorisationCode', null);

        // Common fields.
        $this->setPaymentMethod($data);
        $this->setStatuses($data);

        // The "3D Secure object" does not include the '3DSecure' container element.
        if ($secure3D = Helper::dataGet($data, '3DSecure')) {
            $this->set3dSecure($secure3D);
        }

        // Set currency on its own first.

        $this->setCurrency($data);

        // Then set the amount, using the currency.

        $this->setAmount($data, $this->getCurrency());

        // Create the "AVS CVC Check Object" if present in the response.

        if ($avsCvcCheck = Helper::dataGet($data, 'avsCvcCheck')) {
            $this->setAvsCvcCheck($avsCvcCheck);
        }

        return $this;
    }

    /**
     * @return string|null The numeric code that represents the status detail.
     */
    public function getStatusCode(): ?string
    {
        return $this->statusCode;
    }

    /**
     * This message in some range of codes can be presented to the end user.
     * In other ranges of codes it should only ever be logged fot the site administrator.
     * @return string|null The detailed status message.
     */
    public function getStatusDetail(): ?string
    {
        return $this->statusDetail;
    }

    /**
     * Set the three status fields from body data.
     * @param mixed $data The response message body data.
     * @return void
     */
    protected function setStatuses(mixed $data): void
    {
        $this->status       = Helper::dataGet($data, 'status', null);
        $this->statusCode   = Helper::dataGet($data, 'statusCode', null);
        $this->statusDetail = Helper::dataGet($data, 'statusDetail', null);
    }

    /**
     * Set the currency of the response from the data.
     * @param mixed $data
     * @return void
     */
    protected function setCurrency(mixed $data): void
    {
        if (($currency = Helper::dataGet($data, 'currency')) != null) {
            $this->currency = new Currency($currency);
        }
    }

    /**
     * @param mixed $data
     * @return void
     */
    protected function setPaymentMethod(mixed $data): void
    {
        $paymentMethod = Helper::dataGet($data, 'paymentMethod');

        if ($paymentMethod) {
            $card = Helper::dataGet($paymentMethod, 'card');

            if ($card) {
                // Create a PaymentMethod object from the array data.
                $this->paymentMethod = Model\Card::fromData($card);
            }
        }
    }

    /**
     * @param mixed $data
     * @return void
     */
    protected function set3dSecure(mixed $data): void
    {
        // Create a 3DSecure object from the array data.
        $this->secure3D = Secure3D::fromData($data);
    }

    /**
     * @param mixed $data
     * @param CurrencyInterface|null $currency
     * @return void
     */
    protected function setAmount(mixed $data, ?CurrencyInterface $currency = null): void
    {
        $this->amount = Model\Amount::fromData($data, $currency);
    }

    /**
     * @param mixed $data
     * @return void
     */
    protected function setAvsCvcCheck(mixed $data): void
    {
        $this->avsCvcCheck = Model\AvsCvcCheck::fromData($data);
    }

    /**
     * The ID given to the transaction by Sage Pay.
     * @return string|null
     */
    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    /**
     * The type of the transaction.
     * @return string|null
     */
    public function getTransactionType(): ?string
    {
        return $this->transactionType;
    }

    /**
     * The 3D Secure object.
     * @return Secure3D|null
     */
    public function get3DSecure(): ?Secure3D
    {
        return $this->secure3D;
    }

    /**
     * @return string|null The 3D Secure final status, if available.
     */
    public function get3DSecureStatus(): ?string
    {
        if (isset($this->secure3D)) {
            return $this->secure3D->getStatus();
        }

        return null;
    }

    /**
     * @return CurrencyInterface|null
     */
    public function getCurrency(): ?CurrencyInterface
    {
        return $this->currency;
    }

    /**
     * The Sage Pay docs treat the total/sale/surchage amounts as a single
     * "amount" object. Bizarrely, the object of amounts does *not* include
     * te currency, so it lacks some very important context there.
     * @return Model\Amount|null
     */
    public function getAmount(): ?Model\Amount
    {
        return $this->amount;
    }

    /**
     * Convenience methods dive into the amount object.
     * @return AmountInterface|null
     */
    public function getTotalAmount(): ?AmountInterface
    {
        if ($amount = $this->getAmount()) {
            return $amount->getTotal();
        }

        return null;
    }

    /**
     * @return AmountInterface|null
     */
    public function getSaleAmount(): ?AmountInterface
    {
        if ($amount = $this->getAmount()) {
            return $amount->getSale();
        }

        return null;
    }

    /**
     * @return AmountInterface|null
     */
    public function getSurchargeAmount(): ?AmountInterface
    {
        if ($amount = $this->getAmount()) {
            return $amount->getSurcharge();
        }

        return null;
    }

    /**
     * @return Model\Card|null The payment method object, if available.
     */
    public function getPaymentMethod(): ?Model\Card
    {
        return $this->paymentMethod;
    }

    /**
     * Sage Pay unique Authorisation Code for a successfully authorised
     * transaction. Only present if Status is OK (or Ok).
     * @return int|null
     */
    public function getRetrievalReference(): ?int
    {
        return $this->retrievalReference;
    }

    /**
     * Also known as the decline code, these are codes that are
     * specific to the merchant bank.
     * @return string|null
     */
    public function getBankResponseCode(): ?string
    {
        return $this->bankResponseCode;
    }

    /**
     * The authorisation code returned from your merchant bank.
     * @return string|null
     */
    public function getBankAuthorisationCode(): ?string
    {
        return $this->bankAuthorisationCode;
    }

    /**
     * The AVS CVC Check results.
     * @return Model\AvsCvcCheck|null
     */
    public function getAvsCvcCheck(): ?Model\AvsCvcCheck
    {
        return $this->avsCvcCheck;
    }

    /**
     * Convenient serialisation for logging and debugging.
     * Each response message would extend this where appropriate.
     *
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        $return = [];

        // Transaction context.
        $return['transactionId'] = $this->transactionId;
        $return['transactionType'] = $this->transactionType;

        // Status details.
        $return['httpCode'] = $this->getHttpCode();
        $return['status'] = $this->getStatus();
        $return['statusCode'] = $this->getStatusCode();
        $return['statusDetail'] = $this->getStatusDetail();

        if (($retrievalReference = $this->getRetrievalReference()) !== null) {
            $return['retrievalReference'] = $retrievalReference;
        }

        if (($bankResponseCode = $this->getBankResponseCode()) !== null) {
            $return['bankResponseCode'] = $bankResponseCode;
        }

        if (($bankAuthorisationCode = $this->getBankAuthorisationCode()) !== null) {
            $return['bankAuthorisationCode'] = $bankAuthorisationCode;
        }

        if ($paymentMethod = $this->getPaymentMethod()) {
            $return['paymentMethod'] = $paymentMethod;
        }

        if ($secure3D = $this->get3DSecure()) {
            $return['3DSecure'] = $secure3D;
        }

        if ($amount = $this->getAmount()) {
            // Merge in the "amount object" at the top level.
            $return = array_merge($return, $amount->getData());
        }

        if ($currency = $this->getCurrency()) {
            $return['currency'] = $currency->getCode();
        }

        if ($avsCvcCheck = $this->getAvsCvcCheck()) {
            // Merge in the "AVS CVC Check object" at the top level.
            $return = array_merge($return, $avsCvcCheck->getData());
        }

        return $return;
    }
}
