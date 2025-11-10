<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

/**
 * Abstract for shared functionality across "instructions" requests.
 */

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;

abstract class AbstractInstruction extends AbstractRequest
{
    protected string $instructionType;
    protected array $resource_path = ['transactions', '{transactionId}', 'instructions'];

    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        protected readonly string $transactionId
    ) {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);
    }

    public function jsonSerialize(): mixed
    {
        $body = [];

        if (!empty($this->getInstructionType())) {
            $body['instructionType'] = $this->getInstructionType();
        }

        return $body;
    }

    public function getInstructionType(): ?string
    {
        return $this->instructionType ?? null;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }
}
