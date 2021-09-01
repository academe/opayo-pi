<?php

namespace Academe\Opayo\Pi\Request;

/**
 * The transaction value object to send a transaction to Sage Pay.
 * See https://test.sagepay.com/documentation/#transactions
 */

use UnexpectedValueException;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\PaymentMethod\PaymentMethodInterface;
use Academe\Opayo\Pi\Money\AmountInterface;

class CreateDeferred extends CreatePayment
{
    protected $transactionType = AbstractRequest::TRANSACTION_TYPE_DEFERRED;
}
