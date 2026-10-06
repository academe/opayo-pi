<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Response\Secure3DRedirect;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

class PaymentOutcomeTest extends TestCase
{
    private function authorised(): object
    {
        return ResponseFactory::fromData([
            'transactionId' => 'T-OK',
            'transactionType' => 'Payment',
            'status' => 'Ok',
            'statusCode' => '0000',
            'statusDetail' => 'The Authorisation was Successful.',
        ], 201);
    }

    private function declined(): object
    {
        return ResponseFactory::fromData([
            'transactionId' => 'T-NO',
            'transactionType' => 'Payment',
            'status' => 'NotAuthed',
            'statusCode' => '2000',
            'statusDetail' => 'The Authorisation was Declined by the bank.',
        ], 201);
    }

    /** What Opayo returns after a failed 3D Secure challenge: no transactionType. */
    private function failedChallenge(): object
    {
        return ResponseFactory::fromData([
            'transactionId' => 'T-3DF',
            'status' => 'Rejected',
            'statusCode' => '2001',
            'statusDetail' => 'The Transaction was rejected because of the 3D-Authentication failed.',
            'paymentMethod' => ['card' => ['cardType' => 'Visa', 'lastFourDigits' => '0006', 'expiryDate' => '1229']],
            'amount' => ['totalAmount' => 999, 'saleAmount' => 999, 'surchargeAmount' => 0],
            'currency' => 'GBP',
        ], 200);
    }

    private function challenge(): object
    {
        return ResponseFactory::fromData([
            'statusCode' => '2021',
            'statusDetail' => 'Please redirect your customer to the ACS to continue the 3D Secure process.',
            'transactionId' => 'T-3DS',
            'status' => '3DAuth',
            'acsUrl' => 'https://acs.example/challenge',
            'cReq' => 'eyJjcmVxIjoidGVzdCJ9',
            'dsTranId' => 'ds-1',
        ], 202);
    }

    private function payPal(): object
    {
        return ResponseFactory::fromData([
            'transactionId' => 'T-PP',
            'transactionType' => 'Payment',
            'status' => 'Redirect',
            'statusCode' => '2023',
            'statusDetail' => 'Transaction registered, redirect client to wallet server.',
            'paymentMethod' => ['paypal' => [
                'redirectUrl' => 'https://www.sandbox.paypal.com/checkoutnow?token=ABC',
                'orderId' => 'ABC',
            ]],
        ], 201);
    }

    private function errors(): object
    {
        return ResponseFactory::fromData([
            'errors' => [
                ['code' => 1003, 'description' => 'Missing mandatory field', 'property' => 'amount'],
                ['code' => 1004, 'description' => 'Invalid length', 'property' => 'vendorTxCode'],
            ],
        ], 422);
    }

    public function testAuthorisedIsFinishedAndSuccessful()
    {
        $outcome = PaymentOutcome::fromResponse($this->authorised());

        $this->assertSame(OutcomeKind::Finished, $outcome->kind);
        $this->assertTrue($outcome->isFinished());
        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame('T-OK', $outcome->transactionId());
        $this->assertSame('Ok', $outcome->status());
        $this->assertSame('The Authorisation was Successful.', $outcome->statusDetail());
    }

    public function testDeclinedIsFinishedNotSuccessful()
    {
        $outcome = PaymentOutcome::fromResponse($this->declined());

        $this->assertTrue($outcome->isFinished());
        $this->assertFalse($outcome->isSuccessful());
        $this->assertSame('NotAuthed', $outcome->status());
    }

    public function testFailedChallengeWithoutTransactionTypeIsFinished()
    {
        $outcome = PaymentOutcome::fromResponse($this->failedChallenge());

        $this->assertTrue($outcome->isFinished());
        $this->assertFalse($outcome->isSuccessful());
        $this->assertSame('T-3DF', $outcome->transactionId());
    }

    public function testChallenge()
    {
        $outcome = PaymentOutcome::fromResponse($this->challenge());

        $this->assertTrue($outcome->isChallenge());
        $this->assertSame('T-3DS', $outcome->transactionId());
        $this->assertSame('https://acs.example/challenge', $outcome->acsUrl());
        $this->assertSame(['creq' => 'eyJjcmVxIjoidGVzdCJ9'], $outcome->formFields());
        $this->assertSame(
            ['creq' => 'eyJjcmVxIjoidGVzdCJ9', 'threeDSSessionData' => 'abc'],
            $outcome->formFields('abc')
        );
    }

    public function testPayPalRedirect()
    {
        $outcome = PaymentOutcome::fromResponse($this->payPal());

        $this->assertTrue($outcome->isRedirect());
        $this->assertSame('T-PP', $outcome->transactionId());
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=ABC', $outcome->redirectUrl());
    }

