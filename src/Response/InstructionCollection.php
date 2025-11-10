<?php

declare(strict_types=1);

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
    protected string $permittedClass = AbstractInstruction::class;

    /**
     * @param mixed $data
     * @param int|string|null $httpCode
     * @return void
     */
    public function setData(mixed $data, int|string|null $httpCode = null): void
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
    public function jsonSerialize(): mixed
    {
        return [
            'instructions' => $this->items,
        ];
    }
}
