# Credentials and setup

Payments through Opayo Pi involve up to four separate parties, each of which
owns some credentials, hands you others, and expects certain things pasted into
certain places. This page lists who owns what, where you get it, where it goes,
and which error tells you it is missing or wrong.

Every credential is owned by one party, obtained from one place and pasted into
one place. The tables below give all three for each credential.

## What is possible, by environment

"Complete" means a payment authorises end to end. "Stops at" means the request
is built and the gateway accepts its structure, but the sandbox will not finish
the payment.

| Method | Sandbox (test) | Live (production) | The blocker on sandbox |
|---|---|---|---|
| Card, no 3DS | Complete | Complete | |
| Card + 3D Secure v2 | Complete (public `sandbox` vendor; magic cardholder names) | Complete | Personal test vendors have no 3DS simulation |
| PayPal | Complete (needs a PayPal sandbox buyer login) | Complete | |
| Google Pay | Stops at `6203` | Complete | The sandbox does not accept tokens from Google's TEST environment; Opayo supports Google Pay **live only** |
| Apple Pay (web) | Stops at `4006`/`6118`/`6125` | Complete (Opayo-managed cert) | The web cert mode (Opayo-managed) is not offered on test; test's merchant-managed is the In-App path |
| Apple Pay (In-App, native) | Complete (merchant-managed; needs Apple Developer account) | Complete | Outside this library's scope, which is web (Pi API) |

**Google Pay and Apple Pay cannot be completed on Opayo's sandbox for a web
integration.** Card and PayPal can. That is how Opayo's sandbox behaves, and
this package cannot change it. Some gateways (Stripe, for one) special-case the
wallet test tokens so you can complete a payment in test; Opayo does not.

## What "working" means for this package

This is a library. It builds the request the gateway documents and parses the
response. Decrypting a wallet token and moving the money happens inside Elavon,
where no integrator can see.

So for the two wallets, the evidence stops where the sandbox stops:

| Evidence | What it proves | Where |
|---|---|---|
| Unit tests | The library emits the exact structures Google, Apple and Opayo document | `tests/GooglePay/`, `tests/Demo/`, `tests/Request/Model/` |
| Sandbox requests | Opayo parsed a real request and rejected only the payload or the enrolment; a structural error returns a different code | the demo's wire panel |
| The error codes | `6203` and `4006` mean the gateway accepted the shape of the request | the demo's setup check and wire panel |

A correct structure is not the same as a payment that has been seen to
authorise. The wallet payments in this package have not been run against a live
account, so the decrypt-and-authorise step is untested. If you take a wallet
live, treat the first payments as a test: use a real card and refund it.

The demo helps in two ways:

- The panels show the gateway's own code and message (`6203`, `4006` and so
  on), so you can look the code up in the troubleshooting table below.
- The wire panel shows the exact request and response for every call. If
  something fails on a live account, copy both into a bug report.

## The four parties

| Party | Role | You get credentials from them via |
|---|---|---|
| **You (the merchant)** | Run the checkout; hold your Opayo vendor login | |
| **Opayo / Elavon** | The gateway. Decrypts wallet tokens, authorises, settles | MyOpayo portal + the account email from onboarding |
| **Google** | Owns Google Pay; encrypts the card to Elavon's key | Google Pay & Wallet Console (business.google.com/…/paymentscenter) |
| **Apple** | Owns Apple Pay; encrypts the card to the certificate holder's key | Apple Developer portal, only if you manage the certificates yourself |

For both wallets you cannot read the encrypted token. Google encrypts it to
Elavon, and Apple encrypts it to whoever holds the payment-processing
certificate (Opayo, in the recommended setup). You only pass it on. That is why
almost nothing a wallet gives you is a secret, and why you never receive a
decryption key. `docs/google-pay-key-custody.html` explains this in detail.

## Master credential matrix

