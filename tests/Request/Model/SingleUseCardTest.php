<?php

namespace Academe\Opayo\Pi\Request\Model;

use PHPUnit\Framework\TestCase;

class SingleUseCardTest extends TestCase
{
    protected $sessionKey = '{11111111-1111-1111-1111-111111111111}';
    protected $cardIdentifier = '{22222222-2222-2222-2222-222222222222}';

    public function testConstructWithoutSaveFlag()
    {
        $card = new SingleUseCard($this->sessionKey, $this->cardIdentifier);

        $data = $card->jsonSerialize();

        $this->assertArrayHasKey('card', $data);
        $this->assertEquals($this->sessionKey, $data['card']['merchantSessionKey']);
        $this->assertEquals($this->cardIdentifier, $data['card']['cardIdentifier']);
        $this->assertArrayNotHasKey('save', $data['card']);
    }

    public function testConstructWithSaveTrue()
    {
        $card = new SingleUseCard($this->sessionKey, $this->cardIdentifier, true);

        $data = $card->jsonSerialize();

        $this->assertArrayHasKey('save', $data['card']);
        $this->assertTrue($data['card']['save']);
    }

    public function testConstructWithSaveFalse()
    {
        $card = new SingleUseCard($this->sessionKey, $this->cardIdentifier, false);

        $data = $card->jsonSerialize();

        $this->assertArrayHasKey('save', $data['card']);
        $this->assertFalse($data['card']['save']);
    }

    public function testWithSave()
    {
        $card = new SingleUseCard($this->sessionKey, $this->cardIdentifier);
        $cardWithSave = $card->withSave();

        // Original should not have save flag
        $originalData = $card->jsonSerialize();
        $this->assertArrayNotHasKey('save', $originalData['card']);

        // Cloned card should have save flag
        $clonedData = $cardWithSave->jsonSerialize();
        $this->assertArrayHasKey('save', $clonedData['card']);
        $this->assertTrue($clonedData['card']['save']);

        // Should be different instances
        $this->assertNotSame($card, $cardWithSave);
    }

    public function testWithSaveFalse()
    {
        $card = new SingleUseCard($this->sessionKey, $this->cardIdentifier);
        $cardWithSave = $card->withSave(false);

        $data = $cardWithSave->jsonSerialize();
        $this->assertArrayHasKey('save', $data['card']);
        $this->assertFalse($data['card']['save']);
    }

    public function testFromDataWithJsonString()
    {
        $json = json_encode([
            'card' => [
                'merchantSessionKey' => $this->sessionKey,
                'cardIdentifier' => $this->cardIdentifier,
            ]
        ]);

        $card = SingleUseCard::fromData($json);
        $data = $card->jsonSerialize();

        $this->assertEquals($this->sessionKey, $data['card']['merchantSessionKey']);
        $this->assertEquals($this->cardIdentifier, $data['card']['cardIdentifier']);
    }

    public function testFromDataWithArray()
    {
        $arrayData = [
            'card' => [
                'merchantSessionKey' => $this->sessionKey,
                'cardIdentifier' => $this->cardIdentifier,
            ]
        ];

        $card = SingleUseCard::fromData($arrayData);
        $data = $card->jsonSerialize();

        $this->assertEquals($this->sessionKey, $data['card']['merchantSessionKey']);
        $this->assertEquals($this->cardIdentifier, $data['card']['cardIdentifier']);
    }

    public function testFromDataWithoutCardWrapper()
    {
        $arrayData = [
            'merchantSessionKey' => $this->sessionKey,
            'cardIdentifier' => $this->cardIdentifier,
        ];

        $card = SingleUseCard::fromData($arrayData);
        $data = $card->jsonSerialize();

        $this->assertEquals($this->sessionKey, $data['card']['merchantSessionKey']);
        $this->assertEquals($this->cardIdentifier, $data['card']['cardIdentifier']);
    }
}
