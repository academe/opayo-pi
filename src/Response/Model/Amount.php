<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Model;

use Academe\Opayo\Pi\Money\Amount as AmountValue;
use Academe\Opayo\Pi\Money\CurrencyInterface;
use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Helper;
use JsonSerializable;

/**
 * Amount in a transaction response.
 * This is split into multiple elements: totalAmount, saleAmount and surchargeAmount.
 */

class Amount implements JsonSerializable
{
    /**
     * Amount constructor.
     * The currency will be known for these amounts at this point.
     *
     * @param AmountInterface|null $total
     * @param AmountInterface|null $sale
     * @param AmountInterface|null $surcharge
     */
    public function __construct(
        protected readonly ?AmountInterface $total = null,
        protected readonly ?AmountInterface $sale = null,
        protected readonly ?AmountInterface $surcharge = null
    ) {
    }

    /**
     * Extract the amount from the raw message data.
     * The amount object inludes the "amount" wrapper element, so this
     * will extract from an entire message.
     * If the currency is not passed in separately, then a "currency"
     * element will be expected.
     *
     * @param array|object|string $data
     * @param CurrencyInterface|null $currency
     * @return static
     */
    public static function fromData(array|object|string $data, ?CurrencyInterface $currency = null): static
    {
        // For convenience.
        if (is_string($data)) {
            $data = json_decode($data);
        }

        // If a currency is not passed in, then get it from the data.

        if (empty($currency)) {
            if (($currency = Helper::dataGet($data, 'currency')) != null) {
                $currency = new Currency($currency);
            }
        }

        // If the amount parts are in an "amount" wrapper then
        // move them up a level for convenience.

        if (Helper::dataGet($data, 'amount')) {
            $data = Helper::dataGet($data, 'amount');
        }

        if (($totalAmount = Helper::dataGet($data, 'totalAmount')) !== null) {
            $totalAmount = new AmountValue($currency, $totalAmount);
        }

        if (($saleAmount = Helper::dataGet($data, 'saleAmount')) !== null) {
            $saleAmount = new AmountValue($currency, $saleAmount);
        }

        if (($surchargeAmount = Helper::dataGet($data, 'surchargeAmount')) !== null) {
            $surchargeAmount = new AmountValue($currency, $surchargeAmount);
        }

        return new static(
            $totalAmount,
            $saleAmount,
            $surchargeAmount
        );
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function getData(): array
    {
        $amount = [];

        if (isset($this->total)) {
            $amount['totalAmount'] = $this->total->getAmount();
        }

        if (isset($this->sale)) {
            $amount['saleAmount'] = $this->sale->getAmount();
        }

        if (isset($this->surcharge)) {
            $amount['surchargeAmount'] = $this->surcharge->getAmount();
        }

        return ['amount' => $amount];
    }

    /**
     * Serialisation for storage/logging/debug.
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return $this->getData();
    }

    public function getTotal(): ?AmountInterface
    {
        return $this->total;
    }

    public function getSale(): ?AmountInterface
    {
        return $this->sale;
    }

    public function getSurcharge(): ?AmountInterface
    {
        return $this->surcharge;
    }
}
