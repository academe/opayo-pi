<?php

namespace Academe\Opayo\Pi\Response;

use PHPUnit\Framework\TestCase;

class AbstractTransactionEnumTest extends TestCase
{
    protected function createMockTransaction(array $data = []): AbstractTransaction
    {
        return new class($data) extends AbstractTransaction {
            public function __construct(array $data = [])
            {
                parent::__construct($data, 200);
            }

            protected function setData(mixed $data): mixed
            {
                parent::setData($data);
                return $this;
            }
        };
    }

    public function testConstantsReferenceEnumValues()
    {
        // Verify backwards compatibility - constants should equal enum values
        $this->assertEquals('Ok', AbstractTransaction::STATUS_OK);
        $this->assertEquals('NotAuthed', AbstractTransaction::STATUS_NOTAUTHED);
        $this->assertEquals('Rejected', AbstractTransaction::STATUS_REJECTED);
        $this->assertEquals('3DAuth', AbstractTransaction::STATUS_3DAUTH);
        $this->assertEquals('Malformed', AbstractTransaction::STATUS_MALFORMED);
        $this->assertEquals('Invalid', AbstractTransaction::STATUS_INVALID);
        $this->assertEquals('Error', AbstractTransaction::STATUS_ERROR);
    }

    public function testConstantsMatchEnumValues()
    {
        // Ensure constants reference actual enum values
        $this->assertEquals(TransactionStatus::OK->value, AbstractTransaction::STATUS_OK);
        $this->assertEquals(TransactionStatus::NOT_AUTHED->value, AbstractTransaction::STATUS_NOTAUTHED);
        $this->assertEquals(TransactionStatus::REJECTED->value, AbstractTransaction::STATUS_REJECTED);
        $this->assertEquals(TransactionStatus::THREE_D_AUTH->value, AbstractTransaction::STATUS_3DAUTH);
        $this->assertEquals(TransactionStatus::MALFORMED->value, AbstractTransaction::STATUS_MALFORMED);
        $this->assertEquals(TransactionStatus::INVALID->value, AbstractTransaction::STATUS_INVALID);
        $this->assertEquals(TransactionStatus::ERROR->value, AbstractTransaction::STATUS_ERROR);
    }

    public function testGetStatusEnumReturnsEnum()
    {
        $transaction = $this->createMockTransaction(['status' => 'Ok']);

        $status = $transaction->getStatusEnum();

        $this->assertInstanceOf(TransactionStatus::class, $status);
        $this->assertSame(TransactionStatus::OK, $status);
    }

    public function testGetStatusEnumWithDifferentValues()
    {
        $testCases = [
            'Ok' => TransactionStatus::OK,
            'NotAuthed' => TransactionStatus::NOT_AUTHED,
            'Rejected' => TransactionStatus::REJECTED,
            '3DAuth' => TransactionStatus::THREE_D_AUTH,
            'Malformed' => TransactionStatus::MALFORMED,
            'Invalid' => TransactionStatus::INVALID,
            'Error' => TransactionStatus::ERROR,
        ];

        foreach ($testCases as $input => $expected) {
            $transaction = $this->createMockTransaction(['status' => $input]);
            $this->assertSame($expected, $transaction->getStatusEnum());
        }
    }

    public function testGetStatusReturnsStringValue()
    {
        $transaction = $this->createMockTransaction(['status' => 'Ok']);

        $status = $transaction->getStatus();

        $this->assertIsString($status);
        $this->assertEquals('Ok', $status);
    }

    public function testGetStatusReturnsNullWhenNotSet()
    {
        $transaction = $this->createMockTransaction([]);

        $this->assertNull($transaction->getStatusEnum());
        $this->assertNull($transaction->getStatus());
    }

    public function testIsSuccessfulWithOkStatus()
    {
        $transaction = $this->createMockTransaction(['status' => 'Ok']);

        $this->assertTrue($transaction->isSuccessful());
    }

    public function testIsSuccessfulWithNonOkStatus()
    {
        $statuses = ['NotAuthed', 'Rejected', '3DAuth', 'Malformed', 'Invalid', 'Error'];

        foreach ($statuses as $status) {
            $transaction = $this->createMockTransaction(['status' => $status]);
            $this->assertFalse($transaction->isSuccessful(), "Failed for status: $status");
        }
    }

