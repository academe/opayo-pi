<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\ServerRequest;

use Academe\Opayo\Pi\Request\AbstractRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * TODO: implement parseBody() here to check getParsedBody() before falling
 * back to parent::parseBody() if not set.
 */

abstract class AbstractServerRequest extends AbstractRequest
{
    /**
     * The 3DSecure resource callback from Sage Pay.
     */
    public function __construct(?ServerRequestInterface $message = null)
    {
        if (isset($message)) {
            $this->setData($this->parseBody($message));
        }
    }

    public static function fromData(mixed $data): static
    {
        $instance = new static();
        return $instance->setData($data);
    }

    abstract protected function setData(mixed $data): mixed;
}
