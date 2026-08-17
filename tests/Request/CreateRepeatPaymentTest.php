<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use PHPUnit\Framework\TestCase;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\CredentialType;
use Academe\Opayo\Pi\Request\Model\Person;

class CreateRepeatPaymentTest extends TestCase
{
    protected function createRequest(array $options = []): CreateRepeatPayment
    {
        return new CreateRepeatPayment(
            new Endpoint(Endpoint::MODE_TEST),
            new Auth('vendor', 'key', 'password'),
            'REF-TRANSACTION-ID',
            'vendor-tx-code-123',
            new Amount(new Currency('GBP'), 999),
            'Repeat payment description',
            null,
            null,
            $options
        );
    }

    public function testMinimalPayload()
    {
        $body = $this->createRequest()->jsonSerialize();

        $this->assertSame('Repeat', $body['transactionType']);
        $this->assertSame('REF-TRANSACTION-ID', $body['referenceTransactionId']);
        $this->assertSame('vendor-tx-code-123', $body['vendorTxCode']);
        $this->assertSame(999, $body['amount']);
        $this->assertSame('GBP', $body['currency']);
        $this->assertSame('Repeat payment description', $body['description']);
        $this->assertArrayNotHasKey('credentialType', $body);
        $this->assertArrayNotHasKey('shippingDetails', $body);
    }

    public function testCredentialTypeAsConstructorOption()
    {
        $request = $this->createRequest([
            'credentialType' => CredentialType::createForMerchantReusingCard(),
        ]);

        $body = $request->jsonSerialize();

        $this->assertSame(
            [
                'cofUsage' => 'Subsequent',
                'initiatedType' => 'MIT',
                'mitType' => 'Unscheduled',
            ],
            $body['credentialType']
        );
    }

    public function testWithCredentialTypeIsImmutable()
    {
        $original = $this->createRequest();

        $request = $original->withCredentialType(
            CredentialType::createForMerchantReusingCard()
        );

        $this->assertArrayNotHasKey('credentialType', $original->jsonSerialize());
        $this->assertArrayHasKey('credentialType', $request->jsonSerialize());
    }

    public function testShippingDetailsSerialized()
    {
        $request = new CreateRepeatPayment(
            new Endpoint(Endpoint::MODE_TEST),
            new Auth('vendor', 'key', 'password'),
            'REF-TRANSACTION-ID',
            'vendor-tx-code-123',
            new Amount(new Currency('GBP'), 999),
            'Repeat payment description',
            new Address('Address1', 'Address2', 'City', 'NE26 2SB', 'GB'),
            new Person('Sam', 'Jones')
        );

        $body = $request->jsonSerialize();

        $this->assertSame(
            [
                'shippingAddress1' => 'Address1',
                'shippingAddress2' => 'Address2',
                'shippingCity' => 'City',
                'shippingPostalCode' => 'NE26 2SB',
                'shippingCountry' => 'GB',
                'recipientFirstName' => 'Sam',
                'recipientLastName' => 'Jones',
            ],
            $body['shippingDetails']
        );
    }
}
