<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use Academe\Opayo\Pi\Helper;

/**
 * Response to a Payment request made with the PayPal payment method:
 * status "Redirect", statusCode 2023, "Transaction registered, redirect
 * client to wallet server."
 *
 * Send the shopper's browser to getRedirectUrl() (a full-page redirect, not
 * an iframe). When they are done at PayPal, Opayo redirects them to the
 * callbackUrl given in the request, with the transactionId appended as a
 * query parameter; fetch the transaction (Request\FetchTransaction) to get
 * the final outcome.
 */

class PayPalRedirect extends AbstractTransaction
{
    public const STATUS_CODE_REDIRECT = '2023';

    /**
     * The PayPal URL to redirect the shopper to.
     */
    protected ?string $redirectUrl = null;

    /**
     * The PayPal order ID, for your records.
     */
    protected ?string $orderId = null;

    protected function setData(mixed $data): mixed
    {
        $this->setStatuses($data);

        $this->transactionId = Helper::dataGet($data, 'transactionId');
        $this->transactionType = Helper::dataGet($data, 'transactionType');

        $this->redirectUrl = Helper::dataGet($data, 'paymentMethod.paypal.redirectUrl');
        $this->orderId = Helper::dataGet($data, 'paymentMethod.paypal.orderId');

        return $this;
    }

    /**
     * The PayPal URL to send the shopper to (full page redirect).
     */
    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    /**
     * The PayPal order ID ("token" in the redirect URL).
     */
    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    /**
     * This response is a redirect: the transaction is not complete yet.
     */
    public function isRedirect(): bool
    {
        return true;
    }

    /**
     * Is this data a PayPal redirect response?
     * Used by the ResponseFactory to pick this class.
     */
    public static function isResponse(mixed $data): bool
    {
        return (is_array($data) || is_object($data))
            && (string)Helper::dataGet($data, 'statusCode') === static::STATUS_CODE_REDIRECT
            && Helper::dataGet($data, 'paymentMethod.paypal') !== null;
    }

    /**
     * Serialisation for storage/logging/debug.
     */
    public function jsonSerialize(): mixed
    {
        return [
            'transactionId' => $this->transactionId,
            'transactionType' => $this->transactionType,
            'status' => $this->status,
            'statusCode' => $this->statusCode,
            'statusDetail' => $this->statusDetail,
            'paymentMethod' => [
                'paypal' => [
                    'redirectUrl' => $this->redirectUrl,
                    'orderId' => $this->orderId,
                ],
            ],
        ];
    }
}
