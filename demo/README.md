# Opayo Pi Demo

A minimal payment demo running on the PHP built-in server against the
Opayo sandbox. No SSL and no public URL are needed: all gateway calls are
outbound HTTPS from PHP, and the 3D Secure challenge result is returned by
the shopper's *browser* (not by Opayo's servers), so `localhost` is
reachable for the notification hop.

It is a single checkout page: the order is entered once at the top, and every
payment method is offered together below it, each as a self-contained panel. A
method that cannot run right now shows a placeholder saying why, rather than
disappearing. Forms and actions are on the left, the raw wire data (request and
response bodies, plus the PHP session contents) on the right — and on load the
wire panel doubles as a readiness report.

> **New to the credentials?** `docs/CREDENTIALS-AND-SETUP.md` is the map: who
> owns each credential, where you get it, where it goes, and which error code
> tells you it is missing or wrong. Start there if the wallet setup feels
> scattered.

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

## Running it publicly (for Apple Pay / wallets)

Apple Pay needs the demo served from a real HTTPS domain with no port in it, so
`127.0.0.1:8000` will not do. An [ngrok](https://ngrok.com) tunnel solves this:
it carries a public HTTPS URL down to your local PHP server. Two processes do
two jobs — `php -S` *is* the site, ngrok *carries* it to the outside world:

```text
Safari  →  https://<your-domain>  (ngrok cloud)  →  tunnel  →  127.0.0.1:8000  →  PHP
```

One-time setup (any OS):

1. Install [ngrok](https://ngrok.com/download) and connect it to your account
   once: `ngrok config add-authtoken <token>` (copy the token from your ngrok
   dashboard). It is saved in ngrok's own config file and never typed again;
   `ngrok config check` prints where that file lives on your OS.
2. Reserve a free static domain in your ngrok dashboard (Domains), e.g.
   `something.ngrok-free.dev`. A *static* domain never changes, so you only
   register it with Opayo once.
3. Put it in `.env` as `OPAYO_APPLE_PAY_DOMAIN` — the one place the domain is
   defined. The launch scripts read it from there.
4. Register that same domain in MyOpayo → Settings → Pay Methods → Apple Pay.

Then start it. The launch script starts both the PHP server and the tunnel,
reads the domain from `.env`, picks the right ngrok flag for your installed
version, and stops both on `Ctrl-C`:

```bash
./demo/serve-public.sh      # macOS / Linux
```

```powershell
.\demo\serve-public.ps1     # Windows (PowerShell)
```

Or run the two pieces by hand — the commands are identical on every OS, in two
terminals:

```bash
php -S 127.0.0.1:8000 -t demo              # terminal 1: the site itself
ngrok http 8000 --url https://<your-domain> # terminal 2: the public tunnel
```

Open the **public** URL the tunnel prints, not `127.0.0.1:8000`.

Gotchas:

- Only one tunnel can hold the domain at a time. `ERR_NGROK_334 ... already
  online` means a previous ngrok is still running — stop it (`pkill ngrok` on
  macOS/Linux, `Get-Process ngrok | Stop-Process` on Windows) and re-run.
- Older ngrok builds call the flag `--domain <host>` instead of
  `--url https://<host>`; run `ngrok http --help` to see which yours has. The
  launch scripts detect this for you, so prefer them if in doubt.
- First run of the shell script may need `chmod +x demo/serve-public.sh`. If
  PowerShell blocks its script, run it once as
  `powershell -ExecutionPolicy Bypass -File .\demo\serve-public.ps1`.
- ngrok's authtoken lives in ngrok's config file (`ngrok config check` shows
  the path), set once via `ngrok config add-authtoken`. You never retype it.

## What it demonstrates

| Panel | How |
|--------|-----|
| Card — hosted fields | The recommended default: `sagepay.js` renders the card fields and tokenises in the browser, so card details never reach PHP. |
| Card — server-side capture | The toggle inside the card panel: raw card details are posted to `pay.php`, which tokenises them via the API. Sandbox/test cards only — never do this with real cards. Kept because it is the only way to drive the sandbox's magic cardholder names without typing into Opayo's iframe. |
| Without 3D Secure | Leave the checkbox off: `apply3DSecure: Disable`, one round trip, result rendered immediately. |
| With 3D Secure v2 | Tick the checkbox: `apply3DSecure: Force` plus a `strongCustomerAuthentication` object built from real browser details. The browser POSTs the `creq` to the sandbox ACS, completes the challenge, and is returned to `notification.php`, which forwards the `cres` to Opayo. |
| Google Pay | The Google Pay sheet mints a token; the panel posts it to `pay.php` as `googlePayToken`. See Wallets below for how far the sandbox lets this go. |
| Apple Pay | The Opayo-managed path: `onvalidatemerchant` calls `apple-session.php`, which asks Opayo to open a merchant session; the token is posted to `pay.php` as `applePayToken`. See Wallets below. |
| PayPal | No card. The panel posts `method=paypal` to `pay.php`, which registers the transaction with `paymentMethod.paypal = {merchantSessionKey, callbackUrl}`, gets a `Redirect` (2023) response with a PayPal URL, and sends the browser there. PayPal returns the shopper to Opayo, which redirects to `paypal-return.php?transactionId=…`; that page fetches the transaction for the outcome. Needs the public sandbox vendor (PayPal enabled) and a PayPal *sandbox buyer* login to approve. |

Every panel posts the same order to one endpoint, `pay.php`, with its own
credential. `pay.php` turns that credential into a payment method and runs a
single `CreatePayment`; the response type (authorised, `3DAuth`, PayPal
`Redirect`, or error) decides the next step, identically for every method. That
is the whole point of the layout: the token is the boundary, and after it the
flow is the same.

## Files

- `shared.php` — env loading, auth/endpoint/client helpers, the wire-data
  recorder, the two-column page layout, and the demo's reusable pieces: the
  readiness probes, the payment-method factory (`paymentMethodFromRequest`),
  the SCA builder (`scaFromRequest`) and the outcome renderers.
- `index.php` — the checkout page: the order block plus the four method panels.
- `methods/card.php`, `methods/googlepay.php`, `methods/applepay.php`,
  `methods/paypal.php` — one self-contained panel each. This is the code to read
  (and copy) for a given method: readiness check, control or placeholder, and
  the form that posts to `pay.php`.
- `pay.php` — the single payment endpoint. Turns whatever credential was posted
  into a payment method, runs one `CreatePayment`, and acts on the response.
- `apple-session.php` — the Apple Pay `onvalidatemerchant` endpoint: asks Opayo
  to open a merchant session (Opayo-managed certificate) and returns it as JSON.
- `notification.php` — receives the 3D Secure challenge result and completes the
  transaction. Shared by every method that can be challenged.
- `paypal-return.php` — the PayPal callback: fetches the transaction by the
  `transactionId` Opayo appends to the URL and shows the result.

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

**Google Pay** has a panel, and gets as far as the gateway reading the payload.
The Google Pay sheet only mints a real, encrypted token in its `PRODUCTION`
environment; in `TEST` it returns the fixed placeholder
`examplePaymentMethodToken`, which Opayo rejects with `6203 Invalid Google Pay
payload` (verified against the sandbox, both bare and JSON-wrapped). So the tab
demonstrates the whole integration — sheet, token, `merchantSessionKey`,
`paymentMethod.googlePay`, and the wire data for all of it — but cannot be
authorised. Going further needs a Google merchant ID from the Google Pay &
Wallet Console and an allowlisted HTTPS origin, neither of which applies to
`127.0.0.1`. Note the sheet opens a popup, so a headless browser cannot complete
it either; use a real browser signed into a Google account. The request objects
the sheet needs are built server-side by the library
(`Academe\Opayo\Pi\GooglePay\Configuration`), not hand-written in JavaScript.

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

**Apple Pay** has a panel built on the **Opayo-managed certificate** path: the
panel calls `apple-session.php`, which asks Opayo to open a merchant session
(`CreateApplePaySession`) and returns it plus a `sessionValidationToken`; the
token from `onpaymentauthorized` is posted to `pay.php` as `applePayToken`.

> **Important — this cannot be exercised on Opayo's sandbox.** Elavon confirmed
> (September 2026) that **test only supports the *merchant-managed* certificate
> mode**, not Opayo-managed. The demo implements Opayo-managed, which is a
> production path, so on test the readiness probe returns
> `4006 The TxType requested is not supported on this account`. Testing Apple
> Pay on the sandbox needs merchant-managed — an Apple Developer account, an
> Apple Merchant ID, Opayo's CSR signed by Apple, and a server that validates
> the merchant session **directly with Apple** (not via `CreateApplePaySession`).
> That flow is not built here. And it is not yet confirmed that Opayo's
> merchant-managed test path covers Apple Pay on the **web** at all (their docs
> describe In-App). See `docs/CREDENTIALS-AND-SETUP.md` for the full picture and
> the question to put to Elavon.

The browser half of the Apple Pay panel (`ApplePaySession`) is written from
Apple's and Opayo's docs and is **unverified**. The server half
(`apple-session.php`) is verified against the sandbox only as far as the `4006` /
`6118` / `6125` readiness errors — the Opayo-managed happy path needs production.

Optional `.env` settings feed the wallet panels; all have workable defaults, and
the Google form field overrides the first:

    OPAYO_GOOGLE_PAY_MERCHANT_ID=   # gatewayMerchantId from MyOpayo (defaults to the vendor name)
    GOOGLE_PAY_MERCHANT_ID=         # Google merchant ID, only read in PRODUCTION
    OPAYO_APPLE_PAY_DOMAIN=         # domain registered in MyOpayo (defaults to the served host)
    DEMO_ENABLE_CARD=1              # switch any panel off with =0; a disabled panel
    DEMO_ENABLE_GOOGLE_PAY=1        #   shows a one-line note rather than disappearing
    DEMO_ENABLE_APPLE_PAY=1
    DEMO_ENABLE_PAY_PAL=1

### Verify Apple Pay on your iPhone

This assumes the account/certificate blockers above are resolved — i.e. either
you are on **production** (where the demo's Opayo-managed flow applies), or Opayo
has confirmed and you have built merchant-managed for test. Given those:

1. Serve the demo from a public HTTPS domain (not `127.0.0.1`).
2. In MyOpayo → Settings → Pay Methods → Apple Pay, register that domain.
3. Set `OPAYO_APPLE_PAY_DOMAIN` to it in `.env`.
4. Open the checkout in Safari on an iPhone or Mac signed into an Apple ID with a
   card in Wallet. The Apple Pay panel should show the button instead of the
   `4006`/`6118`/`6125` placeholder.
5. Pay with a sandbox-tester card and confirm the transaction authorises. Apple
   Pay tokens are device-authenticated, so Opayo should not return `3DAuth`; if
   it does, that is worth reporting (handover open point).

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
