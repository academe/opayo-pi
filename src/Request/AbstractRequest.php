<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use Academe\Opayo\Pi\AbstractMessage;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Model\Auth;
use UnexpectedValueException;
use JsonSerializable;
use Psr\Http\Message\RequestInterface;

/**
 * Shared message abstract.
 * Contains base methods that request messages will use.
 */

abstract class AbstractRequest extends AbstractMessage implements JsonSerializable, RequestInterface
{
    use RequestPsr7Trait;

    // Transaction types.
    public const TRANSACTION_TYPE_PAYMENT  = 'Payment';
    public const TRANSACTION_TYPE_REPEAT   = 'Repeat';
    public const TRANSACTION_TYPE_REFUND   = 'Refund';
    public const TRANSACTION_TYPE_DEFERRED = 'Deferred';

    // Instruction types.
    public const INSTRUCTION_TYPE_VOID     = 'void';
    public const INSTRUCTION_TYPE_ABORT    = 'abort';
    public const INSTRUCTION_TYPE_RELEASE  = 'release';

    protected ?Endpoint $endpoint = null;
    protected ?Auth $auth = null;
    protected array $resource_path = [];

    /**
     * Most messages are sent with the POST method, so this is the default
     */
    protected string $method = 'POST';

    /**
     * @param Auth $auth
     * @return self
     */
    protected function setAuth(Auth $auth): self
    {
        $this->auth = $auth;
        return $this;
    }

    /**
     * @param Auth $auth
     * @return static
     */
    protected function withAuth(Auth $auth): static
    {
        $clone = clone $this;
        return $clone->setAuth($auth);
    }

    /**
     * @return Auth|null
     */
    public function getAuth(): ?Auth
    {
        return $this->auth;
    }

    /**
     * @param Endpoint $endpoint
     * @return self
     */
    protected function setEndpoint(Endpoint $endpoint): self
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    /**
     * @param Endpoint $endpoint
     * @return static
     */
    protected function withEndpoint(Endpoint $endpoint): static
    {
        $clone = clone $this;
        return $clone->setEndpoint($endpoint);
    }

    /**
     * @return Endpoint|null
     */
    public function getEndpoint(): ?Endpoint
    {
        return $this->endpoint;
    }

    /**
     * Support substitution strings; any {fooBar} mapped to $this->getFooBar()
     *
     * @return array The path of this resource, as an array of path segments
     */
    public function getResourcePath(): array
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
     * @return string The fully qualified URL of this resource
     */
    public function getUrl(): string
    {
        return $this->getEndpoint()->getUrl($this->getResourcePath());
    }

    /**
     * The HTTP Basic Auth header, as an array.
     * Use this if your transport tool does not do "Basic Auth" out of the box.
     *
     * @return array
     */
    protected function getAuthHeaders(): array
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
     * Return as a PSR-7 request message.
     * The request classes are native PSR-7 requests, so just return $this.
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    public function createHttpRequest(): RequestInterface
    {
        return $this; // The requests are now native PSR-7 requests.
    }

    /**
     * Set various flags - anything with a setFoo() method.
     *
     * @param array $options
     * @return self
     */
    protected function setOptions(array $options = []): self
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
     * @return static
     */
    public function withOptions(array $options = []): static
    {
        $copy = clone $this;
        return $copy->setOptions($options);
    }
}
