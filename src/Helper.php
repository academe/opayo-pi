<?php namespace Academe\Opayo\Pi;

/**
 * Shared message abstract.
 * Contains base methods that all messages will use.
 */

use Exception;
use UnexpectedValueException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ServerRequestInterface;

use DateTime;
use DateTimeZone;

abstract class Helper
{
    // SagePay date format, ISO 8601 with microseconds.
    // e.g. 2015-08-11T11:45:16.285+01:00

    const SAGEPAY_DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * Get an element from a nested array, nested objects, or mix of the two.
     * The key uses "dot notation" to walk the nested data structure.
     * @param array|object $target The data structure to walk.
     * @param string $key The location of the data in "dot notation"
     * @param mixed $default The value if the key is not found
     * @return mixed
     */
    public static function dataGet($target, $key, $default = null)
    {
        if (is_null($key) || trim($key) == '') {
            return $target;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
                continue;
            }

            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
                continue;
            }

            return static::theValue($default);
        }

        return $target;
    }

    /**
     * @param $value
     */
    protected static function theValue($value)
    {
        if ($value instanceof Closure) {
            return $value();
        } else {
            return $value;
        }
    }

    /**
     * Parse a date, returning a DateTime.
     * @param $date
     * @return DateTime
     */
    public static function parseDateTime($date)
    {
        try {
            if (is_string($date)) {
                // Supplied timestamp string should be ISO 8601 format.
                // Use a default UTC timezone for any relative dates without a timezone that
                // Sage Pay may send. From the "02-09-2016 v1" API all dates will use
                // ISO 8601 format, e.g. "2016-09-26T15:34:37.761000+01:00"

                $datetime = new DateTime($date, new DateTimeZone('UTC'));
            } elseif ($date instanceof DateTime) {
                $datetime = $date;
            } elseif (is_int($date)) {
                // Treat as a unix timestamp.
                $datetime = new DateTime();
                $datetime->setTimestamp($date);
            } else {
                throw new UnexpectedValueException('Unexpected datatype for datetime');
            }
        } catch (Exception $e) {
            throw new UnexpectedValueException('Unexpected time format', $e->getCode(), $e);
        }

        return $datetime;
    }

    /**
     * Parse the body of a PSR-7 message, into a PHP array.
     * TODO: We really need to find a package to do this. It is built into the
     * Guzzle client as helper methods, but this is not specifically a client
     * function.
     *
     * @param $message MessageInterface
     * @return array|mixed
     */
    public static function parseBody(MessageInterface $message)
    {
        // If a ServerRequest object, then parsing will be handled (and cached if necessary)
        // by the implementation.

        if ($message instanceof ServerRequestInterface) {
            return $message->getParsedBody();
        }

        $data = [];

        if ($message->hasHeader('Content-Type')) {
            // Sage Pay returns responses generally with JSON, but the notify callback is Form URL
            // encoded, so we need to parse both.

            if ($message->getHeaderLine('Content-Type') === 'application/x-www-form-urlencoded') {
                parse_str((string)$message->getBody(), $data);
            } elseif ($message->getHeaderLine('Content-Type') === 'application/json') {
                $data = json_decode((string)$message->getBody(), true);
            }
        }

        return $data;
    }

    /**
     * Returns a map of error codes to property names.
     * Used to translate an error code returned by the underlying Sage Pay
     * Direct API to property names in the new REST data structions.
     *
     * @returns array {code, property, message}
     */
    public static function readErrorPropertyMap()
    {
        $source = __DIR__ . '/../data/error-maps.json';

        $json = file_get_contents($source);

        $map = [];

        foreach (json_decode($json) as $message) {
            $map[$message->Code] = [
                'code' => $message->Code,
                'property' => $message->Property,
                'message' => $message->Message,
                'clientMessage' => isset($message->ClientMessage) ? $message->ClientMessage : null,
            ];
        }

        return $map;
    }
}
