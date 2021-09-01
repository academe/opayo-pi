<?php namespace Academe\Opayo\Pi\Response;

/**
 * A collection of errors, normally validation errors.
 * Once we have  validation errors collected in here, we can sort them into
 * property names (fields), error types etc.
 */

use Psr\Http\Message\ResponseInterface;
use Academe\Opayo\Pi\Helper;

class ErrorCollection extends AbstractCollection
{
    /**
     * The class type that can be added to this collection.
     */
    protected $permittedClass = Model\Error::class;

    /**
     * @param $data
     * @return $this
     */
    protected function setData($data, $httpCode = null)
    {
        if ($httpCode) {
            $this->setHttpCode($httpCode);
        }

        // A list of errors will be provided in a wrapping "errors" element.
        $errors = Helper::dataGet($data, 'errors', null);

        if (is_array($errors)) {
            foreach ($errors as $error) {
                // The $error may be an Error object or an array.
                $this->add(Model\Error::fromData($error, $this->getHttpCode()));
            }
        } else {
            // No list of errors, so take the data as a single error.
            $this->add(Model\Error::fromData($data, $this->getHttpCode()));
        }

        return $this;
    }

    /**
     * Simplified version of what is in the AbstractResponse since we don't need to
     * check if there are any errors returns; we are here because this *is* an error
     * being parsed.
     *
     * @inheritdoc
     */
    public static function fromHttpResponse(ResponseInterface $response)
    {
        return new static($response);
    }

    /**
     * Return errors for a specific property.
     * Use null to return errors without a property reference.
     * Returns ErrorCollection
     *
     * @param null|string $property_name The property name or null to get errors without a property name
     *
     * @return static A collection of zero or more Error objects
     */
    public function byProperty($property_name = null)
    {
        $result = new static();

        foreach ($this as $error) {
            if ($property_name === $error->getProperty()) {
                $result->add($error);
            }
        }

        return $result;
    }

    /**
     * @return array Array of all properties the errors in this collection report on
     */
    public function getProperties()
    {
        $result = array();

        foreach ($this as $error) {
            if (! in_array($error->getProperty(), $result)) {
                $result[] = $error->getProperty();
            }
        }

        return $result;
    }

    /**
     * @return bool True if there are any errors in the collection, otherwise False
     */
    public function hasErrors()
    {
        return $this->count() > 0;
    }

    /**
     * @inheritdoc
     */
    public static function isResponse($data)
    {
        return is_array(Helper::dataGet($data, 'errors'))
            || Helper::dataGet($data, 'status') == 'Error'
            || Helper::dataGet($data, 'status') == 'Invalid'
            || Helper::dataGet($data, 'card-identifier-error-code', '') != '';
    }

    /**
     * @inheritdoc
     */
    public function isError()
    {
        return true;
    }

    /**
     * Reduce the object to an array so it can be serialised.
     * @return array
     */
    public function jsonSerialize()
    {
        $return = [
            'httpCode' => $this->getHttpCode(),
            'errors' => $this->all(),
        ];

        return $return;
    }
}
