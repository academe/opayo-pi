<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Enums;

/**
 * How the payment details reached the merchant.
 * Enum equivalent of the CreatePayment::ENTRY_METHOD_* constants, which
 * remain the source of truth for the legacy string API until the package
 * minimum moves to PHP 8.2 and the constants can be derived from these cases.
 */

enum EntryMethod: string
{
    case Ecommerce = 'Ecommerce';
    case MailOrder = 'MailOrder';
    case TelephoneOrder = 'TelephoneOrder';
}
