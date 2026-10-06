<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

/**
 * An Authenticate transaction: verify the card and the cardholder (3D Secure)
 * without reserving or taking any funds.
 *
 * Built exactly like a payment. The response status is "Authenticated" when
 * the cardholder passed 3D Secure, or "Registered" when they did not (or it
 * was not performed) and your rules still let the transaction through. A
 * challenge is handled in the same way as for a payment.
 *
 * Nothing is taken until you send one or more CreateAuthorise requests against
 * the transaction. Opayo's guide says to authorise or cancel (CreateCancel)
 * within 90 days.
 */

class CreateAuthenticate extends CreatePayment
{
    protected string $transactionType = AbstractRequest::TRANSACTION_TYPE_AUTHENTICATE;
}
