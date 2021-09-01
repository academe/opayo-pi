<?php

namespace Academe\Opayo\Pi\Response;

/**
 * Result of a Payment request where payment is approved or declined.
 * See Secrure3DRedirect for when the result is 3D Secure redirect.
 */

class Payment extends AbstractTransaction
{
    /**
     * @inheritdoc
     */
    public function isSuccess()
    {
        return $this->getStatus() == static::STATUS_OK;
    }
}
