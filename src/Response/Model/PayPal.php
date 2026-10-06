<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Model;

use Academe\Opayo\Pi\Helper;
use JsonSerializable;

/**
 * The paymentMethod.paypal object of a transaction response.
 *
 * On the initial Redirect response it carries redirectUrl and orderId
 * (see Response\PayPalRedirect); on the completed transaction fetched
 * after the callback it carries the orderId, payerId and captureId.
 */

class PayPal implements JsonSerializable
{
    public function __construct(
        protected readonly ?string $orderId = null,
        protected readonly ?string $redirectUrl = null,
        protected readonly ?string $payerId = null,
        protected readonly ?string $captureId = null
    ) {
    }

    /**
     * Construct from the paymentMethod.paypal data (or a wrapper containing it).
     */
    public static function fromData(array|object|string $data): static
    {
        if (is_string($data)) {
            $data = json_decode($data);
        }

        if ($paypal = Helper::dataGet($data, 'paypal')) {
            $data = $paypal;
        }

        return new static(
            Helper::dataGet($data, 'orderId'),
            Helper::dataGet($data, 'redirectUrl'),
            Helper::dataGet($data, 'payerId'),
            Helper::dataGet($data, 'captureId')
        );
    }

    /**
     * The PayPal order ID ("token" in the redirect URL).
     */
    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    /**
     * The PayPal URL to send the shopper to; only present on the Redirect response.
     */
    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    /**
     * PayPal's ID for the shopper who paid; present once the payment is complete.
     */
    public function getPayerId(): ?string
    {
        return $this->payerId;
    }

    /**
     * PayPal's ID for the capture of funds; present once the payment is
     * complete. This is the reference PayPal's own reports use.
     */
    public function getCaptureId(): ?string
    {
        return $this->captureId;
    }

    public function jsonSerialize(): mixed
    {
        return array_filter([
            'redirectUrl' => $this->redirectUrl,
            'orderId' => $this->orderId,
            'payerId' => $this->payerId,
            'captureId' => $this->captureId,
        ], fn ($v) => $v !== null);
    }
}
