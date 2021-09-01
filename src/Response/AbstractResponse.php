<?php namespace

Academe\Opayo\Pi\Response;

/**
 * Shared message abstract.
 */

use Academe\Opayo\Pi\Response\ErrorCollection;
use Academe\Opayo\Pi\AbstractMessage;
use Psr\Http\Message\ResponseInterface;
use Academe\Opayo\Pi\Helper;
use JsonSerializable;

// Teapot here provides HTTP response code constants.
// Not sure why RFC4918 is not included in Http; it contains some responses we expect to get.
use Teapot\StatusCode\RFC\RFC4918;
use Teapot\StatusCode\Http;

abstract class AbstractResponse extends AbstractMessage implements Http, RFC4918, JsonSerializable
{
    /**
     * @var integer The HTTP response code.
     */
    protected $httpCode;

    /**
     * The remote status of the returned object.
     */
    protected $status = null;

    /**
     * Can initialise with a PSR7 message, an array, a value object or a JSON string.
     *
     * @param array|object|ResponseInterface $init The data returned from SagePay in the response body.
     */
    public function __construct($init, $httpCode = null)
    {
        $this->setHttpCode($httpCode);

        if ($init instanceof ResponseInterface) {
            $this->setHttpResponse($init);
        } elseif (is_string($init) || is_array($init) || is_object($init)) {
            $this->setData($init, $httpCode);
        }
    }

    /**
     * Create an instance of this class from data, either from Sage Pay or
     * from storage (e.g. the session).
     *
     * @param array|object|string $data
     * @param null|string $httpCode
     * @return static
     */
    public static function fromData($data, $httpCode = null)
    {
        // Just a convenience conversion.
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return new static($data, $httpCode);
    }

    /**
     * Construct an instance from a PSR-7 response message.
     *
     * Here we can check for errors and return the appropriate error or error
     * collection object instead, avoiding the need for the factory every time?
     *
     * @param ResponseInterface $response
     * @returns static|ErrorCollection
     */
    public static function fromHttpResponse(ResponseInterface $response)
    {
        $httpCode = $response->getStatusCode();
        $data = static::parseBody($response);

        if ($httpCode >= Http::BAD_REQUEST || Helper::dataGet($data, 'errors')) {
            // 4xx and 5xx errors.
            // Return an error collection.
            return ErrorCollection::fromHttpResponse($response);
        }

        return static::fromData($data, $httpCode);
    }

    /**
     * Set attributes from a PSR-7 response message.
     *
     * @param ResponseInterface $response
     */
    protected function setHttpResponse(ResponseInterface $response)
    {
        $this->setData($this->parseBody($response));
        $this->setHttpCode($response->getStatusCode());

        return $this;
    }

    /**
     * @return integer The HTTP status code for the response.
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /**
     * Set the httpCode only if not null.
     * @param integer|null $code The HTTP status code for the response
     */
    protected function setHttpCode($code)
    {
        if (isset($code)) {
            $this->httpCode = (int) $code;
        }
    }

    /**
     * @param integer $code The HTTP status code for the response.
     *
     * @return self Clone of $this with the HTTP code set.
     */
    public function withHttpCode($code)
    {
        $clone = clone $this;
        $clone->setHttpCode($code);
        return $clone;
    }

    /**
     * There is a status (e.g. Ok), a statusCode (e.g. 2007), and a statusDetail (e.g. Transaction authorised).
     * Also there is a HTTP return code (e.g. 202). All are needed in different contexts.
     * However, there is a hint that the "status" may be removed, relying on the HTTP return code instead.
     * @return string The overall status string of the transaction.
     */
    public function getStatus()
    {
        // Enforce the correct capitalisation.

        $statusValue = $this->constantValue('STATUS', $this->status);

        return ! empty($statusValue) ? $statusValue : $this->status;
    }

    /**
     * Set properties from an array or object of values.
     * This response will be returned either embedded into a Payment (if 3DSecure is not
     * enabled, or a Payment is being fetched from storage) or on its own in response to
     * sending the paRes to Sage Pay.
     *
     * @param $data
     * @return $this
     */
    abstract protected function setData($data);

    /**
     * Indicate whether the response is an error or not.
     * CHECKME: distinguish between transaction failures and errors in the messages.
     * @return boolean True if the response is an error collection.
     */
    public function isError()
    {
        return false;
    }

    /**
     * Indicate whether the response is a 3D Secure redirect.
     * @return boolean True if the response is a Secure3DRedirect.
     */
    public function isRedirect()
    {
        return false;
    }

    /**
     * Indicate whether the authorisation or 3D Secure password was successful.
     * @return boolean True if the response is a successful (in context) transaction result.
     */
    public function isSuccess()
    {
        return false;
    }
}
