<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Enums;

/**
 * The reason for bypassing 3D Secure authentication, used together with
 * apply3DSecure:Disable. You must first get permission from your acquirer
 * to use exemptions, and using one shifts fraud liability to the merchant.
 * If the card issuer does not agree with the exemption it can soft-decline
 * (bankResponseCode 65 or 1A), after which Opayo automatically retries the
 * authorisation with 3D Secure authentication data, so the
 * strongCustomerAuthentication object must always be fully populated.
 */

enum ThreeDSExemptionIndicator: string
{
    case LowValue = 'LowValue';
    case TransactionRiskAnalysis = 'TransactionRiskAnalysis';
    case TrustedMerchant = 'TrustedMerchant';
    case SecureCorporatePayment = 'SecureCorporatePayment';
    case DelegatedAuthentication = 'DelegatedAuthentication';
}