| Credential | Owned by | Get it from | Goes into | Secret? | Missing / wrong shows as |
|---|---|---|---|---|---|
| `OPAYO_VENDOR_NAME` | You (assigned at onboarding) | MyOpayo / welcome email | `.env` | No | `3000` malformed / auth errors |
| `OPAYO_INTEGRATION_KEY` | Opayo | MyOpayo → Settings → API keys | `.env` | **Yes** | `1000 Unauthenticated` |
| `OPAYO_INTEGRATION_PASSWORD` | Opayo | MyOpayo → Settings → API keys (shown once) | `.env` | **Yes** | `1000 Unauthenticated` |
| Public sandbox creds (`sandbox`, `sandboxEC`) | Elavon (published) | Elavon's "Test in Sandbox" page (saved to `docs/vendor/opayo/test-in-sandbox.md` by `scripts/fetch-opayo-docs.php`); also in `demo/debug/accounts.php` | Selected by the account switcher in the demo's debug panel | No (public) | |
| `gatewayMerchantId` (Google Pay) | Opayo (issued when the wallet is enabled) | MyOpayo → Settings → Pay Methods → Google Pay | `OPAYO_GOOGLE_PAY_MERCHANT_ID` in `.env`, then the page's `tokenizationSpecification` | No (an identifier) | `6401` if wallet not enabled; a decline if wrong |
| Gateway name `opayoelavon` | Opayo (fixed, same for everyone) | Constant in the library (`GooglePay\Configuration::GATEWAY`) | The page's `tokenizationSpecification` | No | Google sheet returns an unchargeable token |
| Google merchant ID | Google | Google Pay & Wallet Console | `GOOGLE_PAY_MERCHANT_ID` in `.env`; only read in `PRODUCTION` | No (an identifier) | `PRODUCTION` sheet fails to load |
| Apple Pay domain | You (a domain you control) | Registered in MyOpayo → Pay Methods → Apple Pay | `OPAYO_APPLE_PAY_DOMAIN` in `.env` + registered in MyOpayo | No | `6118` not registered; `6125` if it carries a port |
| Apple payment-processing certificate | Opayo generates the CSR; Apple signs it; Opayo holds the key | Download CSR from MyOpayo → sign at Apple → upload back to MyOpayo | Uploaded to MyOpayo; never in your code | **Yes (key held by Opayo)** | `4006` if Apple Pay not enabled on the account |
| Apple Merchant ID | You | Apple Developer portal (needs the paid programme) | Entered in MyOpayo (merchant-managed) | No (an identifier) | Required on **test**, where merchant-managed is the only mode Opayo supports |
| PayPal enablement | Opayo | MyOpayo → Pay Methods | Nothing to paste; enabled on the vendor | n/a | `1030 Vendor not enrolled with this wallet type` |
| PayPal sandbox buyer login | You | developer.paypal.com → Sandbox → Accounts | Typed at PayPal, not stored | **Yes** | Cannot approve the PayPal redirect |

Secrets (integration password, and the certificates Opayo holds) never belong in
client-side code or version control. The identifiers (`gatewayMerchantId`,
`opayoelavon`, the Google/Apple merchant IDs, your registered domain) are not
secret and are fine in page source, which is where the demo puts them.

## Setup sequences

Each sequence is for the sandbox. What changes for production is at the end of
each.

### Card

1. Put `OPAYO_VENDOR_NAME`, `OPAYO_INTEGRATION_KEY`, `OPAYO_INTEGRATION_PASSWORD`
   in `.env`, or switch the demo to Elavon's public sandbox account from the
   debug panel.
2. Run the demo. The card panel works straight away. The hosted fields are the
   code to copy; the test card page (`debug/test-card.php`) is only there to
   reach the 3DS magic cardholder names.
3. **3D Secure:** set `OPAYO_APPLY_3D_SECURE=Force`. The sandbox reads the
   outcome from the *cardholder name*: `CHALLENGE`, `SUCCESSFUL`, `NOTAUTH`.
   Personal test vendors have no 3DS simulation and reject everything, so use
   the public sandbox for 3DS.

**Production:** live vendor credentials, `Endpoint::MODE_LIVE` in your app
(`OPAYO_ENVIRONMENT=live` in the demo), and a real card.

### PayPal

1. Use a vendor with PayPal enabled. The public `sandbox` vendor has it; a
   personal test vendor answers `1030` until Opayo enables it.
2. Click **Pay with PayPal**; approve at the PayPal sandbox with a **sandbox
   buyer** account (developer.paypal.com → Sandbox → Accounts).
3. PayPal returns you to `paypal-return.php`, which fetches the outcome.

**Production:** PayPal enabled on your live vendor; a real PayPal login.

### Google Pay

1. Ask Opayo to **enable Google Pay** on your vendor. They ask for your Google
   merchant ID to do it. Until then: `6401 Wallet not enabled for the vendor`.
2. Copy the **gatewayMerchantId** MyOpayo now shows into
   `OPAYO_GOOGLE_PAY_MERCHANT_ID` (or let it default to the vendor name).
3. The sheet renders and produces a token. **Opayo supports Google Pay for live
   testing only** (their words, September 2026): there is no sandbox path that
   completes. The sheet in Google's TEST environment returns a genuine token,
   signed with Google's test key and addressed to `gateway:opayoelavon`; the
   sandbox creates a transaction for it, then rejects it with `6203 Invalid
   Google Pay payload`. Reaching `6203` proves your integration is correct up
   to the token; it cannot go further on test.

