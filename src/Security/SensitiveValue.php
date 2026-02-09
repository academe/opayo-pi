<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Security;

use Exception;
use JsonSerializable;

final class SensitiveValue implements JsonSerializable
{
    /**
     * @param mixed $value
     */
    final public function __construct(
        private mixed $value
    ) {
    }

    /**
     * @return mixed
     */
    public function peek(): mixed
    {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function get(): mixed
    {
        $value = $this->value;

        $this->erase();

        return $value;
    }

    public function erase(): void
    {
        $this->value = null;
    }

    /**
     * Serialize the object (returns empty array to avoid serializing sensitive data)
     */
    public function __serialize(): array
    {
        return [];
    }

    /**
     * Unserialize the object (no-op, as sensitive data is not persisted)
     */
    public function __unserialize(array $data): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString(): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function __clone(): void
    {
        throw new Exception('It is not permitted to clone this object.');
    }

    /**
     * var_dump or print_r
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return ['value' => gettype($this->value)];
    }
}
