<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Enums;

/**
 * The final 3D Secure result for a transaction, as returned by the gateway.
 * Enum equivalent of the Secure3D::STATUS3D_* constants.
 */

enum Secure3DStatus: string
{
    use TryFromInsensitive;

    case AUTHENTICATED = 'Authenticated';
    case NOT_CHECKED = 'NotChecked';
    case NOT_AUTHENTICATED = 'NotAuthenticated';
    case ERROR = 'Error';
    case CARD_NOT_ENROLLED = 'CardNotEnrolled';
    case ISSUER_NOT_ENROLLED = 'IssuerNotEnrolled';
    case MALFORMED_OR_INVALID = 'MalformedOrInvalid';
    case ATTEMPT_ONLY = 'AttemptOnly';
    case INCOMPLETE = 'Incomplete';

    /**
     * Whether 3D Secure authentication fully succeeded.
     * Mirrors Secure3D::isSuccess(); note that AttemptOnly may still
     * carry a liability shift with some acquirers, but is not treated
     * as a success here.
     */
    public function isSuccess(): bool
    {
        return $this === self::AUTHENTICATED;
    }
}
