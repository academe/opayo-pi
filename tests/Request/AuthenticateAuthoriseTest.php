<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Request\Enums\ApplyAvsCvcCheck;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\PaymentMethodInterface;
use Academe\Opayo\Pi\Request\Model\Person;
use Money\Money;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Authenticate (verify the cardholder, take nothing), Authorise (take funds
 * against an Authenticate) and the cancel instruction.
 */
class AuthenticateAuthoriseTest extends TestCase
{
    protected function endpoint(): Endpoint
    {
        return new Endpoint(Endpoint::MODE_TEST);
    }

    protected function auth(): Auth
    {
        return new Auth('vendor', 'key', 'password');
    }

    protected function authenticate(array $options = []): CreateAuthenticate
    {
        $paymentMethod = new class implements PaymentMethodInterface {
            public function jsonSerialize(): mixed
            {
                return ['card' => ['merchantSessionKey' => 'key', 'cardIdentifier' => 'id']];
            }
        };

        return new CreateAuthenticate(
            $this->endpoint(),
            $this->auth(),
            $paymentMethod,
            'vendor-tx-code-123',
            Amount::GBP(1999),
            'Authenticate description',
            new Address('Billing1', 'Billing2', 'BillCity', 'NE26 2SB', 'GB'),
            new Person('Bill', 'Payer', 'bill@example.com', '+441234567891'),
            options: $options
        );
    }

    protected function authorise(array $options = []): CreateAuthorise
    {
        return new CreateAuthorise(
            $this->endpoint(),
            $this->auth(),
            'AUTHENTICATE-TRANSACTION-ID',
            'vendor-tx-code-456',
            Amount::GBP(1000),
            'Authorise description',
            $options
        );
    }

    public function testAuthenticateIsAPaymentRequestWithItsOwnType()
    {
        $request = $this->authenticate();
        $body = $request->jsonSerialize();

        $this->assertInstanceOf(CreatePayment::class, $request);
        $this->assertSame('Authenticate', $body['transactionType']);
        $this->assertSame(1999, $body['amount']);
        $this->assertSame('GBP', $body['currency']);
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/transactions', (string) $request->getUri());
    }

    public function testAuthenticateTakesTheSameOptionsAsAPayment()
    {
        $body = $this->authenticate(['settlementReferenceText' => 'Order12345', 'apply3DSecure' => 'Force'])
            ->jsonSerialize();

        $this->assertSame('Order12345', $body['settlementReferenceText']);
        $this->assertSame('Force', $body['apply3DSecure']);
    }

    public function testAuthoriseBody()
    {
        $request = $this->authorise();

        $this->assertSame([
            'transactionType' => 'Authorise',
            'referenceTransactionId' => 'AUTHENTICATE-TRANSACTION-ID',
            'vendorTxCode' => 'vendor-tx-code-456',
            'amount' => 1000,
            'description' => 'Authorise description',
        ], $request->jsonSerialize());

        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/transactions', (string) $request->getUri());
    }

    public function testAuthoriseAcceptsMoney()
    {
        $request = new CreateAuthorise(
            $this->endpoint(),
            $this->auth(),
            'AUTHENTICATE-TRANSACTION-ID',
            'vendor-tx-code-456',
            Money::GBP(1000),
            'Authorise description'
        );

        $this->assertSame(1000, $request->jsonSerialize()['amount']);
    }

    public function testAuthoriseOptionalFields()
    {
        $body = $this->authorise(['applyAvsCvcCheck' => ApplyAvsCvcCheck::Force, 'cv2' => '123'])->jsonSerialize();

        $this->assertSame('Force', $body['applyAvsCvcCheck']);
        $this->assertSame('123', $body['cv2']);
    }

    public function testAuthoriseWithersReturnCopies()
    {
        $original = $this->authorise();
        $changed = $original->withApplyAvsCvcCheck('Disable')->withCv2('123');

        $this->assertNotSame($original, $changed);
        $this->assertArrayNotHasKey('applyAvsCvcCheck', $original->jsonSerialize());
        $this->assertArrayNotHasKey('cv2', $original->jsonSerialize());
        $this->assertSame('Disable', $changed->jsonSerialize()['applyAvsCvcCheck']);
    }

    public function testAuthoriseRejectsAnUnknownAvsCvcSetting()
    {
        $this->expectException(UnexpectedValueException::class);

        $this->authorise(['applyAvsCvcCheck' => 'Sometimes']);
    }

    public function testAuthoriseKeepsTheSecurityCodeOutOfDumps()
    {
        $request = $this->authorise(['cv2' => '987']);

        $this->assertSame('987', $request->jsonSerialize()['cv2']);
        $this->assertStringNotContainsString('987', print_r($request, true));
    }

    public function testCancelInstruction()
    {
        $request = new CreateCancel($this->endpoint(), $this->auth(), 'AUTHENTICATE-TRANSACTION-ID');

        $this->assertSame(['instructionType' => 'cancel'], $request->jsonSerialize());
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith(
            '/transactions/AUTHENTICATE-TRANSACTION-ID/instructions',
            (string) $request->getUri()
        );
    }
}
