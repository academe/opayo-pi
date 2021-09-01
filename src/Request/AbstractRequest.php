<?php

namespace Academe\Opayo\Pi\Request;

/**
 * Shared message abstract.
 * Contains base methods that request messages will use.
 */

use Academe\Opayo\Pi\AbstractMessage;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Factory\FactoryInterface;
use Academe\Opayo\Pi\Factory\DiactorosFactory;
use Academe\Opayo\Pi\Factory\GuzzleFactory;
use Academe\Opayo\Pi\Factory\RequestFactoryInterface;
use UnexpectedValueException;
use JsonSerializable;
use Exception;
// use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

abstract class AbstractRequest extends AbstractMessage implements JsonSerializable, RequestInterface
{
    use RequestPsr7Trait;

    // Transaction types.
    const TRANSACTION_TYPE_PAYMENT  = 'Payment';
    const TRANSACTION_TYPE_REPEAT   = 'Repeat';
    const TRANSACTION_TYPE_REFUND   = 'Refund';
    const TRANSACTION_TYPE_DEFERRED = 'Deferred';

    // Instruction types.
    const INSTRUCTION_TYPE_VOID     = 'void';
    const INSTRUCTION_TYPE_ABORT    = 'abort';
    const INSTRUCTION_TYPE_RELEASE  = 'release';

    protected $endpoint;
    protected $auth;
    protected $factory;
    protected $resource_path = [];

    /**
     * @var string Most messages are sent with the POST method, so this is the default
     */
    protected $method = 'POST';

    /**
     * @param Auth $auth
     * @return $this
     */
    protected function setAuth(Auth $auth)
    {
        $this->auth = $auth;
        return $this;
    }

    /**
     * @param Auth $auth
     * @return AbstractRequest
     */
    protected function withAuth(Auth $auth)
    {
        $clone = clone $this;
        return $clone->setAuth($auth);
    }

    /**
     * @return mixed
     */
    public function getAuth()
    {
        return $this->auth;
    }

    /**
     * @param Endpoint $endpoint
     * @return $this
     */
    protected function setEndpoint(Endpoint $endpoint)
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    /**
     * @param Endpoint $endpoint
     * @return AbstractRequest
     */
    protected function withEndpoint(Endpoint $endpoint)
    {
        $clone = clone $this;
        return $clone->setEndpoint($endpoint);
    }

    /**
     * @return Endpoint|null
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Support substitution strings; any {fooBar} mapped to $this->getFooBar()
     *
     * @returns array The path of this resource, as an array of path segments
     */
    public function getResourcePath()
    {
        $path = $this->resource_path;

        // Look for segments that need a substitution.
        $subtitution_parameters = preg_grep('/^\{.*\}$/', $path);

        if (! empty($subtitution_parameters)) {
            foreach ($subtitution_parameters as $key => $sub) {
                // The name of the getter method.
                $method_name = 'get' . ucfirst(substr($sub, 1, -1));

                // Replace the value from the getter method.
                $path[$key] = $this->$method_name();
            }
        }

        return $path;
    }

    /**
     * @returns string The fully qualified URL of this resource
     */
    public function getUrl()
    {
        return $this->getEndpoint()->getUrl($this->getResourcePath());
    }

    /**
     * The HTTP Basic Auth header, as an array.
     * Use this if your transport tool does not do "Basic Auth" out of the box.
     * 
     * @return array
     */
    protected function getAuthHeaders()
    {
        return [
            'Authorization' => ['Basic '
                . base64_encode(
                    $this->getAuth()->getIntegrationKey()
                    . ':' . $this->getAuth()->getIntegrationPassword()
                )],
        ];
    }

    /**
     * TODO: can we use the PSR-17 Psr\Http\Message\RequestFactoryInterface
     * instead of our custom factory?
     * 
     * @param RequestFactoryInterface $factory
     * @return $this
     */
    protected function setFactory(RequestFactoryInterface $factory)
    {
        $this->factory = $factory;
        return $this;
    }

    /**
     * @param RequestFactoryInterface $factory
     * @return AbstractRequest
     */
    protected function withFactory(RequestFactoryInterface $factory)
    {
        $clone = clone $this;
        return $clone->setAuth($factory);
    }

    /**
     * Get the PSR-7 factory.
     * Create a factory if none supplied and relevant libraries are installed.
     * 
     * @param bool $exception
     * @return RequestFactoryInterface for example DiactorosFactory or GuzzleFactory
     * @throws Exception
     */
    public function getFactory($exception = false): RequestFactoryInterface
    {
        if (!isset($this->factory) && GuzzleFactory::isSupported()) {
            // If the GuzzleFactory is supported (relevant Guzzle package is
            // installed) then instantiate this factory.

            $this->factory = new GuzzleFactory();
        }

        if (!isset($this->factory) && DiactorosFactory::isSupported()) {
            // If the DiactorosFactory is supported (relevant Zend package is
            // installed) then instantiate this factory.

            $this->factory = new DiactorosFactory();
        }

        // If the exception flag is set, then throw an exception if we do not
        // have a factory.
        // Without the factory we cannot create PSR-7 Requests.

        if ($exception && empty($this->factory)) {
            throw new Exception('No PSR-7 Request factory has been provided.');
        }

        return $this->factory;
    }

    /**
     * Return as a PSR-7 request message.
     * TODO: Use a PSR-17 factory to create the basic request, then add the
     * headers and body to that.
     * 
     * @return \Psr\Http\Message\RequestInterface
     * @throws Exception
     */
    public function createHttpRequest(): RequestInterface
    {
        return $this; // The requests now are now native PSR-7 requests.
    }

    /**
     * Set various flags - anything with a setFoo() method.
     * 
     * @param array $options
     * @return $this
     */
    protected function setOptions(array $options = [])
    {
        foreach ($options as $name => $value) {
            $method = 'set' . ucfirst($name);

            if (method_exists($this, $method)) {
                $this->{$method}($value);
            } else {
                // Unknown option.
                throw new UnexpectedValueException(sprintf('Unknown option "%s"', $name));
            }
        }

        return $this;
    }

    /**
     * Set various flags - anything with a setFoo() method.
     * 
     * @param array $options
     * @return AbstractRequest
     */
    public function withOptions(array $options = [])
    {
        $copy = clone $this;
        return $copy->setOptions($options);
    }
}
