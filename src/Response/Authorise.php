<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response;

/**
 * Result of an Authorise request (Request\CreateAuthorise) against an earlier
 * Authenticate transaction: the funds were approved or declined.
 */

class Authorise extends Payment
{
}
