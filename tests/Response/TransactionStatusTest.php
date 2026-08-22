<?php

namespace Academe\Opayo\Pi\Response;

use PHPUnit\Framework\TestCase;

class TransactionStatusTest extends TestCase
{
    public function testAllCasesHaveCorrectValues()
    {
        $this->assertEquals('Ok', TransactionStatus::OK->value);
        $this->assertEquals('NotAuthed', TransactionStatus::NOT_AUTHED->value);
        $this->assertEquals('Rejected', TransactionStatus::REJECTED->value);
        $this->assertEquals('3DAuth', TransactionStatus::THREE_D_AUTH->value);
        $this->assertEquals('Malformed', TransactionStatus::MALFORMED->value);
        $this->assertEquals('Invalid', TransactionStatus::INVALID->value);
        $this->assertEquals('Error', TransactionStatus::ERROR->value);
    }

    public function testTryFromInsensitiveWithExactMatch()
    {
        $this->assertSame(TransactionStatus::OK, TransactionStatus::tryFromInsensitive('Ok'));
        $this->assertSame(TransactionStatus::NOT_AUTHED, TransactionStatus::tryFromInsensitive('NotAuthed'));
        $this->assertSame(TransactionStatus::THREE_D_AUTH, TransactionStatus::tryFromInsensitive('3DAuth'));
    }

    public function testTryFromInsensitiveWithDifferentCase()
    {
        $this->assertSame(TransactionStatus::OK, TransactionStatus::tryFromInsensitive('ok'));
        $this->assertSame(TransactionStatus::OK, TransactionStatus::tryFromInsensitive('OK'));
        $this->assertSame(TransactionStatus::OK, TransactionStatus::tryFromInsensitive('oK'));
        $this->assertSame(TransactionStatus::NOT_AUTHED, TransactionStatus::tryFromInsensitive('notauthed'));
        $this->assertSame(TransactionStatus::NOT_AUTHED, TransactionStatus::tryFromInsensitive('NOTAUTHED'));
    }

    public function testTryFromInsensitiveWithNull()
    {
        $this->assertNull(TransactionStatus::tryFromInsensitive(null));
    }

    public function testTryFromInsensitiveWithInvalidValue()
    {
        $this->assertNull(TransactionStatus::tryFromInsensitive('InvalidStatus'));
        $this->assertNull(TransactionStatus::tryFromInsensitive(''));
        $this->assertNull(TransactionStatus::tryFromInsensitive('Success'));
    }

    public function testIsSuccess()
    {
        $this->assertTrue(TransactionStatus::OK->isSuccess());
        $this->assertFalse(TransactionStatus::NOT_AUTHED->isSuccess());
        $this->assertFalse(TransactionStatus::REJECTED->isSuccess());
        $this->assertFalse(TransactionStatus::THREE_D_AUTH->isSuccess());
        $this->assertFalse(TransactionStatus::ERROR->isSuccess());
    }

    public function testRequiresAuthentication()
    {
        $this->assertTrue(TransactionStatus::THREE_D_AUTH->requiresAuthentication());
        $this->assertFalse(TransactionStatus::OK->requiresAuthentication());
        $this->assertFalse(TransactionStatus::NOT_AUTHED->requiresAuthentication());
        $this->assertFalse(TransactionStatus::REJECTED->requiresAuthentication());
        $this->assertFalse(TransactionStatus::ERROR->requiresAuthentication());
    }

    public function testIsError()
    {
        $this->assertTrue(TransactionStatus::NOT_AUTHED->isError());
        $this->assertTrue(TransactionStatus::REJECTED->isError());
        $this->assertTrue(TransactionStatus::MALFORMED->isError());
        $this->assertTrue(TransactionStatus::INVALID->isError());
        $this->assertTrue(TransactionStatus::ERROR->isError());

        $this->assertFalse(TransactionStatus::OK->isError());
        $this->assertFalse(TransactionStatus::THREE_D_AUTH->isError());
    }

    public function testIsFinal()
    {
        $this->assertTrue(TransactionStatus::OK->isFinal());
        $this->assertTrue(TransactionStatus::NOT_AUTHED->isFinal());
        $this->assertTrue(TransactionStatus::REJECTED->isFinal());
        $this->assertTrue(TransactionStatus::MALFORMED->isFinal());
        $this->assertTrue(TransactionStatus::INVALID->isFinal());
        $this->assertTrue(TransactionStatus::ERROR->isFinal());

        $this->assertFalse(TransactionStatus::THREE_D_AUTH->isFinal());
    }

    public function testDescription()
    {
        $this->assertEquals('Transaction successful', TransactionStatus::OK->description());
        $this->assertEquals('Transaction not authenticated', TransactionStatus::NOT_AUTHED->description());
        $this->assertEquals('Transaction rejected by bank', TransactionStatus::REJECTED->description());
        $this->assertEquals('3D Secure authentication required', TransactionStatus::THREE_D_AUTH->description());
        $this->assertEquals('Malformed request', TransactionStatus::MALFORMED->description());
        $this->assertEquals('Invalid request', TransactionStatus::INVALID->description());
        $this->assertEquals('Transaction error', TransactionStatus::ERROR->description());
    }

    public function testSeverity()
    {
        $this->assertEquals('success', TransactionStatus::OK->severity());
        $this->assertEquals('info', TransactionStatus::THREE_D_AUTH->severity());
        $this->assertEquals('warning', TransactionStatus::NOT_AUTHED->severity());
        $this->assertEquals('warning', TransactionStatus::REJECTED->severity());
        $this->assertEquals('error', TransactionStatus::MALFORMED->severity());
        $this->assertEquals('error', TransactionStatus::INVALID->severity());
        $this->assertEquals('error', TransactionStatus::ERROR->severity());
        $this->assertEquals('success', TransactionStatus::AUTHENTICATED->severity());
        $this->assertEquals('warning', TransactionStatus::REGISTERED->severity());
    }

    public function testEnumCasesCount()
    {
        $cases = TransactionStatus::cases();
        $this->assertCount(10, $cases);
    }

    public function testAllCasesAreUnique()
    {
        $cases = TransactionStatus::cases();
        $values = array_map(fn($case) => $case->value, $cases);
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues);
    }

    public function testEnumInSwitchStatement()
    {
        $status = TransactionStatus::OK;

        $result = match ($status) {
            TransactionStatus::OK => 'success',
            TransactionStatus::THREE_D_AUTH => 'redirect',
            default => 'failure',
        };

        $this->assertEquals('success', $result);
    }

    public function testEnumComparison()
    {
        $status1 = TransactionStatus::OK;
        $status2 = TransactionStatus::OK;
        $status3 = TransactionStatus::ERROR;

        $this->assertSame($status1, $status2);
        $this->assertNotSame($status1, $status3);
        $this->assertTrue($status1 === $status2);
        $this->assertFalse($status1 === $status3);
    }

    public function testBackwardsCompatibilityWithConstants()
    {
        // Demonstrate that enum values match what constants would have been
        $this->assertEquals('Ok', TransactionStatus::OK->value);
        $this->assertEquals('NotAuthed', TransactionStatus::NOT_AUTHED->value);
        $this->assertEquals('3DAuth', TransactionStatus::THREE_D_AUTH->value);

        // These values should match the old constant values exactly
        // so that AbstractTransaction::STATUS_OK === TransactionStatus::OK->value
    }
}
