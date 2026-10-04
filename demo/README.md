# Opayo Pi Demo

The demo is [docs/INTEGRATION.md](../docs/INTEGRATION.md) applied: one checkout
page offering card, Google Pay, Apple Pay and PayPal, built exactly the way the
guide describes. Read the guide for what each file does and why; this README is
about running the demo.

It runs on the PHP built-in server against the Opayo sandbox. No SSL and no
public URL are needed for card and PayPal: every gateway call is outbound HTTPS
from PHP, and the 3D Secure result comes back through the shopper's browser.
Apple Pay needs a public HTTPS domain (see "Running it publicly" below).

> **New to the credentials?** [docs/CREDENTIALS-AND-SETUP.md](../docs/CREDENTIALS-AND-SETUP.md)
> is the map: who owns each credential, where you get it, where it goes, and
> which error code tells you it is missing or wrong.

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

Open the **public** URL the tunnel prints, not `127.0.0.1:8000`. Behind the
tunnel the demo builds its callback URLs (3D Secure notification, PayPal
return) as `https://` from the `X-Forwarded-Proto` header.

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

## Layout

```text
demo/
  index.php           redirects to checkout.php
  bootstrap.php       loads .env; builds the endpoint, auth, HTTP client and config
  config.php          small pure helpers (method switches, URLs), unit tested
  layout.php          page chrome (presentation only)
  checkout.php        the order + one front end per enabled method
  pay.php             CORE: the one pay endpoint every method posts to
  notification.php    CORE: 3D Secure return (card, Google Pay)
  complete.php        CORE: the one result page every flow ends on
  methods/
    card.php          sagepay.js hosted card fields
    googlepay.php     Google Pay button, config from GooglePay\Configuration
    applepay.php      ApplePaySession
    paypal.php        a button posting method=paypal
  apple-session.php   APPLE PAY: merchant validation endpoint
  paypal-return.php   PAYPAL: return endpoint
  debug/              demo-only tooling, see below
  serve-public.sh, serve-public.ps1
```

Everything outside `debug/` is integration code, and each file maps to a
section of the guide (see its "Worked example" table).

## Debug layer

`demo/debug/` adds tooling a real site would not have, attached by one marked
line in `bootstrap.php`. Delete that line and the folder, or set
`DEMO_DEBUG=0`, and the demo is the bare integration.

- **Wire panel:** on the right of every page, the raw request and response
  bodies, what the browser brought to the callback endpoints (the 3D Secure
  `cres`, the PayPal return), and the PHP session. Entries survive redirects
  and are shown once, on the next page that renders.
- **Setup check** (`debug/check.php`): asks Opayo once whether each method can
  work for this account, and explains each error code it gets back
  (`4006`, `6118`, `6125`, `6203`, `6401`, `1030`). The Google Pay and PayPal
  probes start a payment that cannot complete, so they only run against the
  test endpoint.
- **Test card** (`debug/test-card.php`): tokenises a sandbox card on the server
  with a chosen magic cardholder name, then pays through the normal `pay.php`.
  Test endpoint only.
- **Account switcher:** a link in the panel runs the whole demo against your
  `.env` account or Elavon's public sandbox profile, which has the 3D Secure
  simulation and PayPal enabled.

## Settings

All read from `.env` in the repository root; see `.env.example`.

| Key | Purpose |
| --- | ------- |
| `OPAYO_VENDOR_NAME`, `OPAYO_INTEGRATION_KEY`, `OPAYO_INTEGRATION_PASSWORD` | Your Opayo credentials |
| `OPAYO_ENVIRONMENT` | `test` (default) or `live` |
| `OPAYO_APPLY_3D_SECURE` | Card 3D Secure: `UseMSPSetting` (default), `Force`, `Disable`, `ForceIgnoringRules` |
| `OPAYO_GOOGLE_PAY_MERCHANT_ID` | Google Pay gatewayMerchantId from MyOpayo; defaults to the vendor name |
| `GOOGLE_PAY_ENVIRONMENT` | `TEST` (default) or `PRODUCTION` |
| `GOOGLE_PAY_MERCHANT_ID` | Google merchant ID; needed in `PRODUCTION` |
| `OPAYO_APPLE_PAY_DOMAIN` | Apple Pay domain registered in MyOpayo; defaults to the served host |
| `DEMO_ENABLE_CARD`, `DEMO_ENABLE_GOOGLE_PAY`, `DEMO_ENABLE_APPLE_PAY`, `DEMO_ENABLE_PAY_PAL` | `0` stops offering that method; the page says "not offered" and the endpoints refuse it |
| `DEMO_DEBUG` | `0` turns off the debug layer |

## What the sandbox can show

- **Card** and **PayPal** complete end to end.
- **Google Pay** reaches Opayo with a genuine token from Google's TEST
  environment. Opayo creates a transaction, then rejects the token with `6203`:
  the sandbox does not accept TEST tokens. Reaching that point proves the vendor
  is enrolled and the request is well formed.
- **Apple Pay** stops at merchant validation with `4006`: the sandbox does not
  offer the Opayo-managed certificate mode. It can only be completed live.

The guide's "Testing" section for each method has the detail.

## Test cards

| Card | Number | CVV |
| ---- | ------ | --- |
| Visa | `4929000000006` | `123` |
| MasterCard | `5404000000000001` | `123` |

Any future expiry date (MMYY). See `docs/TESTING-GUIDE.md` for more.

## Magic cardholder names (3D Secure v2 outcomes)

With 3D Secure applied, the sandbox picks the authentication outcome from the
*cardholder name* on the card identifier. Use the public sandbox account, set
`OPAYO_APPLY_3D_SECURE=Force`, and choose the name on the test card page
(`debug/test-card.php`), or type it as the name in the card fields:

| Name | Outcome |
| ---- | ------- |
| `CHALLENGE` | Challenge flow: redirect to the ACS, which displays the word to enter. Correct answer = authenticated; anything else = failed. |
| `SUCCESSFUL` | Frictionless flow, authentication succeeds (no redirect). |
| `NOTAUTH` | Frictionless flow, authentication fails. |
| `PROOFATTEMPT` | Attempted authentication, treated as successful. |
| `NOTENROLLED`, `REJECT`, `TECHDIFFICULTIES`, `ERROR` | Other failure modes. |

Any other name (e.g. a real one) fails 3D Secure authentication in the
sandbox. Personal test accounts often have no 3D Secure simulation at all and
reject every attempt with "3D-Authentication failed"; use the public sandbox
account for these flows.
