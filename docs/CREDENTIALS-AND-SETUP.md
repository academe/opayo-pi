# Credentials and setup

Payments through Opayo Pi involve up to four separate parties, each of which
owns some credentials, hands you others, and expects certain things pasted into
certain places. This page is the map: **who owns what, where you get it, where
it goes, and which error tells you it is missing or wrong.**

The one rule that removes most of the confusion:

> Every credential is **owned by one party**, **obtained from one place**, and
> **pasted into one place**. If you know those three for each, nothing is
> mysterious. The tables below give them.

## What is possible, by environment

The single most useful table here. "Complete" means a payment authorises end to
end; "up to the boundary" means the request is built and the gateway accepts its
structure, but the sandbox will not finish it.

| Method | Sandbox (test) | Live (production) | The blocker on sandbox |
|---|---|---|---|
| Card, no 3DS | ✅ Complete | ✅ Complete | — |
| Card + 3D Secure v2 | ✅ Complete (public `sandbox` vendor; magic cardholder names) | ✅ Complete | Personal test vendors have no 3DS simulation |
| PayPal | ✅ Complete (needs a PayPal sandbox buyer login) | ✅ Complete | — |
| Google Pay | ⛔ Up to the boundary — stops at `6203` | ✅ Complete | Google's TEST token is a placeholder Opayo cannot decrypt; Opayo supports Google Pay **live only** |
| Apple Pay (web) | ⛔ Up to the boundary — stops at `4006`/`6118`/`6125` | ✅ Complete (Opayo-managed cert) | The web cert mode (Opayo-managed) is not offered on test; test's merchant-managed is the In-App path |
| Apple Pay (In-App, native) | ✅ Complete (merchant-managed; needs Apple Developer account) | ✅ Complete | Not this library's scope — this package is web (Pi API) |

The two ⛔ rows are the whole of the frustration: **the two wallets cannot be
completed on Opayo's sandbox for a web integration.** Card and PayPal can. This
is a property of Opayo's sandbox, not of this package — some gateways (Stripe,
for one) special-case the wallet test tokens so you can complete a payment in
test; Opayo does not.

## What "working" means for this package

This is a **library**, not a shop. It is responsible for exactly two things:
building the request the gateway documents, and parsing the response. It is *not*
responsible for the gateway decrypting a wallet token and moving money — that is
Elavon's cryptography, in a black box no integrator can see into.

So for the two wallets, "working" is demonstrated to the boundary of what the
package owns, and no further, because no further is possible on sandbox:

| Evidence | What it proves | Where |
|---|---|---|
| Unit tests | The library emits the exact structures Google, Apple and Opayo document | `tests/GooglePay/`, `tests/Demo/`, `tests/Request/Model/` |
| Live sandbox wire | Opayo parsed a real request and rejected only the payload/enrolment — a structural error would look different | the demo's wire panel |
| The error codes themselves | `6203` / `4006` are *positive* signals: the gateway accepted the shape | demo placeholders + wire |

**The honest limit, stated plainly:** structural correctness is not the same as
having watched a payment authorise. Documentation is wrong in tiny edge cases,
and those live in the decrypt-and-authorise step the sandbox refuses to run. This
package cannot close that gap in test, and neither can any other. It is closed
only by a real live transaction — and realistically that means **the first
integrator who goes live**, not you.

Which is exactly why the demo is built the way it is:

- **Every failure names itself.** The panels show the gateway's own code and
  message (`6203`, `4006`, …), so "it doesn't work" becomes "it stops at `6203`,
  which the table says is expected in sandbox" — self-service triage, before it
  ever reaches you.
- **The wire panel makes a bug debuggable without you being live.** When a live
  integrator hits a real edge case, they can copy the exact request and response
  from the right-hand panel into a bug report. You debug the bytes remotely; you
  never need a live account of your own. That is the design answer to "I'll get
  reports I can't debug."

## The four parties

| Party | Role | You get credentials from them via |
|---|---|---|
| **You (the merchant)** | Run the checkout; hold your Opayo vendor login | — |
| **Opayo / Elavon** | The gateway. Decrypts wallet tokens, authorises, settles | MyOpayo portal + the account email from onboarding |
| **Google** | Owns Google Pay; encrypts the card to Elavon's key | Google Pay & Wallet Console (business.google.com/…/paymentscenter) |
| **Apple** | Owns Apple Pay; encrypts the card to the certificate holder's key | Apple Developer portal — only if you self-manage certificates |

