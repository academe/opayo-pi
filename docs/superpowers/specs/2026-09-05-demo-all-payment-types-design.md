# Demo redesign: one checkout page offering all payment types

Status: design approved in chat, awaiting spec review
Branch: feature/demo
Date: 2026-09-05

## Goal

Replace the current tab-per-method demo (pick a payment type first, then see its
form) with a single checkout page that offers every payment type at once, the way a
real checkout does. Each method's demo code clearly shows how that method is wired.
A method that cannot be used right now still appears, as a placeholder that states
the reason.

This serves the package's aim (`[[package-aim]]`): an integrator should be able to
read one file per method and copy it.

## Background

The current `demo/index.php` (444 lines) branches on `?js=`, `?paypal=`,
`?googlepay=` query flags and renders one method per page load, with a nav to switch.
`googlepay.php` and `paypal.php` are separate top-level endpoints, each with its own
copy of the SCA block and the 3DS/result rendering. `pay.php` handles cards.

The wallet analysis from earlier sessions is captured in `docs/wallets-handover.md`
(the token is the package boundary; SCA must always be sent because you cannot know
in advance whether a Google `PAN_ONLY` token will be challenged) and
`docs/google-pay-key-custody.html` (why no certificate is issued for Google Pay).

The library already models the **Opayo-managed** Apple Pay path:
`CreateApplePaySession` (`POST /applepay/sessions`), `Response\ApplePaySession`
(carries `merchantSession` + `sessionValidationToken`), and `ApplePayPayment`. The
handover doc assumed the merchant-managed path (server holds an identity cert); the
Opayo-managed path is simpler and is what the demo will use — no cert files, domain
registered in MyOpayo.

## File structure

```
demo/
  index.php            checkout: order fields once, then four method panels
  pay.php              ONE endpoint, all methods -> CreatePayment
  notification.php     3DS callback (unchanged)
  paypal-return.php    PayPal callback (unchanged)
  apple-session.php    onvalidatemerchant -> CreateApplePaySession        (new)
  shared.php           config, client, wire panel, readiness probes,
                       payment-method factory, outcome rendering          (grows)
  methods/
    card.php           drop-in by default; server-side capture toggle inside  (new)
    googlepay.php      library Configuration -> sheet -> token               (new; replaces top-level)
    applepay.php       sessions probe -> ApplePaySession -> token            (new)
    paypal.php         one button, redirect flow                             (new; replaces top-level)
```

Top-level `googlepay.php` and `paypal.php` are removed; their logic moves into
`pay.php` (server side) and `methods/*.php` (browser side). The account selector
(env / sandbox) and the wire panel are unchanged.

## Readiness model

Each panel answers "can this shopper pay this way, right now?" in four layers. A
method that cannot run is shown greyed with the reason, never hidden. Rule: **a
placeholder never lies about what it doesn't know** — Google Pay "ready" means
Google is ready, not that Opayo is enrolled.

| Layer | When | Who answers | Card | Google Pay | Apple Pay | PayPal |
|---|---|---|---|---|---|---|
| Demo flag | render | `.env` `DEMO_ENABLE_*` | flag | flag | flag | flag |
| Config | render | `.env` creds | always | `gatewayMerchantId`? | domain set? | always |
| Gateway | render | Opayo | session key ok? | *(no pre-flight)* | `POST /applepay/sessions` -> session or `6118` | *(none)* |
| Browser | load | JS | - | `isReadyToPay` | `canMakePayments` (Safari) | - |

- **Demo flag** is the top layer and short-circuits the rest: a disabled method shows
  a one-line "switched off in .env" note and runs no probe (so Apple's sessions call
  is skipped when `DEMO_ENABLE_APPLE_PAY=0`).
- **Card** is the baseline. The merchant session key is created at render; if that
  fails, the whole page says so once at the top.
- **Google Pay** has no Opayo pre-flight (a real pre-flight means posting a junk
  transaction and reading `6401` vs `6203`, which leaves rejected records in MyOpayo —
  wrong for page load; belongs in a separate `scripts/check-wallets.php` diagnostic,
  out of scope here). The live panel carries one honest line: Opayo enrolment is only
  tested by paying.
- **Apple Pay** is the one method whose render-time probe is a real Opayo call. On
  `127.0.0.1` it shows `6118 Domain not registered`; in Chrome it also notes "needs
  Safari on an Apple device". Both clear once a registered domain serves the page.
- **PayPal** is always offered; its enrolment failure (`1030`) only appears after
  redirect, as today.

Every probe and its raw response is recorded in the wire panel, so the page-load
section of the wire panel reads as a readiness report.

## .env additions

