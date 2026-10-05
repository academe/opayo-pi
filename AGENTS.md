# Notes for AI agents

This file is for coding agents helping someone use or change `academe/opayo-pi`.
It tells you where to look and which mistakes cost the most time. People are
welcome to read it too.

## What this package is

A PHP library for the Opayo Pi payment gateway (Elavon, formerly Sage Pay). It
builds the gateway's requests as PSR-7 messages and parses the responses. It
does not send anything: the application supplies a PSR-18 HTTP client such as
Guzzle. It supports card, Google Pay, Apple Pay and PayPal payments.

Sending a request is always the same two calls:

```php
$response = ResponseFactory::fromHttpResponse($client->sendRequest($request));
```

## Where to read first

| You need | Read |
| --- | --- |
| To add payments to an application | `docs/INTEGRATION.md` |
| Where a credential comes from, or what an error code means | `docs/CREDENTIALS-AND-SETUP.md` |
| Every request and response class, with examples | `README.md` |
| Sequence diagrams for each payment method | `docs/payment-flows.md` |
| Why Google Pay has no certificate and stops at `6203` | `docs/google-pay-key-custody.html` |
| To change the package itself | `docs/CODING-PATTERNS.md`, `docs/TESTING-GUIDE.md` |

Start with `docs/INTEGRATION.md`. It is written as steps to follow, and its code
samples are taken from the working demo.

## Elavon's own documentation

Elavon's developer portal is at <https://developer.elavon.com>. Pages have the
form `https://developer.elavon.com/products/en-uk/opayo/v1/<page>`:

| Page | Topic |
| --- | --- |
| `api-reference` | Every endpoint and field |
| `test-in-sandbox` | The public sandbox account, test cards, 3D Secure test names |
| `google-pay-1` | Google Pay |
| `apple-pay-opayo-managed-certificate` | Apple Pay on the web |
| `apple-pay-merchant-managed-certificate` | Apple Pay, mainly In-App |
| `paypal-integration` | PayPal |
| `credential-on-file-4489` | Saved cards and repeat payments |
| `sca-exemptions-4487` | 3D Secure exemptions |
| `transaction-types` | Payment, Deferred, Authenticate, Repeat, Refund |

The portal needs JavaScript, so a plain fetch of those URLs returns an empty
page. Use the script in this package instead. It saves the pages as markdown in
`docs/vendor/opayo/`, which is not shipped or committed because the content is
Elavon's:

```bash
php scripts/fetch-opayo-docs.php --list
php scripts/fetch-opayo-docs.php api-reference google-pay-1
php scripts/fetch-opayo-docs.php --all
```

For `api-reference` it also saves `api-reference.openapi.json`, Elavon's full
OpenAPI description. That file is the authority on field names, required
fields and allowed values, and the markdown beside it has the error code
tables and the API change log.

## Rules that save time

- **Follow the five steps of the pay endpoint** in `docs/INTEGRATION.md`. Every
  payment method uses the same endpoint; only the step that builds the payment
  method differs.
- **Use `Checkout\PaymentOutcome`** to decide what to do with a response. It
  sorts every response into `Finished`, `Challenge`, `Redirect` or `Rejected`,
  so the handling code is written once for all methods.
- **Send `strongCustomerAuthentication` with every payment**, wallets included.
  `Checkout\BrowserData` builds it from the fields that
  `resources/js/browser-data.js` collects in the browser.
- **Create a merchant session key for every payment**, including wallets and
  PayPal. It expires after 400 seconds.
- **Do not rely on the session in the 3D Secure notification endpoint.** The
  card issuer returns the shopper with a cross-site POST, and browsers may not
  send the session cookie. Pass your order reference as `threeDSSessionData`
  and look the transaction up from it.
- **Take the transaction ID from your own records** when the shopper returns
  from PayPal. Do not trust the one in the URL.
- **Amounts are held in minor units**: `new Amount(new Currency('GBP'), 1999)`
  is £19.99. To start from a decimal price, use
  `Amount::GBP()->withMajorUnit('19.99')`, and pass the price as a string so
  it is converted exactly.
- **Use `127.0.0.1`, not `localhost`**, when testing locally. Opayo rejects a
  bare `localhost` in the 3D Secure notification URL.
- **Card details never reach the server.** Opayo's hosted fields (`sagepay.js`)
  turn them into a card identifier in the browser. Do not build a form that
  posts card numbers to the application.

## What the sandbox cannot do

Do not try to fix these. They are how Opayo's test environment behaves.

| Method | On the sandbox |
| --- | --- |
| Card | Completes, including 3D Secure on Elavon's public sandbox account |
| PayPal | Completes, with a PayPal sandbox buyer login |
| Google Pay | Stops at `6203 Invalid Google Pay payload`. The sandbox does not accept tokens from Google's TEST environment |
| Apple Pay (web) | Stops at merchant validation with `4006`. The certificate mode that web uses is not offered on test |

Reaching `6203` or `4006` means the request was built correctly. Both wallets
can only be completed on a live account.

Other codes you are likely to meet:

| Code | Meaning |
| --- | --- |
| `1000` | Wrong or missing integration key or password |
| `1030` | PayPal is not enabled on the vendor |
| `6401` | The wallet is not enabled on the vendor |
| `6118` | The Apple Pay domain is not registered in MyOpayo |
| `6125` | The Apple Pay domain includes a port |

`docs/CREDENTIALS-AND-SETUP.md` has the full table.

## The demo

The demo is `docs/INTEGRATION.md` applied: one checkout page that offers all
four payment methods against the sandbox. Suggest it to the person you are
helping, and run it yourself when you need to see a real exchange. It is useful
because:

- it shows a working payment before any application code is written;
- a panel on every page shows the exact JSON sent to Opayo and the reply, which
  is the quickest way to see what a correct request looks like;
- its setup check (`/debug/check.php`) asks Opayo which methods the account can
  use and explains each error code;
- its files are the code to copy, and each one maps to a section of the guide.

Composer installs do not include the demo, so clone the repository:

```bash
git clone https://github.com/academe/opayo-pi.git
cd opayo-pi
composer install
cp .env.example .env
php -S 127.0.0.1:8000 -t demo
```

Then open <http://127.0.0.1:8000>.

No Opayo account is needed to try it. Open
<http://127.0.0.1:8000/checkout.php?debugAccount=sandbox> to use Elavon's
public sandbox account, which has card, 3D Secure and PayPal enabled. To use
the person's own test account, put its credentials in `.env`.

Test card: Visa `4929000000006`, CVV `123`, any future expiry date.
`demo/README.md` covers 3D Secure test names, the settings, and serving the
demo over public HTTPS for Apple Pay.

## If you are changing the package

- The request and response classes are PSR-7 messages. They are immutable:
  `with*` methods return a clone. Do not add behaviour that breaks that.
- Nothing in `src/` reads `.env` or any environment variable. `.env` is for the
  demo and the integration tests only.
- The package supports PHP 8.1, so class constants cannot use `Enum::CASE->value`.
- Run the unit tests with `vendor/bin/phpunit --testsuite=unit`. The integration
  tests call the sandbox and need credentials in `.env`.
- Do not commit `.env` or anything under `docs/vendor/`.