A theme worth internalising before the tables: for **both wallets the encrypted
token is opaque to you** — Google encrypts it to Elavon, Apple encrypts it to
whoever holds the payment-processing certificate (Opayo, in the recommended
setup). You are a courier. That is why almost nothing a wallet gives you is a
secret, and why you never receive a decryption key. See
`docs/google-pay-key-custody.html` for the full picture.

## Master credential matrix

| Credential | Owned by | Get it from | Goes into | Secret? | Missing / wrong shows as |
|---|---|---|---|---|---|
| `OPAYO_VENDOR_NAME` | You (assigned at onboarding) | MyOpayo / welcome email | `.env` | No | `3000` malformed / auth errors |
| `OPAYO_INTEGRATION_KEY` | Opayo | MyOpayo → Settings → API keys | `.env` | **Yes** | `1000 Unauthenticated` |
| `OPAYO_INTEGRATION_PASSWORD` | Opayo | MyOpayo → Settings → API keys (shown once) | `.env` | **Yes** | `1000 Unauthenticated` |
| Public sandbox creds (`sandbox`, `sandboxEC`) | Elavon (published) | `docs/vendor/opayo/test-in-sandbox.md`; baked into `demo/shared.php` | Selected by the demo's account dropdown | No (public) | — |
| `gatewayMerchantId` (Google Pay) | Opayo (issued when the wallet is enabled) | MyOpayo → Settings → Pay Methods → Google Pay | `OPAYO_GOOGLE_PAY_MERCHANT_ID` in `.env`, then the page's `tokenizationSpecification` | No (an identifier) | `6401` if wallet not enabled; a decline if wrong |
| Gateway name `opayoelavon` | Opayo (fixed, same for everyone) | Constant in the library (`GooglePay\Configuration::GATEWAY`) | The page's `tokenizationSpecification` | No | Google sheet returns an unchargeable token |
| Google merchant ID | Google | Google Pay & Wallet Console | `GOOGLE_PAY_MERCHANT_ID` in `.env`; only read in `PRODUCTION` | No (an identifier) | `PRODUCTION` sheet fails to load |
| Apple Pay domain | You (a domain you control) | Registered in MyOpayo → Pay Methods → Apple Pay | `OPAYO_APPLE_PAY_DOMAIN` in `.env` + registered in MyOpayo | No | `6118` not registered; `6125` if it carries a port |
| Apple payment-processing certificate | Opayo generates the CSR; Apple signs it; Opayo holds the key | Download CSR from MyOpayo → sign at Apple → upload back to MyOpayo | Uploaded to MyOpayo; never in your code | **Yes (key held by Opayo)** | `4006` if Apple Pay not enabled on the account |
| Apple Merchant ID | You | Apple Developer portal (needs the paid programme) | Entered in MyOpayo (merchant-managed) | No (an identifier) | Required on **test** — merchant-managed is the only mode Opayo supports there |
| PayPal enablement | Opayo | MyOpayo → Pay Methods | Nothing to paste; enabled on the vendor | — | `1030 Vendor not enrolled with this wallet type` |
| PayPal sandbox buyer login | You | developer.paypal.com → Sandbox → Accounts | Typed at PayPal, not stored | **Yes** | Cannot approve the PayPal redirect |

Secrets (integration password, and the certificates Opayo holds) never belong in
client-side code or version control. The identifiers (`gatewayMerchantId`,
`opayoelavon`, the Google/Apple merchant IDs, your registered domain) are not
secret and are fine in page source — the demo puts them there deliberately.

## Setup sequences

Each is the sandbox path; the production delta is called out at the end of each.

### Card

1. Put `OPAYO_VENDOR_NAME`, `OPAYO_INTEGRATION_KEY`, `OPAYO_INTEGRATION_PASSWORD`
   in `.env` (or use the demo's **Public sandbox** account dropdown, which needs
   no `.env`).
2. Run the demo; the card panel works immediately. Hosted fields (the drop-in)
   is the path to copy; the server-side toggle exists only to reach the 3DS
   magic cardholder names.
3. **3D Secure:** tick the box. The sandbox reads the outcome from the
   *cardholder name* — `CHALLENGE`, `SUCCESSFUL`, `NOTAUTH`. Personal test
   vendors have no 3DS simulation and reject everything; use the public sandbox
   for 3DS.

