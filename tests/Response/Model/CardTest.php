<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Model;

use PHPUnit\Framework\TestCase;

class CardTest extends TestCase
{
    public function testFromDataWithoutReusableFlag()
    {
        // Repeat transaction responses return a card object with no
        // "reusable" field; parsing must not fail on it.
        $card = Card::fromData([
            'card' => [
                'cardType' => 'Visa',
                'lastFourDigits' => '0006',
                'expiryDate' => '0828',
            ],
        ]);

        $this->assertSame('Visa', $card->getCardType());
        $this->assertSame('0006', $card->getLastFourDigits());
        $this->assertFalse($card->isReusable());
    }

    public function testFromDataWithReusableFlag()
    {
        $card = Card::fromData([
            'card' => [
                'cardType' => 'Visa',
                'lastFourDigits' => '0006',
                'expiryDate' => '0828',
                'cardIdentifier' => 'ABC-123',
                'reusable' => true,
            ],
        ]);

        $this->assertTrue($card->isReusable());
        $this->assertSame('ABC-123', $card->getCardIdentifier());
    }
}
