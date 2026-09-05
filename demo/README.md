# Opayo Pi Demo

A minimal payment demo running on the PHP built-in server against the
Opayo sandbox. No SSL and no public URL are needed: all gateway calls are
outbound HTTPS from PHP, and the 3D Secure challenge result is returned by
the shopper's *browser* (not by Opayo's servers), so `localhost` is
reachable for the notification hop.

Each page shows the forms and actions on the left, and the raw wire data
(request and response bodies, plus the PHP session contents) on the right.

## Running

1. Copy `.env.example` to `.env` in the repository root and add your Opayo
   test account credentials (the same file the integration tests use).
2. From the repository root:

   ```bash
   php -S 127.0.0.1:8000 -t demo
   ```

3. Open <http://127.0.0.1:8000>.

Use `127.0.0.1`, not `localhost`: Opayo's URL validation rejects bare
`localhost` in the 3D Secure notification URL (it requires a dotted
hostname), and the demo redirects you accordingly.

## What it demonstrates

| Choice | How |
|--------|-----|
| Server-side card capture | Default tab; test card details are posted to `pay.php`, which tokenises them via the API. Sandbox/test cards only — never do this with real cards. |
| Opayo JS drop-in | Second tab; `sagepay.js` renders the card fields and tokenises in the browser, so card details never reach PHP. |
| Without 3D Secure | Leave the checkbox off: `apply3DSecure: Disable`, one round trip, result rendered immediately. |
| With 3D Secure v2 | Tick the checkbox: `apply3DSecure: Force` plus a `strongCustomerAuthentication` object built from real browser details. The browser POSTs the `creq` to the sandbox ACS, completes the challenge, and is returned to `notification.php`, which forwards the `cres` to Opayo. |
| PayPal | Third tab; no card. `paypal.php` registers the transaction with `paymentMethod.paypal = {merchantSessionKey, callbackUrl}`, gets a `Redirect` (2023) response with a PayPal URL, and sends the browser there. PayPal returns the shopper to Opayo, which redirects to `paypal-return.php?transactionId=…`; that page fetches the transaction for the outcome. Needs the public sandbox vendor (PayPal enabled) and a PayPal *sandbox buyer* login to approve. |

## Files

- `shared.php` — env loading, auth/endpoint/client helpers, the wire-data
  recorder and the two-column page layout.
- `index.php` — the payment form (card capture modes, PayPal and Google Pay).
- `pay.php` — tokenises (if needed), creates the payment, and either shows
  the result or hands the browser to the 3D Secure challenge.
- `notification.php` — receives the challenge result and completes the
  transaction.
- `paypal.php` — registers a PayPal payment and redirects to PayPal.
- `paypal-return.php` — the PayPal callback: fetches the transaction by the
  `transactionId` Opayo appends to the URL and shows the result.
- `googlepay.php` — takes the token the Google Pay sheet minted in the browser
  and sends it as `paymentMethod.googlePay`.

## Wallets

Every wallet needs the vendor to have it enabled in MyOpayo, otherwise the
gateway answers `6401 Wallet not enabled for the vendor`. The public `sandbox`
profile has both PayPal and Google Pay enabled. Personal test accounts have
neither by default, but Opayo will enable Google Pay on request - they ask for
your Google merchant ID from the Google Pay & Wallet Console to do it. Once
enabled, your own vendor reaches the same point the sandbox does (`6203`), so
enrolment is worth doing even though it does not on its own make the flow
completable.

**PayPal** runs end to end here — it is a redirect, so no token has to be minted
in the browser.

**Google Pay** has a tab, and gets as far as the gateway reading the payload.
The Google Pay sheet only mints a real, encrypted token in its `PRODUCTION`
environment; in `TEST` it returns the fixed placeholder
`examplePaymentMethodToken`, which Opayo rejects with `6203 Invalid Google Pay
payload` (verified against the sandbox, both bare and JSON-wrapped). So the tab
demonstrates the whole integration — sheet, token, `merchantSessionKey`,
`paymentMethod.googlePay`, and the wire data for all of it — but cannot be
authorised. Going further needs a Google merchant ID from the Google Pay &
Wallet Console and an allowlisted HTTPS origin, neither of which applies to
`127.0.0.1`. Note the sheet opens a popup, so a headless browser cannot complete
it either; use a real browser signed into a Google account.

There is no Google Pay certificate or secret to ask Elavon for, and no test
credential that would change the above. Opayo Pi uses Google's
`tokenizationSpecification.type = PAYMENT_GATEWAY` with `gateway: 'opayoelavon'`,
which means Google encrypts the card data to *Elavon's* key: they are the
recipient, and the merchant is only a courier for an opaque blob. All you get is
the `gatewayMerchantId`, which is an identifier rather than a secret - it is
fine in page source, and this demo puts it there. Merchant-held key material
exists only in the other mode, `type: DIRECT`, where you generate a P-256 key
pair and register the public half in the Google Pay & Wallet Console; the Opayo
API does not accept that shape. This is the opposite of Apple Pay below, which
does have a certificate story, and is why the question comes up.
`docs/google-pay-key-custody.html` walks through all of this with diagrams.

**Apple Pay** has no tab. Beyond the wallet being enabled it needs Safari on an
Apple device signed into a sandbox-tester Apple ID, plus a registered HTTPS
domain (Opayo-managed certificate) or an Apple merchant certificate
(merchant-managed), and the shared sandbox vendor has no domain you can
register. The library still models it to the API reference (`ApplePayPayment`,
`CreateApplePaySession` / `ApplePaySession`), and the sandbox magic amounts are
listed in `docs/TESTING-GUIDE.md`.

Two optional `.env` settings feed the Google Pay tab; both have workable
defaults, and the field on the form overrides the first:

    OPAYO_GOOGLE_PAY_MERCHANT_ID=   # gatewayMerchantId from MyOpayo (defaults to the vendor name)
    GOOGLE_PAY_MERCHANT_ID=         # Google merchant ID, only read in PRODUCTION

## Test cards

| Card | Number | CVV |
|------|--------|-----|
| Visa | `4929000000006` | `123` |
| MasterCard | `5404000000000001` | `123` |

Any future expiry date (MMYY). See `docs/TESTING-GUIDE.md` for more.

## Magic cardholder names (3D Secure v2 outcomes)

With 3D Secure on, the sandbox picks the authentication outcome from the
*cardholder name* on the card identifier:

| Name | Outcome |
|------|---------|
| `CHALLENGE` | Challenge flow: redirect to the ACS, which displays the password to enter. Correct password = authenticated; anything else = failed. |
| `SUCCESSFUL` | Frictionless flow, authentication succeeds (no redirect). |
| `NOTAUTH` | Frictionless flow, authentication fails. |
| `PROOFATTEMPT` | Attempted authentication, treated as successful. |
| `NOTENROLLED`, `REJECT`, `TECHDIFFICULTIES`, `ERROR` | Other failure modes. |

Any other name (e.g. a real one) fails 3D Secure authentication in the
sandbox, which is why the form fills in `CHALLENGE` when you tick the box.
