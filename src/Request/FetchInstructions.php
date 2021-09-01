<?php

namespace Academe\Opayo\Pi\Request;

/**
 * Fetch instruction applied for the given transaction.
 * TODO: Returns an array of instructions and timestampes, or a 404 if there are none.
 */

class FetchInstructions extends AbstractInstruction
{
    protected $method = 'GET';

    /**
     * Get the message body data for serializing.
     * There is no body data for this message.
     */
    public function jsonSerialize()
    {
    }
}
