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
- `index.php` — the payment form (card capture modes and PayPal).
- `pay.php` — tokenises (if needed), creates the payment, and either shows
  the result or hands the browser to the 3D Secure challenge.
- `notification.php` — receives the challenge result and completes the
  transaction.
- `paypal.php` — registers a PayPal payment and redirects to PayPal.
- `paypal-return.php` — the PayPal callback: fetches the transaction by the
  `transactionId` Opayo appends to the URL and shows the result.

## Wallets

Only PayPal can be exercised here. Apple Pay and Google Pay both need a real
wallet token minted in the browser (Safari on an Apple device signed into a
sandbox-tester Apple ID; the Google Pay sheet with a Google account), plus a
vendor with the wallet enabled in MyOpayo and, for Apple Pay, a registered
HTTPS domain (Opayo-managed certificate) or an Apple merchant certificate
(merchant-managed). Personal test vendors answer `6401 Wallet not enabled for
the vendor`; the shared sandbox vendor has no domain you can register. The
library models them to the API reference (`ApplePayPayment`,
`GooglePayPayment`, `CreateApplePaySession` / `ApplePaySession`), and the
sandbox magic amounts for Apple Pay are listed in `docs/TESTING-GUIDE.md`.

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
