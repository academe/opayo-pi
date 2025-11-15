<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi;

use ReflectionClass;
use Psr\Http\Message\MessageInterface;
use Academe\Opayo\Pi\Helper;

/**
 * Shared (Request and Response) abstract message.
 */

abstract class AbstractMessage
{
    /**
     * Get an array of constants in this [late-bound] class, with an optional prefix.
     * @param string|null $prefix
     * @return array
     */
    public static function constantList(?string $prefix = null): array
    {
        $reflection = new ReflectionClass(get_called_class());
        $constants = $reflection->getConstants();

        if (isset($prefix)) {
            $result = [];
            $prefix = strtoupper($prefix);
            foreach ($constants as $key => $value) {
                if (strpos($key, $prefix) === 0) {
                    $result[$key] = $value;
                }
            }
            return $result;
        } else {
            return $constants;
        }
    }

    /**
     * Get a class constant value based on suffix and prefix.
     * Returns null if not found.
     * @param string $prefix
     * @param string $suffix
     * @return mixed
     */
    public static function constantValue(string $prefix, string $suffix): mixed
    {
        $name = strtoupper($prefix . '_' . $suffix);

        if (defined("static::$name")) {
            return constant("static::$name");
        }

        return null;
    }

    /**
     * Parse the body of a PSR-7 message, into a PHP array.
     * TODO: if this message is a ServerRequestInterface, then the parsed body may already
     * be available through getParsedBody() - check that first. Maybe even move that check to
     * AbstractServerRequest and fall back to this (the parent) if not set.
     * @param MessageInterface $message
     * @return mixed
     */
    public static function parseBody(MessageInterface $message): mixed
    {
        return Helper::parseBody($message);
    }
}
