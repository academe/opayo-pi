<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use Academe\Opayo\Pi\Helper;

/**
 * PayPal payment method for transactions.
 *
 * Note: PayPal integration with Opayo Pi is currently being rolled out.
 * This implementation follows the expected pattern based on other alternative
 * payment methods. The exact API structure may need adjustment when
 * official documentation is released.
 *
 * PayPal tokens are obtained from the PayPal Checkout SDK.
 */

class PayPalPayment implements PaymentMethodInterface
{
    public function __construct(
        protected string $clientIpAddress,
        protected string $paypalOrderId,
        protected ?string $payerId = null
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
            Helper::dataGet($data, 'clientIpAddress'),
            Helper::dataGet($data, 'paypalOrderId') ?? Helper::dataGet($data, 'orderId'),
            Helper::dataGet($data, 'payerId')
        );
    }

    /**
     * Return the complete object data for serialized storage and API submission.
     */
    public function jsonSerialize(): mixed
    {
        $message = [
            'paypal' => [
                'clientIpAddress' => $this->clientIpAddress,
                'paypalOrderId' => $this->paypalOrderId,
            ],
        ];

        if ($this->payerId !== null) {
            $message['paypal']['payerId'] = $this->payerId;
        }

        return $message;
    }

    public function getClientIpAddress(): string
    {
        return $this->clientIpAddress;
    }

    public function getPaypalOrderId(): string
    {
        return $this->paypalOrderId;
    }

    public function getPayerId(): ?string
    {
        return $this->payerId;
    }

    /**
     * Create a new instance with a payer ID.
     */
    public function withPayerId(string $payerId): static
    {
        $clone = clone $this;
        $clone->payerId = $payerId;
        return $clone;
    }
}
