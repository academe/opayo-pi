<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\GooglePay;

use Academe\Opayo\Pi\Money\AmountInterface;
use Academe\Opayo\Pi\Money\Currency;
use InvalidArgumentException;

/**
 * Builds the request objects the Google Pay JavaScript API expects, with the
 * Opayo-specific parts filled in.
 *
 * Opayo's Google Pay guide hands you ~250 lines of JavaScript, of which three
 * lines are actually about Opayo: the gateway name, the gatewayMerchantId, and
 * the Google merchant ID. Everything else is identical for every merchant, so
 * it is built here once instead of being copied into every integration.
 *
 * Typical use - hand the whole thing to the page and let the browser glue it
 * to google.payments.api.PaymentsClient:
 *
 *   $config = new Configuration(
 *       gatewayMerchantId: $gatewayMerchantId,   // MyOpayo > Settings > Pay Methods
 *       merchantName: 'Widgets Ltd',
 *       googleMerchantId: $googleMerchantId,     // Google Pay & Wallet Console
 *       environment: Environment::Test,
 *   );
 *
 *   <script>const opayoGooglePay = <?= json_encode($config->clientConfiguration($amount)) ?>;</script>
 *
 * The token the sheet returns goes to GooglePayPayment::fromGoogleToken(),
 * which base64-encodes it for paymentMethod.googlePay.
 *
 * @see https://developers.google.com/pay/api/web/reference/request-objects
 */
class Configuration
{
    /**
     * Opayo's gateway identifier in the Google Pay tokenizationSpecification.
     * Fixed for every Opayo merchant.
     */
    public const GATEWAY = 'opayoelavon';

    public const API_VERSION = 2;
    public const API_VERSION_MINOR = 0;

    /**
     * PAN_ONLY is a card saved to the Google account; CRYPTOGRAM_3DS is one
     * provisioned to the device, carrying a network token and a cryptogram.
     * Opayo's guide recommends accepting both.
     */
    public const DEFAULT_AUTH_METHODS = ['PAN_ONLY', 'CRYPTOGRAM_3DS'];

    /** The networks listed in Opayo's own Google Pay example. */
    public const DEFAULT_CARD_NETWORKS = ['AMEX', 'DISCOVER', 'INTERAC', 'JCB', 'MASTERCARD', 'VISA'];

    /**
     * Google's totalPriceStatus. Opayo transactions are for a known amount, so
     * FINAL is the normal choice; ESTIMATED is for a total that may still move.
     */
    public const PRICE_STATUS_FINAL = 'FINAL';
    public const PRICE_STATUS_ESTIMATED = 'ESTIMATED';

    /**
     * @param string $gatewayMerchantId Issued by Opayo when Google Pay is enabled on
     *      the vendor. An identifier, not a secret: it belongs in your page source.
     * @param string $merchantName Shown to the shopper on the Google Pay sheet.
     * @param string|null $googleMerchantId From the Google Pay & Wallet Console.
     *      Required for Environment::Production, ignored for Test.
     * @param string $countryCode ISO 3166-1 alpha-2 country of the merchant.
     * @param string[] $allowedAuthMethods
     * @param string[] $allowedCardNetworks
     */
    public function __construct(
        protected readonly string $gatewayMerchantId,
        protected readonly string $merchantName,
        protected readonly ?string $googleMerchantId = null,
        protected readonly Environment $environment = Environment::Test,
        protected readonly string $countryCode = 'GB',
        protected readonly array $allowedAuthMethods = self::DEFAULT_AUTH_METHODS,
        protected readonly array $allowedCardNetworks = self::DEFAULT_CARD_NETWORKS,
    ) {
        if ($gatewayMerchantId === '') {
            throw new InvalidArgumentException(
                'A gatewayMerchantId is required. Opayo shows it in MyOpayo under '
                . 'Settings > Pay Methods > Google Pay, once the wallet is enabled on the vendor.'
            );
        }

        if ($merchantName === '') {
            throw new InvalidArgumentException('A merchantName is required; the shopper sees it on the Google Pay sheet.');
        }

        if ($environment->requiresGoogleMerchantId() && ($googleMerchantId === null || $googleMerchantId === '')) {
            throw new InvalidArgumentException(
                'The PRODUCTION environment needs a Google merchant ID from the Google Pay & Wallet Console. '
                . 'Use Environment::Test while you have not got one.'
            );
        }

        if ($allowedAuthMethods === []) {
            throw new InvalidArgumentException('At least one allowedAuthMethod is required.');
        }

        if ($allowedCardNetworks === []) {
            throw new InvalidArgumentException('At least one allowedCardNetwork is required.');
        }

        if (! preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new InvalidArgumentException(
                sprintf('The countryCode must be two upper-case letters, got "%s".', $countryCode)
            );
        }
    }

