<?php

namespace Academe\Opayo\Pi;

/**
 * Shared (Request and Response) abstract message.
 */

use ReflectionClass;
use Psr\Http\Message\MessageInterface;
use Academe\Opayo\Pi\Helper;

abstract class AbstractMessage
{
    /**
     * Get an array of constants in this [late-bound] class, with an optional prefix.
     * @param null $prefix
     * @return array
     */
    public static function constantList($prefix = null)
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
     * @param $prefix
     * @param $suffix
     * @return mixed|null
     */
    public static function constantValue($prefix, $suffix)
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
     * @param $message MessageInterface
     * @return array|mixed
     */
    public static function parseBody(MessageInterface $message)
    {
        return Helper::parseBody($message);
    }
}
