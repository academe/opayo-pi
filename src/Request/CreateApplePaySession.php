<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request;

use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;

/**
 * Request an Apple Pay merchant session from Opayo (POST /applepay/sessions).
 *
 * Used with the "Opayo manages your certificate" Apple Pay option: when Safari
 * fires ApplePaySession.onvalidatemerchant, your server sends this request and
 * hands the resulting merchant session (Response\ApplePaySession::getMerchantSession())
 * back to the browser for session.completeMerchantValidation(). The response also
 * carries a sessionValidationToken, which must be sent with the transaction in
 * Request\Model\ApplePayPayment.
 *
 * The domain must first be registered against the vendor in MyOpayo
 * (Settings > Pay Methods > Apple Pay > Configure). Authentication is the same
 * Basic auth as for merchant session keys.
 */

class CreateApplePaySession extends AbstractRequest
{
    protected array $resource_path = ['applepay', 'sessions'];

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string $domainName The domain the payment request originates from, as registered with
     *                           Opayo, e.g. "www.example.com". (The published OpenAPI spec calls this
     *                           field "domain", but the gateway requires "domainName".)
     */
    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        protected readonly string $domainName
    ) {
        $this->setEndpoint($endpoint);
        $this->setAuth($auth);
    }

    public function getDomainName(): string
    {
        return $this->domainName;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'vendorName' => $this->getAuth()->getVendorName(),
            'domainName' => $this->domainName,
        ];
    }
}