    public function testErrorsAreRejected()
    {
        $outcome = PaymentOutcome::fromResponse($this->errors());

        $this->assertTrue($outcome->isRejected());
        $this->assertSame([
            ['code' => 1003, 'description' => 'Missing mandatory field', 'property' => 'amount'],
            ['code' => 1004, 'description' => 'Invalid length', 'property' => 'vendorTxCode'],
        ], $outcome->errors());
    }

    public function testResponseIsTheSameInstance()
    {
        $response = $this->authorised();

        $this->assertSame($response, PaymentOutcome::fromResponse($response)->response());
    }

    public function testWrongKindGetterThrows()
    {
        $outcome = PaymentOutcome::fromResponse($this->authorised());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('redirectUrl() is only available on a redirect outcome; this is a finished outcome.');

        $outcome->redirectUrl();
    }

    public function testTransactionIdThrowsOnRejected()
    {
        $this->expectException(LogicException::class);

        PaymentOutcome::fromResponse($this->errors())->transactionId();
    }

    public function testRetired3DSecureV1IsUnexpected()
    {
        $v1 = Secure3DRedirect::fromData([
            'statusCode' => '2007',
            'status' => '3DAuth',
            'transactionId' => 'T-V1',
            'acsUrl' => 'https://acs.example/v1',
            'paReq' => 'PAREQ',
        ], 202);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Secure3DRedirect');

        PaymentOutcome::fromResponse($v1);
    }

    public function testUnknownObjectIsUnexpected()
    {
        $this->expectException(UnexpectedValueException::class);

        PaymentOutcome::fromResponse(new stdClass());
    }

    public function testSummaryForEachKind()
    {
        $this->assertSame([
            'kind' => 'finished',
            'successful' => true,
            'transactionId' => 'T-OK',
            'status' => 'Ok',
            'statusDetail' => 'The Authorisation was Successful.',
            'errors' => [],
            'settlementReferenceText' => null,
            'declineDetail' => null,
        ], PaymentOutcome::fromResponse($this->authorised())->summary());

        $this->assertSame([
            'kind' => 'redirect',
            'successful' => false,
            'transactionId' => 'T-PP',
            'status' => 'Redirect',
            'statusDetail' => 'Transaction registered, redirect client to wallet server.',
            'errors' => [],
            'settlementReferenceText' => null,
            'declineDetail' => null,
        ], PaymentOutcome::fromResponse($this->payPal())->summary());

        $rejected = PaymentOutcome::fromResponse($this->errors())->summary();
        $this->assertSame('rejected', $rejected['kind']);
        $this->assertFalse($rejected['successful']);
        $this->assertNull($rejected['transactionId']);
        $this->assertCount(2, $rejected['errors']);
        $this->assertNull($rejected['settlementReferenceText']);
        $this->assertNull($rejected['declineDetail']);
    }

    public function testSummaryCarriesTheSettlementReference()
    {
        $response = ResponseFactory::fromData([
            'transactionId' => 'T-OK',
            'transactionType' => 'Payment',
            'status' => 'Ok',
            'statusCode' => '0000',
            'statusDetail' => 'The Authorisation was Successful.',
            'settlementReferenceText' => 'Order12345',
        ], 201);

        $summary = PaymentOutcome::fromResponse($response)->summary();

        $this->assertSame('Order12345', $summary['settlementReferenceText']);
        $this->assertNull($summary['declineDetail']);
    }

    public function testSummaryCarriesTheDeclineDetail()
    {
        // As returned by the sandbox for the "declined by the bank" test card.
        $response = ResponseFactory::fromData([
            'transactionId' => 'T-NO',
            'transactionType' => 'Payment',
            'status' => 'NotAuthed',
            'statusCode' => '2000',
            'statusDetail' => 'The Authorisation was Declined by the bank.',
            'additionalDeclineDetail' => [
                'additionalDeclineCode' => '03',
                'additionalDeclineCodeDescription' => 'DECLINED',
                'additionalDeclineCodeCategory' => '03',
            ],
        ], 201);

        $summary = PaymentOutcome::fromResponse($response)->summary();

        $this->assertFalse($summary['successful']);
        $this->assertSame(
            ['code' => '03', 'description' => 'DECLINED', 'category' => '03'],
            $summary['declineDetail']
        );
    }

    public function testSummarySurvivesJsonAndSessionStorage()
    {
        $summary = PaymentOutcome::fromResponse($this->declined())->summary();

        $this->assertSame($summary, json_decode(json_encode($summary), true));
        $this->assertSame($summary, unserialize(serialize($summary)));
    }
}
