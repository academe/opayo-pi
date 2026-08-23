# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased] — feature/demo

### Wallet payment methods now match the Opayo Pi API (breaking for wallet users)

The Apple Pay, Google Pay and PayPal request models in earlier releases did not
match the gateway's API (no `merchantSessionKey`, wrong field names, and a PayPal
shape that does not exist), so they could never have produced an accepted request.
They have been rewritten against the Opayo Pi OpenAPI spec v1.1.0 and Elavon's
integration guides, and the field shapes verified against the sandbox.

**Constructor signatures changed, and PHP will not tell you.** All parameters are
strings, so code written against the old signatures still compiles but sends the
values in the wrong fields:

| Class | Old constructor | New constructor |
|---|---|---|
| `ApplePayPayment` | `(clientIpAddress, payload, ?sessionValidationToken)` | `(merchantSessionKey, clientIpAddress, paymentData, ?sessionValidationToken, ?applicationData, ?displayName, ?paymentMethodType)` |
| `GooglePayPayment` | `(clientIpAddress, payload)` | `(merchantSessionKey, clientIpAddress, payload)` |
| `PayPalPayment` | `(clientIpAddress, paypalOrderId, ?payerId)` | `(merchantSessionKey, callbackUrl)` |

Removed: `ApplePayPayment::getPayload()` (now `getPaymentData()`),
`PayPalPayment::getClientIpAddress()/getPaypalOrderId()/getPayerId()/withPayerId()`.
Wire keys: Apple Pay `payload` → `paymentData`; PayPal `clientIpAddress/paypalOrderId/payerId`
→ `merchantSessionKey/callbackUrl`. `fromData()` of JSON stored by the old
release yields empty strings for the new required fields (Apple Pay's old
`payload` key is still read as `paymentData`); re-create wallet payment methods
rather than rehydrating old ones.

Because no working integration can exist against the old shapes, this is shipped
without a major version bump; if you referenced these classes, update the calls.

### Added

- `CreateApplePaySession` / `Response\ApplePaySession` — `POST /applepay/sessions`
  for the Opayo-managed certificate flow (the gateway requires `domainName`,
  although the published spec says `domain`).
- `Response\PayPalRedirect` (status `Redirect`, statusCode 2023) with
  `getRedirectUrl()` / `getOrderId()`; `TransactionStatus::REDIRECT` and
  `AbstractTransaction::STATUS_REDIRECT`.
- `Response\Model\PayPal` and `AbstractTransaction::getPayPal()`: transactions paid
  with PayPal expose `paymentMethod.paypal` (orderId) instead of silently dropping it.
- `ApplePayPayment::fromAppleToken()` and `GooglePayPayment::fromGoogleToken()`
  helpers that do the base64 encoding the gateway expects.
- Request constructors (`CreatePayment`, `CreateDeferred`, `CreateRepeatPayment`,
  `CreateRefund`, `CreateRelease`) accept a `Money\Money` directly as the amount;
  `Amount::toMoney()` and `MoneyAmount::fromAmount()` convert the other way.
- Demo: PayPal tab (`demo/paypal.php`, `demo/paypal-return.php`).

### Fixed

- `MoneyAmount` could not be loaded at all (`getAmount()` return type mismatch
  with `AmountInterface`).
- `Amount::withMajorUnit()` rejected integer-like strings such as `"10"` and
  accepted a bare `"."` as zero.
- `ResponseFactory::fromData()` fell off the end (TypeError) for unrecognised
  data; it now throws `UnexpectedValueException` with the offending body.
- `StrongCustomerAuthentication::withBrowserColorDepth()` accepted strings such
  as `"24px"` via an `(int)` cast; plain integer strings only now.
- Stale documentation URLs (test portal is `https://sandbox.opayo.eu.elavon.com/mysagepay/`).

### Notes on behaviour decisions in this branch (unchanged, flagged by review)

- `TransactionStatus::isSuccess()` returns true for `REGISTERED` and
  `AUTHENTICATED` (Authenticate-type outcomes). `REGISTERED` means the card
  details were secured but 3D Secure failed or was not performed (no liability
  shift) — its `severity()` is `warning`. If you gate fulfilment on
  `isSuccessful()`, check `getStatusEnum()` explicitly for Authenticate flows.
- `Response\Model\Card` defaults a missing `reusable` flag to `false` (the
  Repeat response omits it); treat `false` as "not stated" in that context.
