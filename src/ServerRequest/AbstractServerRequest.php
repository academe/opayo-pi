<?php

namespace Academe\Opayo\Pi\ServerRequest;

/**
 * TODO: implement parseBody() here to check getParsedBody() before falling
 * back to parent::parseBody() if not set.
 */

use Academe\Opayo\Pi\Request\AbstractRequest;
use Psr\Http\Message\ServerRequestInterface;

abstract class AbstractServerRequest extends AbstractRequest
{
    /**
     * @param ServerRequestInterface $message The 3DSecure resource callback from Sage Pay.
     */
    public function __construct(ServerRequestInterface $message = null)
    {
        if (isset($message)) {
            $this->setData($this->parseBody($message));
        }
    }

    /**
     * @param $data
     * @return mixed
     */
    public static function fromData($data)
    {
        $instance = new static();
        return $instance->setData($data);
    }

    /**
     * @param $data
     */
    abstract protected function setData($data);
}