```
# Which methods the demo offers. All on by default; switch off any you are not
# working on. A method that is off shows a one-line note rather than disappearing.
DEMO_ENABLE_CARD=1
DEMO_ENABLE_GOOGLE_PAY=1
DEMO_ENABLE_APPLE_PAY=1
DEMO_ENABLE_PAYPAL=1
```

`DEMO_` prefix, not `OPAYO_`, so it never reads as "Opayo has this enabled on my
vendor" — the confusion the placeholders exist to prevent. Added to `.env.example`
with these defaults. Apple Pay also needs a domain setting for the sessions probe;
add `OPAYO_APPLE_PAY_DOMAIN` (falls back to the request host).

## pay.php — the one endpoint

Reads top to bottom as the flow the handover describes:

```php
// 1. What did the browser send? One credential, never two.
$paymentMethod = paymentMethodFromRequest($_POST, $sessionKey);
//   card-identifier  -> SingleUseCard
//   googlePayToken   -> GooglePayPayment::fromGoogleToken()
//   applePayToken    -> ApplePayPayment (+ sessionValidationToken)
//   method=paypal    -> PayPalPayment(callbackUrl)

// 2. Same request for every method.
$request = new CreatePayment(..., $paymentMethod, ..., options: [
    'entryMethod' => EntryMethod::Ecommerce,
    'strongCustomerAuthentication' => scaFromRequest($_POST),   // ALWAYS
    'apply3DSecure' => ...                                       // card panel checkbox only
]);

// 3. Same response handling for every method. Pi decides, not you.
match (true) {
    $response instanceof Secure3Dv2Redirect => renderAcsRedirect($response),    // -> notification.php
    $response instanceof PayPalRedirect     => renderPayPalRedirect($response), // -> paypal-return.php
    $response instanceof ErrorCollection    => renderErrors($response),
    default                                 => renderResult($response),
};
```

Two handover principles become code here: **SCA is always sent** (a Google
`PAN_ONLY` token may be challenged and you cannot know in advance), and the only
method-specific branch is step 1. `renderErrors` keeps the existing teaching
explanations (`6203`, `6401`, `1030`).

Helpers (`paymentMethodFromRequest`, `scaFromRequest`, `renderAcsRedirect`,
`renderPayPalRedirect`, `renderErrors`, `renderResult`) live in `shared.php`, written
as functions with clean inputs so that logic which turns out to be generic
("given this response, what's the next step?") can graduate into the package later.
Kept in the demo for now.

The `use3ds` checkbox stays card-only: forcing 3DS on a `CRYPTOGRAM_3DS` wallet token
would be wrong.

## Card panel

Drop-in (`sagepay.js`) is the default and the thing to copy. A toggle inside the card
panel reveals server-side capture (raw PAN to `pay.php`), kept because it is the only
way to drive the sandbox's magic cardholder names without typing into Opayo's iframe.
Both code paths stay live and visible; the drop-in is visually the recommended one.

## Apple Pay depth

Build the real Opayo-managed path, not a stub: `apple-session.php` handling
`onvalidatemerchant` via `CreateApplePaySession`, `ApplePaySession` JS on the page,
`ApplePayPayment` (with `sessionValidationToken`) in the factory. The server half
(`POST /applepay/sessions` -> `6118` on `127.0.0.1`) is verifiable now. The browser
half (`ApplePaySession`, `canMakePayments`, sheet, token shape) is written from
Apple's and Opayo's docs and **labelled unverified**; the user has an iPhone and will
register a public domain later. README gets a "verify Apple Pay on your iPhone"
section with exact steps.

## Testing and verification

| Layer | How verified | By whom |
|---|---|---|
| `paymentMethodFromRequest`, `scaFromRequest`, outcome `match` | Unit tests: shaped `$_POST` in, right method/request out | now |
| Card (drop-in + server-side), 3DS challenge | Live against sandbox | now |
| Google Pay panel -> `6203` | Live with placeholder token; button renders headless | now |
| Apple Pay server probe -> `6118` | Live `POST /applepay/sessions` from `127.0.0.1` | now |
| PayPal redirect (2023) | Live to sandbox | now |
| Google real token; Apple sheet + token | From docs, labelled unverified | user, later |

## Out of scope

- `scripts/check-wallets.php` Google enrolment diagnostic (noted, not built here).
- Merchant-managed Apple Pay certificate path (Opayo-managed is simpler and modelled).
- Graduating helper logic into the `src/` package (kept in demo; revisit later).
- Repeat/recurring from wallet parents (handover open point 5).

## Open points inherited from handover (verify against local vendor docs)

1. Exact `paymentMethod` field names for Apple Pay token in `/transactions` (the
   library's `ApplePayPayment` models `paymentData`; confirm against
   `docs/vendor/opayo/` once Apple Pay page is fetched).
2. Whether Opayo ever returns `3DAuth` for an Apple Pay token (assume no; SCA already
   satisfied device-side).
