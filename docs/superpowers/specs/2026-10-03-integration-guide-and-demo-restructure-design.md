# Integration guide and demo restructure

Date: 2026-10-03
Status: design approved in conversation; awaiting review of this written spec

## Purpose

A developer installing this package should be able to choose which payment
methods to offer and then follow a clear path for each: what front-end code to
include, what back-end config to set, and which endpoints to host. Today that
path is buried in a demo whose integration code is mixed with diagnostics.

Three deliverables:

1. **`docs/INTEGRATION.md`** - the integration guide. Stands on its own.
2. **The demo, restructured** - the guide applied: "do these things and you can
   take payments with these methods". Every step in the guide appears in the
   demo; nothing in the demo's integration files is absent from the guide.
3. **Small additive helpers in `src/Checkout/`** - so the guide says "call
   this", not "copy this from the demo".

Success: a developer can lift one method's pieces into their own application
without reading demo scaffolding, and deleting `demo/debug/` plus one marked
line leaves exactly the integration the guide describes.

## Hard rules

- **Messages are untouched.** Existing requests, responses, server requests and
  request models keep their behaviour, constructors, public API and
  serialisation. No methods or properties are added to them. New code only
  builds them through existing constructors and reads them through existing
  getters.
- **The existing package test suite passes unchanged.** This is the guard that
  no message broke. (The three `tests/Demo/` files test demo helpers being
  deleted; they are replaced as described under Testing, which is not a change
  to the package suite.)
- **`src/` never reads globals or environment.** No `getenv`, `$_ENV`,
  `$_SERVER`, `$_POST`, `$_GET`, `$_SESSION`. Everything arrives as an explicit
  argument. `.env` is only for running the package standalone: the demo and the
  integration tests.
- **No new dependencies.**
- **Debug tooling is demo-only.** It lives in `demo/debug/`, is never in `src/`,
  is never autoloaded, and is excluded from Composer installs.
- **The agent does not commit.** The user reviews and commits.

## 1. Package additions: `src/Checkout/`

New namespace `Academe\Opayo\Pi\Checkout`.

### `BrowserData`

A value object holding what the shopper's browser reports for 3D Secure:
language, colour depth, screen height, screen width, time-zone offset.

- `BrowserData::fromArray(array $fields): self` - takes the posted fields from
  the caller (it does not read `$_POST`). Missing or empty fields fall back to
  defaults: language `en-GB`, colour depth `24`, screen `0 x 0`, offset `0`.
  Field names are fixed and documented: `browserLanguage`,
  `browserColorDepth`, `browserScreenHeight`, `browserScreenWidth`,
  `browserTz`.
- `toStrongCustomerAuthentication(string $notificationUrl, string $clientIp,
  string $acceptHeader, string $userAgent): StrongCustomerAuthentication` -
  returns an ordinary `StrongCustomerAuthentication` built through its existing
  constructor, with challenge window `Medium`, trans type
  `GoodsAndServicePurchase`, Java disabled, JavaScript enabled.
- The IPv4-only rule moves here: an IPv6 client IP is replaced with
  `127.0.0.1`, as the demo does today, because Opayo's `browserIP` accepts IPv4
  only.

Replaces the demo's `scaFromRequest()`.

### `PaymentOutcome`

`PaymentOutcome::fromResponse(object $response): PaymentOutcome` classifies any
response from `CreatePayment` or `CreateSecure3Dv2Challenge` into exactly one
of four kinds. It reads the response with existing getters only and never
mutates it.

