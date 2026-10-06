<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use Academe\Opayo\Pi\Checkout\OutcomeKind;
use Academe\Opayo\Pi\Checkout\PaymentOutcome;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use PHPUnit\Framework\TestCase;

/**
 * Responses to Authenticate and Authorise transactions and the cancel
 * instruction. The bodies are as returned by the sandbox, October 2026.
 */
class AuthenticateAuthoriseTest extends TestCase
{
    protected function authenticated(): array
    {
        return [
            'statusCode' => '2010',
            'statusDetail' => 'The Authentication was Successful.',
            'transactionId' => 'B4114B15-B2D8-A1D9-44EF-A4F7FA398FC1',
            'transactionType' => 'Authenticate',
            'retrievalReference' => 30416885,
            'bankResponseCode' => '00',
            'bankAuthorisationCode' => '999777',
            'paymentMethod' => ['card' => [
                'cardType' => 'Visa',
                'lastFourDigits' => '0006',
                'expiryDate' => '1229',
                'cardIdentifier' => '670BFB2D-5DCC-43E4-A30A-869023B06305',
                'reusable' => false,
            ]],
            'amount' => ['totalAmount' => 1999, 'saleAmount' => 1999, 'surchargeAmount' => 0],
            'currency' => 'GBP',
            'acsTransId' => '3a6b1913-c3c4-44e6-b648-09519cdc4fdb',
            'dsTransId' => '0acc874e-d97b-4f83-9335-a6d70c034302',
            'status' => 'Authenticated',
            'avsCvcCheck' => [
                'status' => 'AllMatched',
                'address' => 'Matched',
                'postalCode' => 'Matched',
                'securityCode' => 'Matched',
            ],
            '3DSecure' => ['status' => 'Authenticated'],
        ];
    }

    /** 3D Secure not performed: the card is stored, with no liability shift. */
    protected function registered(): array
    {
        return [
            'statusCode' => '2011',
            'statusDetail' => 'The Transaction has been Registered.',
            'transactionId' => 'DD007337-FF37-38C2-4F01-800A7FF03E8C',
            'transactionType' => 'Authenticate',
            'amount' => ['totalAmount' => 1999, 'saleAmount' => 1999, 'surchargeAmount' => 0],
            'currency' => 'GBP',
            'status' => 'Registered',
            '3DSecure' => ['status' => 'NotChecked'],
        ];
    }

    protected function authorised(): array
    {
        return [
            'statusCode' => '0000',
            'statusDetail' => 'The Authorisation was Successful.',
            'transactionId' => '718676A1-71F3-EAFD-82CE-66ABB73A0D9C',
            'transactionType' => 'Authorise',
            'retrievalReference' => 30416886,
            'bankResponseCode' => '00',
            'bankAuthorisationCode' => '999777',
            'paymentMethod' => ['card' => ['cardType' => 'Visa', 'lastFourDigits' => '0006', 'expiryDate' => '1229']],
            'amount' => ['totalAmount' => 1000, 'saleAmount' => 1000, 'surchargeAmount' => 0],
            'currency' => 'GBP',
            'acsTransId' => '3a6b1913-c3c4-44e6-b648-09519cdc4fdb',
            'dsTransId' => '0acc874e-d97b-4f83-9335-a6d70c034302',
            'status' => 'Ok',
            'avsCvcCheck' => [
                'status' => 'AddressMatchOnly',
                'address' => 'Matched',
                'postalCode' => 'Matched',
                'securityCode' => 'NotChecked',
            ],
            '3DSecure' => ['status' => 'Authenticated'],
        ];
    }

    public function testAuthenticatedResponse()
    {
        $response = ResponseFactory::fromData($this->authenticated(), 201);

        $this->assertInstanceOf(Authenticate::class, $response);
        $this->assertInstanceOf(Payment::class, $response);
        $this->assertSame('Authenticate', $response->getTransactionType());
        $this->assertSame(TransactionStatus::AUTHENTICATED, $response->getStatusEnum());
        $this->assertTrue($response->isAuthenticated());
        $this->assertFalse($response->isRegistered());
        $this->assertSame('B4114B15-B2D8-A1D9-44EF-A4F7FA398FC1', $response->getTransactionId());
    }

    public function testRegisteredResponse()
    {
        $response = ResponseFactory::fromData($this->registered(), 201);

        $this->assertInstanceOf(Authenticate::class, $response);
        $this->assertSame(TransactionStatus::REGISTERED, $response->getStatusEnum());
        $this->assertTrue($response->isRegistered());
        $this->assertFalse($response->isAuthenticated());
    }

    public function testAuthorisedResponse()
    {
        $response = ResponseFactory::fromData($this->authorised(), 201);

        $this->assertInstanceOf(Authorise::class, $response);
        $this->assertInstanceOf(Payment::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame(1000, $response->getTotalAmount()->getAmount());
        $this->assertSame('999777', $response->getBankAuthorisationCode());
    }

    public function testThreeDSecureTransactionIdsAreRead()
    {
        $response = ResponseFactory::fromData($this->authenticated(), 201);

        $this->assertSame('3a6b1913-c3c4-44e6-b648-09519cdc4fdb', $response->getAcsTransId());
        $this->assertSame('0acc874e-d97b-4f83-9335-a6d70c034302', $response->getDsTransId());

        $serialized = $response->jsonSerialize();
        $this->assertSame('3a6b1913-c3c4-44e6-b648-09519cdc4fdb', $serialized['acsTransId']);
        $this->assertSame('0acc874e-d97b-4f83-9335-a6d70c034302', $serialized['dsTransId']);

        $without = ResponseFactory::fromData($this->registered(), 201);
        $this->assertNull($without->getAcsTransId());
        $this->assertArrayNotHasKey('acsTransId', $without->jsonSerialize());
    }

    public function testCancelResponse()
    {
        $response = ResponseFactory::fromData(
            ['instructionType' => 'cancel', 'date' => '2026-10-06T22:23:39.769321313Z'],
            201
        );

        $this->assertInstanceOf(Cancel::class, $response);
        $this->assertSame('cancel', $response->getInstructionType());
        $this->assertSame('2026-10-06 22:23:39', $response->getDate()->format('Y-m-d H:i:s'));
    }

    public function testPaymentOutcomeTreatsBothAsFinished()
    {
        $authenticated = PaymentOutcome::fromResponse(ResponseFactory::fromData($this->authenticated(), 201));
        $authorised = PaymentOutcome::fromResponse(ResponseFactory::fromData($this->authorised(), 201));

        $this->assertSame(OutcomeKind::Finished, $authenticated->kind);
        $this->assertTrue($authenticated->isSuccessful());
        $this->assertSame('Authenticated', $authenticated->status());

        $this->assertSame(OutcomeKind::Finished, $authorised->kind);
        $this->assertTrue($authorised->isSuccessful());
    }
}
