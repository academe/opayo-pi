<?php

namespace Academe\Opayo\Pi\Response;

/**
 * A a collection of instructions.
 */

abstract class AbstractCollection extends AbstractResponse implements \IteratorAggregate
{
    /**
     * The list of items.
     * @var array
     */
    protected $items = [];

    /**
     * The class type that can be added.
     */
    protected $permittedClass;

    /**
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Add a new item to the collection.
     * This collection is not a value object. Perhaps it should be: withError()?
     *
     * @param Object $item An object to add
     */
    public function add($item)
    {
        if (! empty($this->permittedClass) && ! $item instanceof $this->permittedClass) {
            throw new \InvalidArgumentException(sprintf(
                'Item to be added to collection must be of type "%s"',
                $this->permittedClass
            ));
        }

        $this->items[] = $item;
    }

    /**
     * @return int Count of instructions in the collection
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * @return array all instructions in the collection.
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * @return Object The first item in the collection.
     */
    public function first()
    {
        return reset($this->items);
    }
}
