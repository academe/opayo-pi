<?php

namespace Academe\Opayo\Pi\GooglePay;

use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The Google Pay JS API request objects, built with the Opayo parts filled in.
 * Shapes are from Google's request-objects reference and Opayo's own example.
 */
class ConfigurationTest extends TestCase
{
    protected string $gatewayMerchantId = 'opayo-gateway-merchant-1234';
    protected string $googleMerchantId = 'BCR2DN4T2ZLMNOPQ';
    protected string $merchantName = 'Widgets Ltd';

    protected function config(
        Environment $environment = Environment::Test,
        ?string $googleMerchantId = null
    ): Configuration {
        return new Configuration(
            gatewayMerchantId: $this->gatewayMerchantId,
            merchantName: $this->merchantName,
            googleMerchantId: $googleMerchantId,
            environment: $environment,
        );
    }

    protected function amount(string $major = '9.99', string $currency = 'GBP'): Amount
    {
        return (new Amount(new Currency($currency), 0))->withMajorUnit($major);
    }

    // -----------------------------------------------------------------------
    // tokenizationSpecification: the part that makes it an Opayo integration
    // -----------------------------------------------------------------------

    public function testTokenizationSpecificationNamesTheOpayoGateway()
    {
        $this->assertSame([
            'type' => 'PAYMENT_GATEWAY',
            'parameters' => [
                'gateway' => 'opayoelavon',
                'gatewayMerchantId' => $this->gatewayMerchantId,
            ],
        ], $this->config()->tokenizationSpecification());
    }

    // -----------------------------------------------------------------------
    // isReadyToPayRequest
    // -----------------------------------------------------------------------

    public function testIsReadyToPayRequestShape()
    {
        $request = $this->config()->isReadyToPayRequest();

        $this->assertSame(2, $request['apiVersion']);
        $this->assertSame(0, $request['apiVersionMinor']);
        $this->assertCount(1, $request['allowedPaymentMethods']);
        $this->assertSame('CARD', $request['allowedPaymentMethods'][0]['type']);
        $this->assertSame(
            ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
            $request['allowedPaymentMethods'][0]['parameters']['allowedAuthMethods']
        );
    }

    /**
     * Google rejects a tokenizationSpecification on the isReadyToPay request.
     */
    public function testIsReadyToPayRequestCarriesNoTokenizationSpecification()
    {
        $request = $this->config()->isReadyToPayRequest();

        $this->assertArrayNotHasKey('tokenizationSpecification', $request['allowedPaymentMethods'][0]);
    }

    // -----------------------------------------------------------------------
    // paymentDataRequest
    // -----------------------------------------------------------------------

    public function testPaymentDataRequestCarriesTheTokenizationSpecification()
    {
        $request = $this->config()->paymentDataRequest($this->amount());

        $this->assertSame(
            $this->config()->tokenizationSpecification(),
            $request['allowedPaymentMethods'][0]['tokenizationSpecification']
        );
    }

    public function testPaymentDataRequestTransactionInfo()
    {
        $request = $this->config()->paymentDataRequest($this->amount('12.34'));

        $this->assertSame([
            'countryCode' => 'GB',
            'currencyCode' => 'GBP',
            'totalPriceStatus' => 'FINAL',
            'totalPrice' => '12.34',
        ], $request['transactionInfo']);
    }

    public function testTotalPriceStatusCanBeEstimated()
    {
        $request = $this->config()->paymentDataRequest(
            $this->amount(),
            Configuration::PRICE_STATUS_ESTIMATED
        );

        $this->assertSame('ESTIMATED', $request['transactionInfo']['totalPriceStatus']);
    }

    // -----------------------------------------------------------------------
    // merchantInfo: the Google merchant ID is only read in PRODUCTION
    // -----------------------------------------------------------------------

    public function testTestEnvironmentOmitsTheGoogleMerchantId()
    {
        $merchantInfo = $this->config(Environment::Test, $this->googleMerchantId)->merchantInfo();

        $this->assertSame(['merchantName' => $this->merchantName], $merchantInfo);
    }

