<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

use Academe\Opayo\Pi\Response\ErrorCollection;
use Academe\Opayo\Pi\Response\Payment;
use Academe\Opayo\Pi\Response\PayPalRedirect;
use Academe\Opayo\Pi\Response\Secure3Dv2Redirect;
use LogicException;
use UnexpectedValueException;

/**
 * The next step after a payment, for every payment method.
 *
 * Pass it the response from CreatePayment (or from CreateSecure3Dv2Challenge,
 * or a FetchTransaction for PayPal) and branch on its kind. It only reads the
 * response; response() hands back the original object.
 */
final class PaymentOutcome
{
    private function __construct(
        public readonly OutcomeKind $kind,
        private readonly object $response
    ) {
    }

    /**
     * @throws UnexpectedValueException for any response that is not a payment
     *   outcome, including the retired 3D Secure v1 Secure3DRedirect.
     */
    public static function fromResponse(object $response): self
    {
        $kind = match (true) {
            $response instanceof ErrorCollection => OutcomeKind::Rejected,
            $response instanceof Secure3Dv2Redirect => OutcomeKind::Challenge,
            $response instanceof PayPalRedirect => OutcomeKind::Redirect,
            $response instanceof Payment => OutcomeKind::Finished,
            default => throw new UnexpectedValueException(sprintf(
                'PaymentOutcome cannot classify a %s; expected a payment, 3D Secure v2, PayPal or error response.',
                $response::class
            )),
        };

        return new self($kind, $response);
    }

    public function isFinished(): bool
    {
        return $this->kind === OutcomeKind::Finished;
    }

    public function isChallenge(): bool
    {
        return $this->kind === OutcomeKind::Challenge;
    }

    public function isRedirect(): bool
    {
        return $this->kind === OutcomeKind::Redirect;
    }

    public function isRejected(): bool
    {
        return $this->kind === OutcomeKind::Rejected;
    }

    /**
     * The original response object, untouched.
     */
    public function response(): object
    {
        return $this->response;
    }

    public function transactionId(): ?string
    {
        if ($this->kind === OutcomeKind::Rejected) {
            throw new LogicException('transactionId() is not available on a rejected outcome; there is no transaction.');
        }

        return $this->response->getTransactionId();
    }

    public function isSuccessful(): bool
    {
        $this->expect(OutcomeKind::Finished, __FUNCTION__);

        return $this->response->isSuccessful();
    }

    public function status(): ?string
    {
        $this->expect(OutcomeKind::Finished, __FUNCTION__);

        return $this->response->getStatus();
    }

    public function statusDetail(): ?string
    {
        $this->expect(OutcomeKind::Finished, __FUNCTION__);

        return $this->response->getStatusDetail();
    }

    public function acsUrl(): ?string
    {
        $this->expect(OutcomeKind::Challenge, __FUNCTION__);

        return $this->response->getAcsUrl();
    }

    /**
     * The fields the shopper's browser must POST to acsUrl().
     *
     * @param string|null $threeDSSessionData Returned to your notification URL
     *   untouched; must not be the transactionId (Opayo rejects that).
     * @return array<string, string>
     */
    public function formFields(?string $threeDSSessionData = null): array
    {
        $this->expect(OutcomeKind::Challenge, __FUNCTION__);

        return $this->response->getPaRequestFields($threeDSSessionData);
    }

    public function redirectUrl(): ?string
    {
        $this->expect(OutcomeKind::Redirect, __FUNCTION__);

        return $this->response->getRedirectUrl();
    }

    /**
     * @return list<array{code: string|int|null, description: ?string, property: ?string}>
     */
    public function errors(): array
    {
        $this->expect(OutcomeKind::Rejected, __FUNCTION__);

        $errors = [];
        foreach ($this->response as $error) {
            $errors[] = [
                'code' => $error->getCode(),
                'description' => $error->getDescription(),
                'property' => $error->getProperty(),
            ];
        }

        return $errors;
    }

    /**
     * A plain array of the outcome, safe for any kind: suitable for a session,
     * a log line or a result page.
     *
     * @return array{kind: string, successful: bool, transactionId: ?string, status: ?string, statusDetail: ?string, errors: list<array>}
     */
    public function summary(): array
    {
        $rejected = $this->kind === OutcomeKind::Rejected;

        return [
            'kind' => $this->kind->value,
            'successful' => $this->kind === OutcomeKind::Finished && $this->response->isSuccessful(),
            'transactionId' => $rejected ? null : $this->response->getTransactionId(),
            'status' => $rejected ? null : $this->response->getStatus(),
            'statusDetail' => $rejected ? null : $this->response->getStatusDetail(),
            'errors' => $rejected ? $this->errors() : [],
        ];
    }

    private function expect(OutcomeKind $kind, string $method): void
    {
        if ($this->kind !== $kind) {
            throw new LogicException(sprintf(
                '%s() is only available on a %s outcome; this is a %s outcome.',
                $method,
                $kind->value,
                $this->kind->value
            ));
        }
    }
}