**Production:** real live vendor credentials, `Endpoint::MODE_LIVE` in your app
(the demo is pinned to test on purpose), and a real card.

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

Read the certificate-mode box below first — the mode is not a free choice, and
on **test** it forces an Apple Developer account on you.

1. Ask Opayo to **enable Apple Pay** on your vendor. Apple Pay "is not currently
   available to all acquirers" (Opayo's words), so this is an explicit request.
   Until then: `4006 The TxType requested is not supported on this account`.
2. On **test**, only **merchant-managed** is offered ("Opayo manages your
   certificate" is greyed out). So you need, from Apple: the paid Apple Developer
   Program, an Apple Merchant ID, and the certificate — download the CSR from
   MyOpayo, sign it at Apple, upload it back. (Opayo still holds the key; see the
   box.)
3. **Register your domain.** HTTPS, no port. `6118 Domain not registered` until
   you do; `6125 Invalid domainName field` if it carries a port
   (`127.0.0.1:8000`). Set `OPAYO_APPLE_PAY_DOMAIN` to it.
4. **Confirm with Elavon that merchant-managed on test covers Apple Pay on the
   web**, not only In-App — their docs only describe In-App. If web is not
   supported on test, stop here: it cannot be demonstrated on the sandbox.
5. If it is supported: open the checkout in **Safari on an Apple device** with a
   card in Wallet; authorise with the sandbox magic amounts (`10600` authorised,
   `10700` soft decline).

**Production:** Opayo-managed becomes available (no Apple Developer account
needed). Apple additionally verifies the domain by fetching
`/.well-known/apple-developer-merchantid-domain-association` from its own IPs, so
the domain **cannot be behind a proxy or redirect** (rules out tunnels). Apple
skips this verification in sandbox.

> **Demo limitation:** `demo/apple-session.php` implements the *Opayo-managed*
> validation flow (`CreateApplePaySession`), which is a production-only path.
> Exercising Apple Pay on **test** needs the merchant-managed validation flow
> (your server calls Apple's `validationURL` directly with a merchant identity
> certificate), which the demo does not yet implement.

## Apple Pay: which certificate mode?

MyOpayo offers two. For a **web** integration like this one, the portal itself
tells you which: the "Opayo manages the authentication certificate" option says

> "Select this option if you wish to use Apple Pay on our hosted payment pages.
> This is the easiest option to configure and works well if you do not receive
> payments directly within a mobile app."

So **Opayo-managed is the correct mode for web** (you are not a mobile app), and
it needs no Apple Developer account — you just register your domain. That is what
the demo implements. The other mode, merchant-managed, is the In-App path.

The catch is purely the **test environment**. On test, "Opayo manages your
certificate" is **greyed out** — Elavon confirmed by email (September 2026):

> "We only support merchant managed certificate apple pay on test [...]. Google
> Pay is only available for live testing."

The greying is an environment limitation, **not** a signal you picked the wrong
mode: Opayo-managed is right for you, it is simply not provisioned on test.

| | Opayo manages your certificate | You manage your certificate |
|---|---|---|
| Available on **test** | **No** (greyed out) | **Yes** |
| Available on live | Yes | Yes |
| Apple Developer account needed | No | **Yes** |
| Apple Merchant ID needed | No | **Yes** (created in that account) |
| Who holds the decryption key | Opayo | **Still Opayo** — you sign Opayo's CSR, so the private key stays with Opayo |
| Documented by Opayo for | Web + In-App | Primarily **In-App** (native iOS); web coverage is thin |
| What you do | Register your domain | Create a Merchant ID, download Opayo's CSR, get it signed by Apple, upload the signed cert |

**The conclusion this leads to:** the mode you need for web (Opayo-managed) is
not on test, and the mode test offers (merchant-managed) is the In-App path. So
**web Apple Pay through Opayo is, like Google Pay, effectively production-verify-
only** — you confirm it live, on a real registered domain, with a real card you
refund. There is likely no meaningful sandbox demonstration of *web* Apple Pay,
whatever certificates you obtain.

**Therefore: do not buy an Apple Developer account to test on the sandbox.** It
would only get you the merchant-managed / In-App path, which is not this
integration. For your web integration on **live**, Opayo-managed needs no Apple
Developer account and no Merchant ID — you register your domain and Opayo holds
the certificate.

**The one thing worth confirming with Elavon** (a yes/no, costs nothing): *"For a
web integration using the Pi API on our own domain, is there any way to test
Apple Pay end to end on the sandbox — or is production the only place it can be
verified?"* The evidence says production-only; get it in writing before planning
around a sandbox Apple Pay demo.

**Demo caveat:** the demo's Apple Pay code (`demo/apple-session.php`) implements
the **Opayo-managed** validation flow (it calls Opayo's `CreateApplePaySession`).
Since Opayo-managed is not available on test, that path returns `4006` on the
sandbox. Merchant-managed uses a different validation step — your server calls
Apple's `validationURL` directly with a merchant identity certificate — which the
demo does not yet implement.

## Running the demo through a tunnel

The demo runs on `127.0.0.1:8000`, which is fine for card, PayPal and the
Google Pay sheet, but Apple Pay (and production Google Pay) needs the page served
from a real HTTPS **domain** — the browser must load the whole checkout from
that origin. A tunnel (ngrok, Cloudflare Tunnel) does this: it is not a redirect,
it makes your local server *be* a public HTTPS origin, and the browser stays on
that domain for the whole flow.

- Use a **stable** tunnel domain (a reserved/custom one). Free rotating
  subdomains change each run, so you would re-register in MyOpayo every time.
- Register that exact domain in MyOpayo and set `OPAYO_APPLE_PAY_DOMAIN` to it.
- **Sandbox only.** Apple skips domain verification in sandbox, so the tunnel's
  proxy is invisible to it. For production Apple explicitly forbids a proxy, so a
  tunnel will fail verification — you need a real domain you own.
- Note this only helps once the Apple Pay **account** and **certificate-mode**
  questions above are resolved. A tunnel fixes the *origin*; it does nothing for
  the `4006` / merchant-managed-on-test situation.

## Troubleshooting by error code

| Code | Text | What is missing / wrong | Fix |
|---|---|---|---|
| `1000` | Unauthenticated | Integration key/password wrong or absent | Check `OPAYO_INTEGRATION_KEY` / `OPAYO_INTEGRATION_PASSWORD` |
| `1030` | Vendor not enrolled with this wallet type | PayPal not enabled on the vendor | Use the public sandbox, or ask Opayo to enable PayPal |
| `4006` | TxType not supported on this account | Apple Pay not enabled on the vendor, **or** you are trying the Opayo-managed flow on test (test supports merchant-managed only) | Ask Opayo to enable Apple Pay; on test use merchant-managed |
| `6118` | Domain not registered | Apple Pay domain not registered in MyOpayo | Register the domain under Pay Methods → Apple Pay |
| `6125` | Invalid domainName field | The domain carries a port, e.g. `127.0.0.1:8000` | Serve from a real domain / tunnel; set `OPAYO_APPLE_PAY_DOMAIN` |
| `6203` | Invalid Google Pay payload | Google TEST token is a placeholder Opayo cannot decrypt | Expected in sandbox; needs a `PRODUCTION` sheet + real card |
| `6401` | Wallet not enabled for the vendor | Google/Apple Pay not enabled on the vendor | Ask Opayo to enable the wallet |
| 3DS | `3D-Authentication failed` before the challenge | Personal test vendor (no 3DS sim), or non-magic cardholder name | Use the public sandbox; set cardholder name to `CHALLENGE` |

Every one of these is visible in the demo's **wire panel** as it happens: the
right-hand column shows the exact request and response for each call, so a
failing setup names itself.

## Where each thing lives, at a glance

- **`.env`** (gitignored, secrets live here): vendor name, integration key +
  password, `OPAYO_GOOGLE_PAY_MERCHANT_ID`, `GOOGLE_PAY_MERCHANT_ID`,
  `OPAYO_APPLE_PAY_DOMAIN`, the `DEMO_ENABLE_*` panel switches. See `.env.example`.
- **MyOpayo portal**: wallet enablement (Google/Apple/PayPal), the Google
  `gatewayMerchantId` display, Apple Pay certificate mode + domain registration.
- **Google Pay & Wallet Console**: your Google merchant ID (production only).
- **Apple Developer portal**: only if you self-manage Apple certificates —
  not needed for the web/Opayo-managed path.
- **In the code**: the fixed `opayoelavon` gateway name
  (`src/GooglePay/Configuration.php`); everything else comes from `.env`.
