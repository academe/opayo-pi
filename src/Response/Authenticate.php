<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

/**
 * Result of an Authenticate request (Request\CreateAuthenticate): the card and
 * cardholder were checked and no funds were reserved or taken.
 *
 * The status is "Authenticated" when the cardholder passed 3D Secure, and
 * "Registered" when they did not, or it was not performed, but the card
 * details were stored anyway. isSuccessful() is true for both, since either
 * can be authorised; use isAuthenticated() when the liability shift matters.
 * See Secure3Dv2Redirect for when the result is a 3D Secure challenge.
 */

class Authenticate extends Payment
{
    /**
     * The cardholder passed 3D Secure.
     */
    public function isAuthenticated(): bool
    {
        return $this->getStatusEnum() === TransactionStatus::AUTHENTICATED;
    }

    /**
     * The card details are stored, but 3D Secure failed or was not performed:
     * there is no liability shift.
     */
    public function isRegistered(): bool
    {
        return $this->getStatusEnum() === TransactionStatus::REGISTERED;
    }
}
