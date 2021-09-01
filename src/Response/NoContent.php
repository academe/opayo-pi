<?php

namespace Academe\Opayo\Pi\Response;

/**
 * A simple (success) response with no data.
 */

use Psr\Http\Message\ResponseInterface;

class NoContent extends AbstractResponse
{
    /**
     * No data to set (this is an empty messgae body).
     */
    public function setData($data)
    {
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [];
    }
}
