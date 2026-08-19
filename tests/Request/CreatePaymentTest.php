<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use PHPUnit\Framework\TestCase;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Request\Enums\Apply3DSecure;
use Academe\Opayo\Pi\Request\Enums\ApplyAvsCvcCheck;
use Academe\Opayo\Pi\Request\Enums\EntryMethod;
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

    public function testEntryMethodAcceptsEnum()
    {
        $body = $this->createRequest()
            ->withEntryMethod(EntryMethod::MailOrder)
            ->jsonSerialize();

        $this->assertSame('MailOrder', $body['entryMethod']);
    }

    public function testEntryMethodStringStillAccepted()
    {
        // The legacy string path is case-insensitive and must stay working.
        $body = $this->createRequest()
            ->withEntryMethod('ecommerce')
            ->jsonSerialize();

        $this->assertSame('Ecommerce', $body['entryMethod']);
    }

    public function testApplyAvsCvcCheckAcceptsEnum()
    {
        $body = $this->createRequest()
            ->withApplyAvsCvcCheck(ApplyAvsCvcCheck::Force)
            ->jsonSerialize();

        $this->assertSame('Force', $body['applyAvsCvcCheck']);
    }

    public function testApply3DSecureAcceptsEnum()
    {
        $body = $this->createRequest()
            ->withApply3DSecure(Apply3DSecure::Disable)
            ->jsonSerialize();

        $this->assertSame('Disable', $body['apply3DSecure']);
    }

    public function testEnumAcceptedAsConstructorOption()
    {
        $paymentMethod = new class implements PaymentMethodInterface {
            public function jsonSerialize(): mixed
            {
                return ['card' => ['merchantSessionKey' => 'key', 'cardIdentifier' => 'id']];
            }
        };

        $request = new CreatePayment(
            new Endpoint(Endpoint::MODE_TEST),
            new Auth('vendor', 'key', 'password'),
            $paymentMethod,
            'vendor-tx-code-123',
            new Amount(new Currency('GBP'), 999),
            'Payment description',
            new Address('Billing1', 'Billing2', 'BillCity', 'NE26 2SB', 'GB'),
            new Person('Bill', 'Payer'),
            null,
            null,
            ['entryMethod' => EntryMethod::TelephoneOrder]
        );

        $this->assertSame('TelephoneOrder', $request->jsonSerialize()['entryMethod']);
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
