<?php namespace Academe\Opayo\Pi\Response;

/**
 * At the moment (12-11-2015 BETA), this resource is the result of a
 * transaction request. It is *not* the details of the transaction
 * that was sent.
 * There is one sub-resource, the Secure3D object, that will be included
 * with this resource automatically so long as the 3D Secure process is
 * final (i.e. no more actions required).
 */

use Academe\Opayo\Pi\Helper;
use Psr\Http\Message\ResponseInterface;

class Repeat extends AbstractTransaction
{
}
