<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use Academe\Opayo\Pi\Response\Model\AdditionalDeclineDetail;
use PHPUnit\Framework\TestCase;

/**
 * Fields added to transaction responses since the response classes were
 * first written. The bodies are as returned by the sandbox.
 */
class TransactionExtraFieldsTest extends TestCase
{
    protected function declined(): array
    {
        return [
            'statusCode' => '2000',
            'statusDetail' => 'The Authorisation was Declined by the bank.',
            'transactionId' => '7BB784F0-7D48-1155-9F05-17AF8209E595',
            'transactionType' => 'Payment',
            'bankResponseCode' => '05',
            'paymentMethod' => ['card' => [
                'cardType' => 'Visa',
                'lastFourDigits' => '5639',
                'expiryDate' => '1229',
                'cardIdentifier' => 'E8C6F530-A542-440D-9519-526A77682D6A',
                'reusable' => false,
            ]],
            'amount' => ['totalAmount' => 1999, 'saleAmount' => 1999, 'surchargeAmount' => 0],
            'currency' => 'GBP',
            'additionalDeclineDetail' => [
                'additionalDeclineCode' => '03',
                'additionalDeclineCodeDescription' => 'DECLINED',
                'additionalDeclineCodeCategory' => '03',
            ],
            'status' => 'NotAuthed',
        ];
    }

    protected function authorised(): array
    {
        return [
            'statusCode' => '0000',
            'statusDetail' => 'The Authorisation was Successful.',
            'transactionId' => 'F31CF323-8C79-BC11-03E8-CF8ABFFA3BC8',
            'transactionType' => 'Payment',
            'settlementReferenceText' => 'Order12345',
            'amount' => ['totalAmount' => 1999, 'saleAmount' => 1999, 'surchargeAmount' => 0],
            'currency' => 'GBP',
            'status' => 'Ok',
        ];
    }

    public function testDeclinedPaymentHasAdditionalDeclineDetail()
    {
        $payment = new Payment($this->declined(), 201);

        $detail = $payment->getAdditionalDeclineDetail();

        $this->assertInstanceOf(AdditionalDeclineDetail::class, $detail);
        $this->assertSame('03', $detail->getCode());
        $this->assertSame('DECLINED', $detail->getDescription());
        $this->assertSame('03', $detail->getCategory());
    }

    public function testAuthorisedPaymentHasNoAdditionalDeclineDetail()
    {
        $this->assertNull((new Payment($this->authorised(), 201))->getAdditionalDeclineDetail());
    }

    public function testSettlementReferenceTextIsRead()
    {
        $this->assertSame('Order12345', (new Payment($this->authorised(), 201))->getSettlementReferenceText());
        $this->assertNull((new Payment($this->declined(), 201))->getSettlementReferenceText());
    }

    public function testBothAppearInTheSerializedResponse()
    {
        $declined = (new Payment($this->declined(), 201))->jsonSerialize();
        $authorised = (new Payment($this->authorised(), 201))->jsonSerialize();

        $this->assertSame($this->declined()['additionalDeclineDetail'], $declined['additionalDeclineDetail']);
        $this->assertArrayNotHasKey('settlementReferenceText', $declined);

        $this->assertSame('Order12345', $authorised['settlementReferenceText']);
        $this->assertArrayNotHasKey('additionalDeclineDetail', $authorised);
    }

    public function testFactoryBuildsTheSameResponse()
    {
        $payment = \Academe\Opayo\Pi\Factory\ResponseFactory::fromData($this->declined(), 201);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame('03', $payment->getAdditionalDeclineDetail()->getCategory());
    }
}
