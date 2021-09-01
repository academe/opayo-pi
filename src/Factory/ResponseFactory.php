<?php

namespace Academe\Opayo\Pi\Factory;

/**
 * Factory to return the appropriate Response object given
 * the PSR-7 HTTP Response object. This handles a lot of logic,
 * such as checking for errors in a number of different places,
 * and knowing exactly which Response object to create, that the
 * application would otherwise have to deal with.
 */

use Academe\Opayo\Pi\Response\AbstractTransaction;
use Academe\Opayo\Pi\Request\AbstractRequest;
use Psr\Http\Message\ResponseInterface;
use Academe\Opayo\Pi\Response;
use Academe\Opayo\Pi\ServerRequest;
use Academe\Opayo\Pi\Helper;
use Teapot\StatusCode\Http;

class ResponseFactory
{
    /**
     * Return a response instance from a PSR-7 Response message.
     */
    public static function fromHttpResponse(ResponseInterface $response)
    {
        // Decode the body for the returned data.
        $data = Helper::parseBody($response);

        // Get the HTTP status.
        $httpCode = $response->getStatusCode();
        $httpReason = $response->getReasonPhrase();

        // Return the response object.
        return static::fromData($data, $httpCode);
    }

    /**
     * Return a response instance from response data.
     */
    public static function fromData($data, $httpCode = null)
    {
        // An error or error collection.

        if ($httpCode >= Http::BAD_REQUEST || Helper::dataGet($data, 'errors')) {
            // 4xx and 5xx errors.
            // Return an error collection.
            return Response\ErrorCollection::fromData($data, $httpCode);
        }

        // Session key.

        if (Helper::dataGet($data, 'merchantSessionKey') && Helper::dataGet($data, 'expiry')) {
            return Response\SessionKey::fromData($data, $httpCode);
        }

        // A card identifier.

        if (Helper::dataGet($data, 'cardIdentifier') || Helper::dataGet($data, 'card-identifier')) {
            return Response\CardIdentifier::fromData($data, $httpCode);
        }

        // A payment.

        if (Helper::dataGet($data, 'transactionId')) {
            if (Helper::dataGet($data, 'transactionType') == AbstractRequest::TRANSACTION_TYPE_PAYMENT) {
                return Response\Payment::fromData($data, $httpCode);
            }
        }

        // A repeat payment.

        if (Helper::dataGet($data, 'transactionId')) {
            if (Helper::dataGet($data, 'transactionType') == AbstractRequest::TRANSACTION_TYPE_REPEAT) {
                return Response\Repeat::fromData($data, $httpCode);
            }
        }

        // A refund payment.

        if (Helper::dataGet($data, 'transactionId')) {
            if (Helper::dataGet($data, 'transactionType') == AbstractRequest::TRANSACTION_TYPE_REFUND) {
                return Response\Refund::fromData($data, $httpCode);
            }
        }

        // A deferred payment.

        if (Helper::dataGet($data, 'transactionId')) {
            if (Helper::dataGet($data, 'transactionType') == AbstractRequest::TRANSACTION_TYPE_DEFERRED) {
                return Response\Deferred::fromData($data, $httpCode);
            }
        }

        // A failed payment.
        // This isn't documented, but it is a payment with no transactionType.
        // It is returned, for example, when 3DS v2 fails user authentication.
        // Just dump it into a Payment to access isSucess().

        if (Helper::dataGet($data, 'transactionId')) {
            if (Helper::dataGet($data, 'paymentMethod')
                && Helper::dataGet($data, 'amount')
                && Helper::dataGet($data, 'transactionType') === null) {
                return Response\Payment::fromData($data, $httpCode);
            }
        }

        // 3D Secure response.
        // This is the simplest of all the messages - just a status and nothing else.
        // Make sure there is no transactionType field.

        $secure3dStatusList = Response\Secure3D::constantList('STATUS3D');
        $status = Helper::dataGet($data, 'status');

        if ($status && in_array($status, $secure3dStatusList) && ! Helper::dataGet($data, 'transactionId')) {
            return Response\Secure3D::fromData($data, $httpCode);
        }

        // A 3D Secure v1 redirect.
        // Like Secure3D, this one does not have a TransactionType, though shares many fields
        // with the abstract transaction response.

        if (Helper::dataGet($data, 'statusCode') == '2007') {
            if (Helper::dataGet($data, 'status') == AbstractTransaction::STATUS_3DAUTH) {
                return Response\Secure3DRedirect::fromData($data, $httpCode);
            }
        }

        // A 3D Secure v2 challenge (aka a redirect)

        if (Helper::dataGet($data, 'statusCode') == '2021') {
            if (Helper::dataGet($data, 'status') == AbstractTransaction::STATUS_3DAUTH && Helper::dataGet($data, 'cReq')) {
                return Response\Secure3Dv2Redirect::fromData($data, $httpCode);
            }
        }

        // A void instruction.

        if (Helper::dataGet($data, 'instructionType') == AbstractRequest::INSTRUCTION_TYPE_VOID) {
            return Response\VoidInstruction::fromData($data, $httpCode);
        }

        // An abort instruction.

        if (Helper::dataGet($data, 'instructionType') == AbstractRequest::INSTRUCTION_TYPE_ABORT) {
            return Response\Abort::fromData($data, $httpCode);
        }

        // A release instruction.

        if (Helper::dataGet($data, 'instructionType') == AbstractRequest::INSTRUCTION_TYPE_RELEASE) {
            return Response\Release::fromData($data, $httpCode);
        }

        // A list of instructions.

        if (Helper::dataGet($data, 'instructions') && is_array(Helper::dataGet($data, 'instructions'))) {
            return Response\InstructionCollection::fromData($data, $httpCode);
        }

        // A 3DS v2 callback successful result, aka notification (this is a server request).

        if (Helper::dataGet($data, 'cres')) {
            return ServerRequest\Secure3Dv2Notification::fromData($data, $httpCode);
        }

        // A 3DS v1 callback successful result (this is a server request).

        if (Helper::dataGet($data, 'PaRes')) {
            return ServerRequest\Secure3DAcs::fromData($data, $httpCode);
        }

        // A 204 with an empty body is a quiet accpetance that what was send is successful.
        // e.g. returned when a CVV is linked to a card.

        if ($httpCode == 204 && empty($data)) {
            return Response\NoContent::fromData($data, $httpCode);
        }
    }
}
