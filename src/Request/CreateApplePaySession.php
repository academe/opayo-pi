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
 *
 * This request differs from Elavon's published OpenAPI description (version
 * 1.1.0), which names the field "domain" and gives "https://www.example.com"
 * as its example. The gateway does neither. Measured on the sandbox,
 * October 2026:
 *
 *   - "domain" is ignored. With or without it, a request that has no
 *     "domainName" is refused with 1003 "Missing mandatory field", property
 *     "domainName".
 *   - "domainName" must be a bare host name. A scheme ("https://...") or a
 *     port is refused with 6125 "Invalid domainName field".
 *   - A well-formed host that is not registered gets 6118 "Domain not registered".
 *
 * The response does use "domainName", as the description says.
 */

class CreateApplePaySession extends AbstractRequest
{
    protected array $resource_path = ['applepay', 'sessions'];

    /**
     * @param Endpoint $endpoint
     * @param Auth $auth
     * @param string $domainName The domain the payment request originates from, as registered with
     *                           Opayo: a bare host name such as "www.example.com", with no scheme
     *                           and no port. Sent as "domainName"; see the class note.
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
