<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

use ArrayIterator;
use InvalidArgumentException;
use Traversable;

/**
 * A a collection of instructions.
 */

abstract class AbstractCollection extends AbstractResponse implements \IteratorAggregate
{
    /**
     * The list of items.
     */
    protected array $items = [];

    /**
     * The class type that can be added.
     */
    protected ?string $permittedClass = null;

    /**
     * @return ArrayIterator
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Add a new item to the collection.
     * This collection is not a value object. Perhaps it should be: withError()?
     *
     * @param object $item An object to add
     */
    public function add(object $item): void
    {
        if (! empty($this->permittedClass) && ! $item instanceof $this->permittedClass) {
            throw new InvalidArgumentException(sprintf(
                'Item to be added to collection must be of type "%s"',
                $this->permittedClass
            ));
        }

        $this->items[] = $item;
    }

    /**
     * @return int Count of instructions in the collection
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return array all instructions in the collection.
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return object|false The first item in the collection.
     */
    public function first(): object|false
    {
        return reset($this->items);
    }
}