    /**
     * Everything the browser needs, in one object to json_encode into the page:
     * the environment for the PaymentsClient constructor, and both request objects.
     *
     * @return array{environment: string, isReadyToPayRequest: array, paymentDataRequest: array}
     */
    public function clientConfiguration(
        AmountInterface $amount,
        string $totalPriceStatus = self::PRICE_STATUS_FINAL
    ): array {
        return [
            'environment' => $this->environment->value,
            'isReadyToPayRequest' => $this->isReadyToPayRequest(),
            'paymentDataRequest' => $this->paymentDataRequest($amount, $totalPriceStatus),
        ];
    }

    /**
     * The isReadyToPay request: asks whether this browser can pay at all.
     * Deliberately carries no tokenizationSpecification - Google's API only
     * wants the base card payment method here.
     */
    public function isReadyToPayRequest(): array
    {
        return $this->baseRequest() + [
            'allowedPaymentMethods' => [$this->baseCardPaymentMethod()],
        ];
    }

    /**
     * The loadPaymentData request: opens the sheet and produces the token.
     */
    public function paymentDataRequest(
        AmountInterface $amount,
        string $totalPriceStatus = self::PRICE_STATUS_FINAL
    ): array {
        return $this->baseRequest() + [
            'allowedPaymentMethods' => [$this->cardPaymentMethod()],
            'merchantInfo' => $this->merchantInfo(),
            'transactionInfo' => $this->transactionInfo($amount, $totalPriceStatus),
        ];
    }

    /**
     * Where the token is addressed: type PAYMENT_GATEWAY means Google encrypts
     * it to Elavon's key, so no key material is ever issued to the merchant.
     */
    public function tokenizationSpecification(): array
    {
        return [
            'type' => 'PAYMENT_GATEWAY',
            'parameters' => [
                'gateway' => self::GATEWAY,
                'gatewayMerchantId' => $this->gatewayMerchantId,
            ],
        ];
    }

    public function merchantInfo(): array
    {
        $merchantInfo = ['merchantName' => $this->merchantName];

        // Google only reads merchantId in PRODUCTION; sending it in TEST is noise.
        if ($this->environment->requiresGoogleMerchantId()) {
            $merchantInfo['merchantId'] = $this->googleMerchantId;
        }

        return $merchantInfo;
    }

    /**
     * Google wants the total as a decimal string in major units, alongside the
     * currency it is in. The Amount holds minor units, so convert exactly -
     * no floats, which would round the odd total wrongly.
     */
    public function transactionInfo(
        AmountInterface $amount,
        string $totalPriceStatus = self::PRICE_STATUS_FINAL
    ): array {
        return [
            'countryCode' => $this->countryCode,
            'currencyCode' => $amount->getCurrencyCode(),
            'totalPriceStatus' => $totalPriceStatus,
            'totalPrice' => static::formatMajorUnits($amount),
        ];
    }

    /**
     * Minor units to the decimal string Google expects, e.g. 999 GBP -> "9.99",
     * 1000 JPY -> "1000" (no minor unit).
     */
    public static function formatMajorUnits(AmountInterface $amount): string
    {
        // AmountInterface carries only the code, so resolve the currency to
        // find out how many minor-unit digits it has.
        $digits = (new Currency($amount->getCurrencyCode()))->getDigits();
        $minorUnits = $amount->getAmount();

        if ($digits === 0) {
            return (string)$minorUnits;
        }

        $sign = $minorUnits < 0 ? '-' : '';
        $absolute = abs($minorUnits);
        $divisor = 10 ** $digits;

        return $sign . intdiv($absolute, $divisor)
            . '.' . str_pad((string)($absolute % $divisor), $digits, '0', STR_PAD_LEFT);
    }

    protected function baseRequest(): array
    {
        return [
            'apiVersion' => self::API_VERSION,
            'apiVersionMinor' => self::API_VERSION_MINOR,
        ];
    }

    protected function baseCardPaymentMethod(): array
    {
        return [
            'type' => 'CARD',
            'parameters' => [
                'allowedAuthMethods' => array_values($this->allowedAuthMethods),
                'allowedCardNetworks' => array_values($this->allowedCardNetworks),
            ],
        ];
    }

    protected function cardPaymentMethod(): array
    {
        return $this->baseCardPaymentMethod() + [
            'tokenizationSpecification' => $this->tokenizationSpecification(),
        ];
    }

    public function getGatewayMerchantId(): string
    {
        return $this->gatewayMerchantId;
    }

    public function getGoogleMerchantId(): ?string
    {
        return $this->googleMerchantId;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }
}