**Production (the only way to complete Google Pay):** a Google merchant ID in
`GOOGLE_PAY_MERCHANT_ID`, the sheet switched to `PRODUCTION`, an allowlisted
HTTPS origin, live Opayo credentials, and a real card you then refund. This is
standard for every Google Pay integration, not a limitation of this one.

### Apple Pay

Read "Apple Pay: which certificate mode?" below first. You do not get a free
choice of mode, and on **test** the only mode offered needs an Apple Developer
account.

1. Ask Opayo to **enable Apple Pay** on your vendor. Apple Pay "is not currently
   available to all acquirers" (Opayo's words), so this is an explicit request.
   Until then: `4006 The TxType requested is not supported on this account`.
2. On **test**, only **merchant-managed** is offered ("Opayo manages your
   certificate" is greyed out). So you need, from Apple: the paid Apple Developer
   Program, an Apple Merchant ID, and the certificate: download the CSR from
   MyOpayo, sign it at Apple, upload it back. (Opayo still holds the key.)
3. **Register your domain.** HTTPS, no port. `6118 Domain not registered` until
   you do; `6125 Invalid domainName field` if it carries a port
   (`127.0.0.1:8000`). Set `OPAYO_APPLE_PAY_DOMAIN` to it.
4. **Confirm with Elavon that merchant-managed on test covers Apple Pay on the
   web**, not only In-App. Their docs only describe In-App. If web is not
   supported on test, stop here: it cannot be demonstrated on the sandbox.
5. If it is supported: open the checkout in **Safari on an Apple device** with a
   card in Wallet; authorise with the sandbox magic amounts (`10600` authorised,
   `10700` soft decline).

**Production:** Opayo-managed becomes available (no Apple Developer account
needed). Apple additionally verifies the domain by fetching
`/.well-known/apple-developer-merchantid-domain-association` from its own IPs, so
the domain **cannot be behind a proxy or redirect** (rules out tunnels). Apple
skips this verification in sandbox.

## Apple Pay: which certificate mode?

MyOpayo offers two. For a **web** integration like this one, the portal itself
tells you which: the "Opayo manages the authentication certificate" option says

> "Select this option if you wish to use Apple Pay on our hosted payment pages.
> This is the easiest option to configure and works well if you do not receive
> payments directly within a mobile app."

So **Opayo-managed is the correct mode for web** (you are not a mobile app), and
it needs no Apple Developer account. You only register your domain. That is what
the demo implements. The other mode, merchant-managed, is the In-App path.

The problem is the **test environment**. On test, "Opayo manages your
certificate" is **greyed out**. Elavon confirmed by email (September 2026):

> "We only support merchant managed certificate apple pay on test [...]. Google
> Pay is only available for live testing."

The option is greyed out because of the environment. It does not mean you
picked the wrong mode: Opayo-managed is right for web, but it is not available
on test.

| | Opayo manages your certificate | You manage your certificate |
|---|---|---|
| Available on **test** | **No** (greyed out) | **Yes** |
| Available on live | Yes | Yes |
| Apple Developer account needed | No | **Yes** |
| Apple Merchant ID needed | No | **Yes** (created in that account) |
| Who holds the decryption key | Opayo | **Still Opayo**: you sign Opayo's CSR, so the private key stays with Opayo |
| Documented by Opayo for | Web + In-App | Primarily **In-App** (native iOS); web coverage is thin |
| What you do | Register your domain | Create a Merchant ID, download Opayo's CSR, get it signed by Apple, upload the signed cert |

The mode you need for web (Opayo-managed) is not on test, and the mode test
offers (merchant-managed) is the In-App path. So **web Apple Pay through Opayo
can probably only be verified in production**, like Google Pay: on a registered
domain, with a real card that you then refund.

**Do not buy an Apple Developer account just to test on the sandbox.** It only
gets you the merchant-managed (In-App) path, which is a different integration.
On **live**, Opayo-managed needs no Apple Developer account and no Merchant ID:
you register your domain and Opayo holds the certificate.

It is worth asking Elavon to confirm this before you plan around a sandbox
Apple Pay demo: *"For a web integration using the Pi API on our own domain, is
there any way to test Apple Pay end to end on the sandbox, or is production the
only place it can be verified?"*

**Demo limitation:** `demo/apple-session.php` implements the **Opayo-managed**
validation flow (it calls Opayo's `CreateApplePaySession`). That mode is not
available on test, so it returns `4006` on the sandbox. Merchant-managed uses a
different validation step, in which your server calls Apple's `validationURL`
directly with a merchant identity certificate. The demo does not implement that.

## Running the demo through a tunnel

The demo runs on `127.0.0.1:8000`, which is fine for card, PayPal and the
Google Pay sheet, but Apple Pay (and production Google Pay) needs the page served
from a real HTTPS **domain**: the browser must load the whole checkout from
that origin. A tunnel (ngrok, Cloudflare Tunnel) does this. It is not a
redirect. It gives your local server a public HTTPS address, and the browser
stays on that domain for the whole flow. `demo/README.md` has the commands.

- Use a **stable** tunnel domain (a reserved/custom one). Free rotating
  subdomains change each run, so you would re-register in MyOpayo every time.
- Register that exact domain in MyOpayo and set `OPAYO_APPLE_PAY_DOMAIN` to it.
- **Sandbox only.** Apple skips domain verification in sandbox, so the tunnel's
  proxy does not matter there. In production Apple forbids a proxy, so a tunnel
  will fail verification and you need a real domain you own.
- A tunnel only fixes the origin. It does nothing about `4006`, which comes
  from the account and the certificate mode described above.

## Troubleshooting by error code

| Code | Text | What is missing / wrong | Fix |
|---|---|---|---|
| `1000` | Unauthenticated | Integration key/password wrong or absent | Check `OPAYO_INTEGRATION_KEY` / `OPAYO_INTEGRATION_PASSWORD` |
| `1030` | Vendor not enrolled with this wallet type | PayPal not enabled on the vendor | Use the public sandbox, or ask Opayo to enable PayPal |
| `4006` | TxType not supported on this account | Apple Pay not enabled on the vendor, **or** you are trying the Opayo-managed flow on test (test supports merchant-managed only) | Ask Opayo to enable Apple Pay; on test use merchant-managed |
| `6118` | Domain not registered | Apple Pay domain not registered in MyOpayo | Register the domain under Pay Methods → Apple Pay |
| `6125` | Invalid domainName field | The domain carries a port (`127.0.0.1:8000`) or a scheme (`https://...`) | Serve from a real domain / tunnel; set `OPAYO_APPLE_PAY_DOMAIN` to the bare host name |
| `1003` | Missing mandatory field, property `domainName` | The Apple Pay session request used `domain`, as Elavon's API reference says, instead of `domainName` | Use `CreateApplePaySession`, which sends `domainName` |
| `6111` | Body payload with invalid base64 format | The Apple Pay `paymentData` is not base64 | Build it with `ApplePayPayment::fromAppleToken()` |
| `6146` | Invalid JSON supplied within the Payload | The Apple Pay `paymentData` decodes to something that is not JSON, often because it was encoded twice | Pass Apple's token to `fromAppleToken()` as it arrived |
| `6151` | Payment data not supplied | The decoded Apple Pay payload has no top-level `paymentData` key (bare contents, or a `token` wrapper) | Send `{"paymentData": {...}}`; `fromAppleToken()` does this |
| `6138` | Invalid payload encryption | Opayo read the Apple Pay payload but could not decrypt it | The request is well formed; the token is not one Opayo can open |
| `6203` | Invalid Google Pay payload | The sandbox does not accept tokens from Google's TEST environment | Expected in sandbox; needs a `PRODUCTION` sheet + real card |
| `6401` | Wallet not enabled for the vendor | Google/Apple Pay not enabled on the vendor | Ask Opayo to enable the wallet |
| 3DS | `3D-Authentication failed` before the challenge | Personal test vendor (no 3DS sim), or non-magic cardholder name | Use the public sandbox; set cardholder name to `CHALLENGE` |

The demo's **wire panel** shows each of these as it happens: the right-hand
column has the exact request and response for every call.

## Where each thing lives

- **`.env`** (gitignored, secrets live here): vendor name, integration key +
  password, `OPAYO_GOOGLE_PAY_MERCHANT_ID`, `GOOGLE_PAY_MERCHANT_ID`,
  `OPAYO_APPLE_PAY_DOMAIN`, the `DEMO_ENABLE_*` panel switches. See `.env.example`.
- **MyOpayo portal**: wallet enablement (Google/Apple/PayPal), the Google
  `gatewayMerchantId` display, Apple Pay certificate mode + domain registration.
- **Google Pay & Wallet Console**: your Google merchant ID (production only).
- **Apple Developer portal**: only if you manage the Apple certificates
  yourself. Not needed for the web (Opayo-managed) path.
- **In the code**: the fixed `opayoelavon` gateway name
  (`src/GooglePay/Configuration.php`); everything else comes from `.env`.
