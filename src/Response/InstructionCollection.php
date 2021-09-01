<?php

namespace Academe\Opayo\Pi\Response;

/**
 * A a collection of instructions.
 */

use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Helper;

class InstructionCollection extends AbstractCollection
{
    /**
     * The class type that can be added to this collection.
     */
    protected $permittedClass = AbstractInstruction::class;

    /**
     *
     */
    public function setData($data, $httpCode = null)
    {
        if ($httpCode) {
            $this->setHttpCode($httpCode);
        }

        // A list of errors will be provided in a wrapping "errors" element.
        $instructions = Helper::dataGet($data, 'instructions', null);

        if (is_array($instructions)) {
            // The instructions will hopefully be an array of instruction objects.
            // Use the ResponseFactory to decide what each one is, and create the
            // appropriate object.

            foreach ($instructions as $instruction) {
                $this->add(ResponseFactory::fromData($instruction, $httpCode));
            }
        }
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'instructions' => $this->items,
        ];
    }
}