| Kind | Arises from | Exposes |
| ---- | ----------- | ------- |
| `Finished` | `Payment` (authorised or declined) | `isSuccessful()`, `transactionId()`, `status()`, `statusDetail()` |
| `Challenge` | `Secure3Dv2Redirect` | `transactionId()`, `acsUrl()`, `formFields(?string $threeDSSessionData)` (delegates to the response's own `getPaRequestFields()`) |
| `Redirect` | `PayPalRedirect` | `transactionId()`, `redirectUrl()` |
| `Rejected` | `ErrorCollection` | `errors()`: a list of `[code, description, property]` |

- Kind is exposed as a backed enum (`OutcomeKind::Finished` and so on) plus
  `isFinished()`, `isChallenge()`, `isRedirect()`, `isRejected()` for readable
  branching.
- `response()` returns the original response object, so nothing is hidden.
- Any other response type, including the retired 3D Secure v1
  `Secure3DRedirect`, throws `UnexpectedValueException` naming the class. These
  can only come from a request the integrator built deliberately for another
  purpose, so failing loudly is correct.
- One final class. Kind-specific getters called on the wrong kind (for
  example `acsUrl()` on a `Finished` outcome) throw `LogicException`, so a
  branching mistake fails loudly instead of returning a silent null.

Used by the pay endpoint and the 3D Secure notification endpoint alike: this is
the shared result handling for every method.

### Browser-data snippet

`resources/js/browser-data.js`: a small, dependency-free script that fills
hidden inputs named exactly as `BrowserData::fromArray()` expects. Documented
in the guide and used by the demo checkout. A test asserts that the field names
in the script match the names `BrowserData` reads.

### Deliberately not added

- **No token-to-payment-method factory.** Each method is already one line
  through its existing constructor or named constructor:
  `new SingleUseCard(...)`, `GooglePayPayment::fromGoogleToken(...)`,
  `ApplePayPayment::fromAppleToken(...)`, `new PayPalPayment(...)`. The guide
  shows the line per method.
- **No session storage, redirects or HTML.** Those are the application's job;
  the demo shows one way to do them.
- **No "enabled methods" type.** Which methods a site offers is the
  application's own config.

## 2. Demo layout

```text
demo/
  index.php           redirects to checkout.php
  bootstrap.php       loads .env; builds Endpoint, Auth, PSR-18 client; reads
                      the enabled-methods list; one marked debug require line
  checkout.php        order block + one partial per enabled method
  pay.php             CORE: the one pay endpoint
  notification.php    CORE: 3D Secure return (card, Google Pay)
  complete.php        CORE: the single "order complete" page
  methods/
    card.php          front end: sagepay.js drop-in
    googlepay.php     front end: Google Pay button, config from GooglePay\Configuration
    applepay.php      front end: ApplePaySession
    paypal.php        front end: button posting method=paypal
  apple-session.php   APPLE PAY ADD-ON: merchant-validation endpoint
  paypal-return.php   PAYPAL ADD-ON: return endpoint
  debug/              see section 3
  serve-public.sh, serve-public.ps1, README.md
```

### Behaviour

- **`shared.php` and `result.php` are deleted.** Config goes to
  `bootstrap.php`; SCA and outcome handling go to the package; rendering, wire
  recording, probes, account switching and test-card capture go to `debug/`.
- **Sending a request is two plain lines** in integration files:
  `$client->sendRequest($request)` then `ResponseFactory::fromHttpResponse(...)`.
  No wrapper.
- **One landing page.** `pay.php`, `notification.php` and `paypal-return.php`
  each turn their response into a `PaymentOutcome`. For `Finished` and
  `Rejected` they store the outcome's display data in the session and redirect
  (POST/redirect/GET) to `complete.php`. For `Challenge`, `pay.php` stores the
  transaction ID and auto-posts the form to the ACS. For `Redirect`, it stores
  the transaction ID and redirects to PayPal.
- **Apple Pay** posts by `fetch` with `resultFormat=json`; `pay.php` answers
  `{approved: bool, completeUrl: string}` so the sheet completes with the real
  status, then the page navigates to `complete.php`.
- **Method switches are integration config.** `bootstrap.php` reads the
  existing `DEMO_ENABLE_CARD`, `DEMO_ENABLE_GOOGLE_PAY`, `DEMO_ENABLE_APPLE_PAY`,
  `DEMO_ENABLE_PAY_PAL` flags (default on) into a list of enabled methods.
  `checkout.php` includes the partial for each enabled method and shows a
  one-line "not offered" note for the rest.
- **Server-side consistency check.** `pay.php`, `apple-session.php` and
  `paypal-return.php` refuse a method that is switched off. This is a
  consistency measure (the server, not the page, decides what it accepts), not
  protection against forged tokens: Opayo's decryption already rejects those.
  It only matters if a site disables a method locally while it is still enabled
  on the Opayo account.
- **Browser availability checks stay in the partials** (Google
  `isReadyToPay`, Apple `ApplePaySession.canMakePayments`), because a real site
  needs them; on failure the partial shows a placeholder. Server-side probes
  that ask Opayo move to `debug/check.php`.

## 3. Debug layer (`demo/debug/`)

Attached by one marked line in `bootstrap.php`, placed after `.env` is loaded
and before `Auth` and the client are built:

```php
// Demo only: wire panel, setup checks, test tools.
// Delete this line and demo/debug/ to get the bare integration.
if (getenv('DEMO_DEBUG') !== '0') require __DIR__ . '/debug/enable.php';
```

`debug/enable.php` hooks everything in; integration files never call debug
code.

- **Wire panel.** Guzzle middleware on the client records each request and
  response body. Incoming callback data (the 3D Secure `cres`, the PayPal
  return query) and the session contents are recorded too. The panel is
  injected into each page by output buffering just before `</body>`.
- **Setup check page (`debug/check.php`).** Probes each method once: merchant
  session key, Google Pay enrolment, Apple merchant session for this domain,
  PayPal enrolment. Explains each known code with a specific remedy (`4006`,
  `6118`, `6125`, `6203`, `6401`, `1030`), carrying over the per-code hints
  now in `applePayReadiness()`. Linked from the wire panel.
- **Test card page (`debug/test-card.php`).** Server-side card capture with the
  sandbox magic cardholder names (`CHALLENGE`, `SUCCESSFUL`, `NOTAUTH`, ...).
  Tokenises server-side, then posts a `card-identifier` to the normal
  `pay.php`. Refuses to run against the live endpoint.
- **Account switcher.** Overrides the `.env` credential values before
  `bootstrap.php` builds `Auth`.

`DEMO_DEBUG` defaults to on in the demo.

## 4. Integration guide (`docs/INTEGRATION.md`)

`docs/CREDENTIALS-AND-SETUP.md` remains the canonical map of where each
credential comes from; the guide links to it rather than restating it. The
package README gains a short pointer to both, and a line saying the demo is
run by cloning the repository.

Outline:

1. **Choose your methods.** Per method: what the shopper sees, what you host,
   sandbox versus live-only. Then the three layers that decide whether a method
   is "on": your config switch; Opayo enrolment (cannot be queried; a mismatch
   surfaces as `6401`, `1030` or `4006` on the first payment, so verify at
   go-live); the shopper's device (browser checks in each snippet).
2. **The core.** Config (endpoint, auth, PSR-18 client, enabled methods). The
   pay endpoint step by step: merchant session key, payment method (one line
   per method), `BrowserData` to SCA, `CreatePayment`, `PaymentOutcome`.
   Handling the four outcomes. The 3D Secure notification endpoint. The
   browser-data snippet.
3. **Per method** (Card, Google Pay, Apple Pay, PayPal), each under the same
   four headings: Front end; Back-end config; Extra endpoint (or none);
   Testing (what the sandbox proves, what waits for live).
4. **Going live.** Checklist: live endpoint; enrol each method in MyOpayo;
   register the Apple domain; Google production merchant ID; one real payment
   per method, then refund. What to send when reporting a problem: request and
   response bodies.
5. **Worked example.** The demo is this guide applied; a table maps each guide
   section to its demo file. One line mentions the debug layer.

Style: short sections, code over prose, every snippet copied from the real demo
file rather than written separately.

## 5. Packaging

Add `.gitattributes` so Composer dist installs (GitHub archives of tags)
exclude non-package files:

```text
/demo          export-ignore
/tests         export-ignore
/phpunit.xml   export-ignore
/.env.example  export-ignore
```

`docs/` stays in, so the guide sits beside the code in `vendor/`. Applies to
new tags only; source installs (`--prefer-source`, dev branches) still get the
full repository.

## Testing

- **Package additions, test-first:**
  - `BrowserData`: defaults for each missing field; IPv6 replaced, IPv4 kept;
    the produced SCA serialises identically to one built by hand with the same
    values.
  - `PaymentOutcome`: one test per kind from recorded response fixtures
    (authorised `Payment`, declined `Payment`, `Secure3Dv2Redirect`,
    `PayPalRedirect`, `ErrorCollection`, and a 3D Secure completion `Payment`);
    `UnexpectedValueException` for an unrecognised type and for
    `Secure3DRedirect`; `response()` returns the same instance.
  - Snippet field names match `BrowserData::fromArray()`.
- **Existing package suite unchanged and passing.**
- **`tests/Demo/` replaced:** `ScaBuilderTest` becomes `BrowserDataTest`
  against the package class; `DemoFlagsTest` is rewritten against
  `bootstrap.php`'s enabled-methods list; `PaymentMethodFactoryTest` is removed
  with the factory.
- **Bare-integration test:** a test that scans `demo/` and fails if any file
  outside `demo/debug/` references `debug/`, except the single marked line in
  `bootstrap.php`.
- **Sandbox run-through after the restructure,** with debug on and off: card
  without 3D Secure; card with a challenge (`CHALLENGE` via
  `debug/test-card.php`); PayPal to completion; Google Pay to `6203`; Apple
  Pay to `4006`.

## Order of work

1. `src/Checkout/` classes and the snippet, test-first.
2. Demo core files switched onto them (`bootstrap.php`, `checkout.php`,
   `pay.php`, `notification.php`, `complete.php`, add-on endpoints, partials).
3. Debug layer.
4. Delete `shared.php` and `result.php`; replace `tests/Demo/`.
5. `.gitattributes`.
6. Write `docs/INTEGRATION.md` from the finished demo; README pointer;
   rewrite `demo/README.md` to match the new layout (keeping the "Running it
   publicly" section).
7. Sandbox run-through.

## Out of scope

- Deduplicating the wallet story across `TESTING-GUIDE.md`,
  `wallets-handover.md` and `demo/README.md` (the guide links out instead).
- Merchant-managed Apple Pay.
- Any change to existing message classes.
- A runtime way to discover which methods Opayo has enabled (no such API).
