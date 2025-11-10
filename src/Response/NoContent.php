<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

/**
 * A simple (success) response with no data.
 */

use Psr\Http\Message\ResponseInterface;

class NoContent extends AbstractResponse
{
    /**
     * No data to set (this is an empty messgae body).
     * @param mixed $data
     * @return mixed
     */
    public function setData(mixed $data): mixed
    {
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return [];
    }
}
