<?php

namespace Academe\Opayo\Pi\Response;

/**
 * Shared transaction response abstract.
 */

use Academe\Opayo\Pi\Money\CurrencyInterface;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Helper;

abstract class AbstractTransaction extends AbstractResponse
{
    /**
     * Transaction status from Sage Pay.
     */
    const STATUS_OK         = 'Ok';
    const STATUS_NOTAUTHED  = 'NotAuthed';
    const STATUS_REJECTED   = 'Rejected';
    const STATUS_3DAUTH     = '3DAuth';
    const STATUS_MALFORMED  = 'Malformed';
    const STATUS_INVALID    = 'Invalid';
    const STATUS_ERROR      = 'Error';

    /**
     * The status, statusCode and statusReason are used in all transaction responses.
     */
    protected $status;
    protected $statusCode;
    protected $statusDetail;

    protected $transactionId;
    protected $transactionType;

    protected $retrievalReference;
    protected $bankResponseCode;
    protected $bankAuthorisationCode;

    protected $secure3D;
    protected $paymentMethod;

    protected $currency;

    protected $amount;

    protected $avsCvcCheck;

    /**
     * @param $data
     * @return $this
     */
    protected function setData($data)
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
     * @return string The numeric code that represents the status detail.
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * This message in some range of codes can be presented to the end user.
     * In other ranges of codes it should only ever be logged fot the site administrator.
     * @return string The detailed status message.
     */
    public function getStatusDetail()
    {
        return $this->statusDetail;
    }

    /**
     * Set the three status fields from body data.
     * @param array $data The response message body data.
     * @return null
     */
    protected function setStatuses($data)
    {
        $this->status       = Helper::dataGet($data, 'status', null);
        $this->statusCode   = Helper::dataGet($data, 'statusCode', null);
        $this->statusDetail = Helper::dataGet($data, 'statusDetail', null);
    }

    /**
     * Set the currency of the response from the data.
     */
    protected function setCurrency($data)
    {
        if (($currency = Helper::dataGet($data, 'currency')) != null) {
            $this->currency = new Currency($currency);
        }
    }

    protected function setPaymentMethod($data)
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
     *
     */
    protected function set3dSecure($data)
    {
        // Create a 3DSecure object from the array data.
        $this->secure3D = Secure3D::fromData($data);
    }

    protected function setAmount($data, CurrencyInterface $currency = null)
    {
        $this->amount = Model\Amount::fromData($data, $currency);
    }

    protected function setAvsCvcCheck($data)
    {
        $this->avsCvcCheck = Model\AvsCvcCheck::fromData($data);
    }

    /**
     * The ID given to the transaction by Sage Pay.
     * @return mixed
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * The type of the transaction.
     * @return mixed
     */
    public function getTransactionType()
    {
        return $this->transactionType;
    }

    /**
     * The 3D Secure object.
     * @return mixed
     */
    public function get3DSecure()
    {
        return $this->secure3D;
    }

    /**
     * @return Secure3D|null The 3D Secure final status object, if available.
     */
    public function get3DSecureStatus()
    {
        if (isset($this->secure3D)) {
            return $this->secure3D->getStatus();
        }

        return null;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * The Sage Pay docs treat the total/sale/surchage amounts as a single
     * "amount" object. Bizarrely, the object of amounts does *not* include
     * te currency, so it lacks some very important context there.
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Convenience methods dive into the amount object.
     * @return Academe\Opayo\Pi\Money\AmountInterface|null
     */

    public function getTotalAmount()
    {
        if ($amount = $this->getAmount()) {
            return $amount->getTotal();
        }
    }

    public function getSaleAmount()
    {
        if ($amount = $this->getAmount()) {
            return $amount->getSale();
        }
    }

    public function getSurchargeAmount()
    {
        if ($amount = $this->getAmount()) {
            return $amount->getSurcharge();
        }
    }

    /**
     * @return PaymentMethod|null The payment method object, if available.
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * Sage Pay unique Authorisation Code for a successfully authorised
     * transaction. Only present if Status is OK (or Ok).
     * @return mixed
     */
    public function getRetrievalReference()
    {
        return $this->retrievalReference;
    }

    /**
     * Also known as the decline code, these are codes that are
     * specific to the merchant bank.
     * @return mixed
     */
    public function getBankResponseCode()
    {
        return $this->bankResponseCode;
    }

    /**
     * The authorisation code returned from your merchant bank.
     * @return mixed
     */
    public function getBankAuthorisationCode()
    {
        return $this->bankAuthorisationCode;
    }

    /**
     * The AVS CVC Check results.
     * @return Model\AvsCvcCheck|null
     */
    public function getAvsCvcCheck()
    {
        return $this->avsCvcCheck;
    }

    /**
     * Convenient serialisation for logging and debugging.
     * Each response message would extend this where appropriate.
     *
     * @return array
     */
    public function jsonSerialize()
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
