<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

/**
 * The "cancel" instruction request.
 * Cancel an Authenticate transaction, so that it can no longer be authorised.
 * Use, for example, if the order will not be fulfilled.
 */

class CreateCancel extends AbstractInstruction
{
    protected string $instructionType = AbstractRequest::INSTRUCTION_TYPE_CANCEL;
}
