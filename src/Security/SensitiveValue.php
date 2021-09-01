<?php

namespace Academe\Opayo\Pi\Security;

use Exception;
use Serializable;
use JsonSerializable;

final class SensitiveValue implements Serializable, JsonSerializable
{
    private $value;

    /**
     * @param mixed $value
     */
    final public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function peek()
    {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function get()
    {
        $value = $this->value;

        $this->erase();

        return $value;
    }

    /**
     *
     */
    public function erase()
    {
        $this->value = null;
    }

    /**
     * {@inheritDoc}
     */
    public function serialize()
    {
        return;
    }

    /**
     * {@inheritDoc}
     */
    public function unserialize($serialized)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function __clone()
    {
        throw new Exception('It is not permitted to clone this object.');
    }

    /**
     * var_dump or print_r (works only for PHP5.6+)
     *
     * @return null for now
     */
    public function __debugInfo()
    {
        return ['value' => gettype($this->value)];
    }
}
