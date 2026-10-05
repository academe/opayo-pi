<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Model;

use PHPUnit\Framework\TestCase;

class AdditionalDeclineDetailTest extends TestCase
{
    /**
     * As returned by the sandbox for the "declined by the bank" test card.
     */
    protected array $data = [
        'additionalDeclineCode' => '03',
        'additionalDeclineCodeDescription' => 'DECLINED',
        'additionalDeclineCodeCategory' => '03',
    ];

    public function testFromData()
    {
        $detail = AdditionalDeclineDetail::fromData($this->data);

        $this->assertSame('03', $detail->getCode());
        $this->assertSame('DECLINED', $detail->getDescription());
        $this->assertSame('03', $detail->getCategory());
    }

    public function testFromDataInsideWrapper()
    {
        $detail = AdditionalDeclineDetail::fromData(['additionalDeclineDetail' => $this->data]);

        $this->assertSame('DECLINED', $detail->getDescription());
    }

    public function testFromJsonString()
    {
        $detail = AdditionalDeclineDetail::fromData(json_encode($this->data));

        $this->assertSame('03', $detail->getCode());
    }

    public function testMissingFieldsAreNull()
    {
        $detail = AdditionalDeclineDetail::fromData(['additionalDeclineCode' => 'N7']);

        $this->assertSame('N7', $detail->getCode());
        $this->assertNull($detail->getDescription());
        $this->assertNull($detail->getCategory());
    }

    public function testSerializesBackToTheGatewayShape()
    {
        $detail = AdditionalDeclineDetail::fromData($this->data);

        $this->assertSame(['additionalDeclineDetail' => $this->data], $detail->getData());
        $this->assertSame(['additionalDeclineDetail' => $this->data], $detail->jsonSerialize());
    }
}
