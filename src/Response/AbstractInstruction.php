<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use DateTime;
use Academe\Opayo\Pi\Helper;

/**
 * Value object to hold the void instruction response.
 * Much of this will likely be moved to an abstract once further
 * instruction types are rolled out.
 */

abstract class AbstractInstruction extends AbstractResponse
{
    protected ?string $instructionType = null;
    protected ?DateTime $date = null;

    protected function setData(array|object $data): static
    {
        if ($date = Helper::dataGet($data, 'date')) {
            $this->date = Helper::parseDateTime($date);
        }

        $this->instructionType = Helper::dataGet($data, 'instructionType');

        return $this;
    }

    public function getInstructionType(): ?string
    {
        return $this->instructionType;
    }

    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'instructionType' => $this->getInstructionType(),
            'date' => $this->getDate()?->format(Helper::SAGEPAY_DATE_FORMAT),
            'httpCode' => $this->getHttpCode(),
        ];
    }
}