    public function testProductionEnvironmentIncludesTheGoogleMerchantId()
    {
        $merchantInfo = $this->config(Environment::Production, $this->googleMerchantId)->merchantInfo();

        $this->assertSame([
            'merchantName' => $this->merchantName,
            'merchantId' => $this->googleMerchantId,
        ], $merchantInfo);
    }

    public function testProductionWithoutAGoogleMerchantIdIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Google Pay & Wallet Console/');

        $this->config(Environment::Production);
    }

    // -----------------------------------------------------------------------
    // clientConfiguration: the single blob the page needs
    // -----------------------------------------------------------------------

    public function testClientConfigurationCarriesEverythingTheBrowserNeeds()
    {
        $client = $this->config()->clientConfiguration($this->amount());

        $this->assertSame('TEST', $client['environment']);
        $this->assertSame($this->config()->isReadyToPayRequest(), $client['isReadyToPayRequest']);
        $this->assertSame(
            $this->config()->paymentDataRequest($this->amount()),
            $client['paymentDataRequest']
        );
    }

    public function testClientConfigurationSurvivesJsonEncoding()
    {
        $json = json_encode($this->config()->clientConfiguration($this->amount()));

        $this->assertIsString($json);
        $this->assertStringContainsString('"gateway":"opayoelavon"', $json);

        // allowedPaymentMethods must encode as a JSON array, not an object.
        $this->assertStringContainsString('"allowedPaymentMethods":[{', $json);
    }

    // -----------------------------------------------------------------------
    // Amount conversion: Google wants major units as a decimal string
    // -----------------------------------------------------------------------

    /**
     * @dataProvider majorUnitProvider
     */
    public function testFormatMajorUnits(string $currency, int $minorUnits, string $expected)
    {
        $amount = new Amount(new Currency($currency), $minorUnits);

        $this->assertSame($expected, Configuration::formatMajorUnits($amount));
    }

    public static function majorUnitProvider(): array
    {
        return [
            'whole pounds' => ['GBP', 1000, '10.00'],
            'pence' => ['GBP', 999, '9.99'],
            'under a pound' => ['GBP', 5, '0.05'],
            'zero' => ['GBP', 0, '0.00'],
            'large' => ['GBP', 123456789, '1234567.89'],
            'no minor unit' => ['JPY', 1000, '1000'],
            'three digit minor unit' => ['BHD', 1234, '1.234'],
        ];
    }

    // -----------------------------------------------------------------------
    // Configuration guardrails
    // -----------------------------------------------------------------------

    public function testEmptyGatewayMerchantIdIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/MyOpayo/');

        new Configuration(gatewayMerchantId: '', merchantName: $this->merchantName);
    }

    public function testEmptyMerchantNameIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);

        new Configuration(gatewayMerchantId: $this->gatewayMerchantId, merchantName: '');
    }

    public function testBadCountryCodeIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/two upper-case letters/');

        new Configuration(
            gatewayMerchantId: $this->gatewayMerchantId,
            merchantName: $this->merchantName,
            countryCode: 'GBR',
        );
    }

    public function testEmptyAuthMethodsAreRejected()
    {
        $this->expectException(InvalidArgumentException::class);

        new Configuration(
            gatewayMerchantId: $this->gatewayMerchantId,
            merchantName: $this->merchantName,
            allowedAuthMethods: [],
        );
    }

    public function testCardNetworksAndCountryCanBeOverridden()
    {
        $config = new Configuration(
            gatewayMerchantId: $this->gatewayMerchantId,
            merchantName: $this->merchantName,
            countryCode: 'IE',
            allowedCardNetworks: ['VISA', 'MASTERCARD'],
        );

        $request = $config->isReadyToPayRequest();

        $this->assertSame(
            ['VISA', 'MASTERCARD'],
            $request['allowedPaymentMethods'][0]['parameters']['allowedCardNetworks']
        );
        $this->assertSame('IE', $config->transactionInfo($this->amount())['countryCode']);
    }
}
