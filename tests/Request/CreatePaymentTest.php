<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use PHPUnit\Framework\TestCase;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\PaymentMethodInterface;
use Academe\Opayo\Pi\Request\Model\Person;

class CreatePaymentTest extends TestCase
{
    protected function createRequest(
        ?Address $shippingAddress = null,
        ?Person $shippingRecipient = null
    ): CreatePayment {
        $paymentMethod = new class implements PaymentMethodInterface {
            public function jsonSerialize(): mixed
            {
                return ['card' => ['merchantSessionKey' => 'key', 'cardIdentifier' => 'id']];
            }
        };

        return new CreatePayment(
            new Endpoint(Endpoint::MODE_TEST),
            new Auth('vendor', 'key', 'password'),
            $paymentMethod,
            'vendor-tx-code-123',
            new Amount(new Currency('GBP'), 999),
            'Payment description',
            new Address('Billing1', 'Billing2', 'BillCity', 'NE26 2SB', 'GB'),
            new Person('Bill', 'Payer'),
            $shippingAddress,
            $shippingRecipient
        );
    }

    public function testShippingDetailsOmittedWhenNoneSet()
    {
        $this->assertArrayNotHasKey('shippingDetails', $this->createRequest()->jsonSerialize());
    }

    public function testShippingDetailsSerialized()
    {
        $request = $this->createRequest(
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

    public function testShippingDetailsSerializedWithRecipientOnly()
    {
        $request = $this->createRequest(null, new Person('Sam', 'Jones'));

        $body = $request->jsonSerialize();

        $this->assertSame(
            [
                'recipientFirstName' => 'Sam',
                'recipientLastName' => 'Jones',
            ],
            $body['shippingDetails']
        );
    }
}