    public function testIsSuccessfulReturnsFalseWhenNoStatus()
    {
        $transaction = $this->createMockTransaction([]);

        $this->assertFalse($transaction->isSuccessful());
    }

    public function testRequires3DSecureWithAuthStatus()
    {
        $transaction = $this->createMockTransaction(['status' => '3DAuth']);

        $this->assertTrue($transaction->requires3DSecure());
    }

    public function testRequires3DSecureWithNonAuthStatus()
    {
        $statuses = ['Ok', 'NotAuthed', 'Rejected', 'Malformed', 'Invalid', 'Error'];

        foreach ($statuses as $status) {
            $transaction = $this->createMockTransaction(['status' => $status]);
            $this->assertFalse($transaction->requires3DSecure(), "Failed for status: $status");
        }
    }

    public function testHasErrorWithErrorStatuses()
    {
        $errorStatuses = ['NotAuthed', 'Rejected', 'Malformed', 'Invalid', 'Error'];

        foreach ($errorStatuses as $status) {
            $transaction = $this->createMockTransaction(['status' => $status]);
            $this->assertTrue($transaction->hasError(), "Failed for status: $status");
        }
    }

    public function testHasErrorWithNonErrorStatuses()
    {
        $transaction = $this->createMockTransaction(['status' => 'Ok']);
        $this->assertFalse($transaction->hasError());

        $transaction = $this->createMockTransaction(['status' => '3DAuth']);
        $this->assertFalse($transaction->hasError());
    }

    public function testHasErrorReturnsFalseWhenNoStatus()
    {
        $transaction = $this->createMockTransaction([]);

        $this->assertFalse($transaction->hasError());
    }

    public function testJsonSerializeIncludesStatusAsString()
    {
        $transaction = $this->createMockTransaction([
            'status' => 'Ok',
            'statusCode' => '0000',
            'statusDetail' => 'Success',
        ]);

        $data = $transaction->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertIsString($data['status']);
        $this->assertEquals('Ok', $data['status']);
    }

    public function testBackwardsCompatibilityStringComparison()
    {
        $transaction = $this->createMockTransaction(['status' => 'Ok']);

        // Old code using string comparison should still work
        $this->assertEquals('Ok', $transaction->getStatus());
        $this->assertEquals(AbstractTransaction::STATUS_OK, $transaction->getStatus());
    }

    public function testBackwardsCompatibilityConstantComparison()
    {
        $transaction = $this->createMockTransaction(['status' => 'Ok']);

        // Old code comparing against constants should still work
        $status = $transaction->getStatus();
        if ($status === AbstractTransaction::STATUS_OK) {
            $this->assertTrue(true); // Test passes if we reach here
        } else {
            $this->fail('Constant comparison failed');
        }
    }

    public function testNewCodeEnumComparison()
    {
        $transaction = $this->createMockTransaction(['status' => 'Ok']);

        // New code using enum comparison
        $this->assertSame(TransactionStatus::OK, $transaction->getStatusEnum());

        if ($transaction->getStatusEnum() === TransactionStatus::OK) {
            $this->assertTrue(true); // Test passes if we reach here
        } else {
            $this->fail('Enum comparison failed');
        }
    }

    public function testEnumHelperMethods()
    {
        $transaction = $this->createMockTransaction(['status' => 'Ok']);

        // Can access enum helper methods
        $this->assertTrue($transaction->getStatusEnum()->isSuccess());
        $this->assertFalse($transaction->getStatusEnum()->isError());
        $this->assertFalse($transaction->getStatusEnum()->requiresAuthentication());
        $this->assertEquals('Transaction successful', $transaction->getStatusEnum()->description());
        $this->assertEquals('success', $transaction->getStatusEnum()->severity());
    }

    public function testCaseInsensitiveStatusParsing()
    {
        // Test that case variations are handled
        $transaction = $this->createMockTransaction(['status' => 'ok']); // lowercase

        $this->assertSame(TransactionStatus::OK, $transaction->getStatusEnum());
        $this->assertTrue($transaction->isSuccessful());
    }
}
