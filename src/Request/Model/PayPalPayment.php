<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use Academe\Opayo\Pi\Helper;

/**
 * PayPal payment method for transactions (paymentMethod.paypal).
 *
 * Wire format (Opayo Pi API reference, paymentMethodObjects/paypal):
 *
 *   merchantSessionKey  required  a merchant session key created for this transaction
 *   callbackUrl         required  where Opayo sends the shopper's browser back to after
 *                                 PayPal, with the Opayo transactionId appended as a
 *                                 query parameter
 *
 * Flow: POST the transaction -> Opayo answers status "Redirect" (statusCode 2023)
 * with paymentMethod.paypal.redirectUrl (see Response\PayPalRedirect) -> send the
 * shopper's browser to it (full page, not an iframe) -> PayPal returns them to
 * Opayo, which redirects to callbackUrl -> fetch the transaction by ID to get the
 * outcome (Request\FetchTransaction).
 *
 * The vendor must have PayPal enabled in MyOpayo (Settings > Pay Methods); the
 * public "sandbox" test vendor has it enabled.
 */

class PayPalPayment implements PaymentMethodInterface
{
    public function __construct(
        protected string $merchantSessionKey,
        protected string $callbackUrl
    ) {
    }

    /**
     * Construct an instance from stored data (e.g. JSON serialized object).
     */
    public static function fromData(array|object|string $data): static
    {
        // For convenience
        if (is_string($data)) {
            $data = json_decode($data);
        }

        // The data will normally be in a "paypal" wrapper element
        if ($paypal = Helper::dataGet($data, 'paypal')) {
            $data = $paypal;
        }

        return new static(
            (string)Helper::dataGet($data, 'merchantSessionKey'),
            (string)Helper::dataGet($data, 'callbackUrl')
        );
    }

    /**
     * Return the complete object data for serialized storage and API submission.
     */
    public function jsonSerialize(): mixed
    {
        return [
            'paypal' => [
                'merchantSessionKey' => $this->merchantSessionKey,
                'callbackUrl' => $this->callbackUrl,
            ],
        ];
    }

    public function getMerchantSessionKey(): string
    {
        return $this->merchantSessionKey;
    }

    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }
}
