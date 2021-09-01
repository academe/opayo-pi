<?php

namespace Academe\Opayo\Pi\Response;

/**
 * Value object to hold the void instruction response.
 * Much of this will likely be moved to an abstract once further
 * instruction types are rolled out.
 */

use Psr\Http\Message\ResponseInterface;
use Academe\Opayo\Pi\Helper;

abstract class AbstractInstruction extends AbstractResponse
{
    protected $instructionType;
    protected $date;

    /**
     * @param array|object $data The parsed data returned by Sage Pay.
     * @return $this
     */
    protected function setData($data)
    {
        if ($date = Helper::dataGet($data, 'date')) {
            $this->date = Helper::parseDateTime($date);
        }

        $this->instructionType = Helper::dataGet($data, 'instructionType');

        return $this;
    }

    public function getInstructionType()
    {
        return $this->instructionType;
    }

    public function getDate()
    {
        return $this->date;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'instructionType' => $this->getInstructionType(),
            'date' => $this->getDate() ? $this->getDate()->format(Helper::SAGEPAY_DATE_FORMAT) : null,
            'httpCode' => $this->getHttpCode(),
        ];
    }
}
