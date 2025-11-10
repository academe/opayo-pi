<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

/**
 * The "void" instruction request.
 * Void a successful transaction up until midnight on the day it is processed.
 */

class CreateVoid extends AbstractInstruction
{
    protected string $instructionType = AbstractRequest::INSTRUCTION_TYPE_VOID;
}
