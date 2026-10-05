# Integrating Opayo Pi

This guide shows how to take card, Google Pay, Apple Pay and PayPal payments
with this package: the front-end code each method needs, the back-end config,
and the endpoints you host. [CREDENTIALS-AND-SETUP.md](CREDENTIALS-AND-SETUP.md)
explains where each credential comes from. The demo in `demo/` is this guide
applied; the code samples below are taken from it, trimmed where noted.

## 1. Choose your payment methods

| Method | What the shopper sees | Endpoints you host | Sandbox | Live |
| ------ | --------------------- | ------------------ | ------- | ---- |
| Card | Opayo's hosted card fields in your page | pay, 3D Secure notification | Completes | Completes |
| Google Pay | Google's button and sheet | pay, 3D Secure notification | Stops at `6203` (the sandbox does not accept Google's TEST tokens) | Completes |
| Apple Pay | Apple's button and sheet (Safari) | Apple merchant validation, pay | Stops at `4006` (Opayo-managed certificate not offered on test) | Completes |
| PayPal | A redirect to PayPal and back | pay, PayPal return | Completes | Completes |

Whether a method is "on" for a shopper depends on three layers:

1. **Your config.** Your application keeps the list of methods it offers. It
   decides which buttons to show and which payments the server accepts.
2. **Opayo enrolment.** Each wallet and PayPal must be enabled on your vendor
   in MyOpayo. There is no API to ask which methods are enabled; a mismatch
   shows up on the first payment as `6401` (wallet not enabled), `1030`
   (PayPal not enabled) or `4006`. Check each method once, at go-live.
3. **The shopper's device.** Wallet buttons only appear where the browser can
   pay: Google's `isReadyToPay` and Apple's `ApplePaySession.canMakePayments`
   decide, and both checks are part of the front-end code below.

## 2. The core

Every integration needs this section, whichever methods it offers.

### Config

An endpoint, your credentials, any PSR-18 HTTP client, and your list of
enabled methods. From `demo/bootstrap.php` (the demo also gives Guzzle a
handler stack for its debug tooling; you do not need one):

```php
$endpoint = new Endpoint(($_ENV['OPAYO_ENVIRONMENT'] ?? 'test') === 'live' ? Endpoint::MODE_LIVE : Endpoint::MODE_TEST);
$auth = new Auth($_ENV['OPAYO_VENDOR_NAME'], $_ENV['OPAYO_INTEGRATION_KEY'], $_ENV['OPAYO_INTEGRATION_PASSWORD']);
$client = new \GuzzleHttp\Client(['http_errors' => false]);

$enabledMethods = enabledMethods($_ENV);
$baseUrl = baseUrl($_SERVER);

// Where the demo records which Opayo transaction belongs to which order. In
// your application this is your orders table.
$orderStore = sys_get_temp_dir() . '/opayo-pi-demo-orders';

$config = [
    'merchantName' => 'Opayo Pi Demo',
    // MyOpayo > Settings > Pay Methods > Google Pay. Not a secret.
    'googlePayMerchantId' => ($_ENV['OPAYO_GOOGLE_PAY_MERCHANT_ID'] ?? '') ?: $_ENV['OPAYO_VENDOR_NAME'],
    // Google Pay & Wallet Console; only read in PRODUCTION.
    'googleMerchantId' => ($_ENV['GOOGLE_PAY_MERCHANT_ID'] ?? '') ?: null,
    'googlePayEnvironment' => GooglePayEnvironment::tryFrom(strtoupper($_ENV['GOOGLE_PAY_ENVIRONMENT'] ?? 'TEST'))
        ?? GooglePayEnvironment::Test,
    // The domain registered in MyOpayo > Settings > Pay Methods > Apple Pay.
    'applePayDomain' => applePayDomain($_ENV, $_SERVER),
    // Card payments only: UseMSPSetting, Force, Disable or ForceIgnoringRules.
    'apply3DSecure' => ($_ENV['OPAYO_APPLY_3D_SECURE'] ?? '') ?: 'UseMSPSetting',
];
```

The helpers are small and in `demo/config.php`:

- `enabledMethods()` returns the methods you offer, here from switches in
  `.env` (`DEMO_ENABLE_CARD=0` turns cards off). Keep this list however your
  application keeps settings.
- `baseUrl()` is the scheme and host your site is served from, used to build
  the callback URLs Opayo and the browser come back to. Behind a proxy or
  tunnel, take the scheme from `X-Forwarded-Proto` so the URLs are `https://`.
- `clientIp()` is the shopper's IP address, which Opayo needs for 3D Secure
  (`browserIP`) and inside the wallet payment methods. Behind a proxy, load
  balancer or tunnel, the connection comes from the proxy, so `REMOTE_ADDR` is
  the proxy's address; the shopper's is the first entry of `X-Forwarded-For`.
  Only believe that header when the connection comes from your own proxy,
  because anyone can send it. The demo trusts a tunnel agent on the same
  machine (loopback); behind a load balancer, trust its address. Opayo's
  `browserIP` takes IPv4 only, so `BrowserData` sends `127.0.0.1` for an IPv6
  shopper.
- `applePayDomain()` is the Apple Pay domain registered in MyOpayo.
- `rememberTransaction()` and `recallTransaction()` record and look up which
  Opayo transaction belongs to which of your order references. They stand in
  for a column on your orders table.

Every payment needs a merchant session key:

```php
function merchantSessionKey(ClientInterface $client, Endpoint $endpoint, Auth $auth): string
{
    $response = ResponseFactory::fromHttpResponse(
        $client->sendRequest(new CreateSessionKey($endpoint, $auth))
    );

    if (! $response instanceof SessionKey) {
        throw new RuntimeException('Opayo would not issue a merchant session key. Check your credentials in .env.');
    }

    return $response->getMerchantSessionKey();
}
```

Sending any request is always the same two calls:
`$client->sendRequest($request)`, then `ResponseFactory::fromHttpResponse(...)`.

### The pay endpoint

Every method posts to one endpoint, and every method follows the same steps.
Only step 2 differs. From `demo/pay.php`.

**Step 1.** Work out which method is paying, and refuse any you do not offer.
Check on the server, not only in the page. This keeps the server consistent
with your config; it is not what stops forged tokens (Opayo's decryption does
that).

```php
$method = postedMethod($_POST);
if ($method === null || ! in_array($method, $enabledMethods, true)) {
    $refuse('This payment method is not offered.');
}
```

`$refuse` answers HTTP 400 with the reason (as JSON for the Apple Pay fetch).
`postedMethod()` reads which credential the form carried, and refuses a post
with none or with more than one:

```php
function postedMethod(array $post): ?string
{
    $found = array_keys(array_filter([
        'card' => ($post['card-identifier'] ?? '') !== '',
        'googlepay' => ($post['googlePayToken'] ?? '') !== '',
        'applepay' => ($post['applePayToken'] ?? '') !== '',
        'paypal' => ($post['method'] ?? '') === 'paypal',
    ]));

    return count($found) === 1 ? $found[0] : null;
}
```

The card drop-in created its session key at checkout; every other method
needs a fresh one:

```php
// The shopper's address, not the tunnel's or proxy's: see clientIp().
$clientIp = clientIp($_SERVER);

$sessionKey = ($_POST['merchantSessionKey'] ?? '') ?: merchantSessionKey($client, $endpoint, $auth);
```

**Step 2.** Turn the posted credential into a payment method. This is the
only line that differs between methods:

```php
try {
    $paymentMethod = match ($method) {
        'card' => new SingleUseCard($sessionKey, $_POST['card-identifier']),
        'googlepay' => GooglePayPayment::fromGoogleToken($sessionKey, $clientIp, $_POST['googlePayToken']),
        'applepay' => ApplePayPayment::fromAppleToken(
            $sessionKey,
            $clientIp,
            $_POST['applePayToken'],
            ($_POST['appleSessionValidationToken'] ?? '') ?: null
        ),
        'paypal' => new PayPalPayment($sessionKey, $baseUrl . '/paypal-return.php'),
    };
} catch (InvalidArgumentException $e) {
    // A token the package cannot read, e.g. not what the wallet gave the browser.
    $refuse('The payment details could not be read: ' . $e->getMessage());
}
```

**Step 3.** Build the 3D Secure data from the shopper's browser, for every
method (a wallet token can be challenged too). Card payments also take your
3D Secure setting; wallet tokens are already authenticated on the device.

```php
$strongCustomerAuthentication = BrowserData::fromArray($_POST)->toStrongCustomerAuthentication(
    $baseUrl . '/notification.php',
    $clientIp,
    $_SERVER['HTTP_ACCEPT'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? ''
);

$options = [
    'entryMethod' => EntryMethod::Ecommerce,
    'strongCustomerAuthentication' => $strongCustomerAuthentication,
];

if ($method === 'card') {
    $options['apply3DSecure'] = $config['apply3DSecure'];
}
```

**Step 4.** Build the payment with your own order reference (unique per
attempt), send it, and classify the response:

```php
$vendorTxCode = 'DEMO-' . bin2hex(random_bytes(8));

$request = new CreatePayment(
    $endpoint,
    $auth,
    $paymentMethod,
    $vendorTxCode,
    (new Amount(new Currency('GBP'), 0))->withMajorUnit($_POST['amount'] ?? '9.99'),
    $_POST['description'] ?? 'Demo purchase',
    new Address('88', '88 Avenue Road', 'London', 'EC2A 4DP', 'GB'),
    new Person($_POST['firstName'] ?? 'Sam', $_POST['lastName'] ?? 'Jones', $_POST['email'] ?? 'sam.jones@example.com'),
    options: $options
);

$outcome = PaymentOutcome::fromResponse(
    ResponseFactory::fromHttpResponse($client->sendRequest($request))
);
```

**Step 5.** Act on the outcome, as described next.

### Handling the outcome

`PaymentOutcome` sorts every response into one of four kinds. The same four
arrive whatever the method, so this code is written once.

| Kind | Means | What to do | Getters |
| ---- | ----- | ---------- | ------- |
| `Finished` | Authorised or declined | Show the result | `isSuccessful()`, `transactionId()`, `status()`, `statusDetail()` |
| `Challenge` | 3D Secure challenge | Store the transaction ID, POST the browser to the issuer | `transactionId()`, `acsUrl()`, `formFields()` |
| `Redirect` | PayPal approval needed | Store the transaction ID, redirect to PayPal | `transactionId()`, `redirectUrl()` |
| `Rejected` | Opayo refused the request | Show the errors | `errors()` |

Calling a getter on the wrong kind throws `LogicException`. `summary()` works
for every kind and returns a plain array you can store or log, and
`response()` returns the original response object.

```php
if ($outcome->kind === OutcomeKind::Challenge) {
    // Send the browser to the card issuer, who returns it to notification.php.
    // Record the transaction against your order, and give the issuer your order
    // reference to hand back: the return may arrive without the session cookie.
    rememberTransaction($orderStore, $vendorTxCode, $outcome->transactionId());
    $fields = $outcome->formFields(base64_encode($vendorTxCode));
    // Render a form that POSTs $fields to $outcome->acsUrl().
    // The demo shows a button; a real site would usually submit it automatically.
}

if ($outcome->kind === OutcomeKind::Redirect) {
    // PayPal: the shopper approves there, then comes back to paypal-return.php.
    $_SESSION['transactionId'] = $outcome->transactionId();
    header('Location: ' . $outcome->redirectUrl());
    exit;
}

// Finished or Rejected: show the result (redirect first, so a refresh cannot pay twice).
$_SESSION['outcome'] = $outcome->summary();
header('Location: complete.php');
```

`formFields()` takes your own session data, which the issuer passes back to
your notification URL untouched. Use your order reference; do not use the
transaction ID (Opayo rejects that). `rememberTransaction()` stands in for
saving the transaction ID on your order record.

### The 3D Secure notification endpoint

After a challenge, the issuer sends the shopper's browser back with the result
(`cres`) and your order reference (`threeDSSessionData`). This is a
cross-site POST, so **do not rely on the session**: browsers may not send the
session cookie with it. Look the transaction up from the order reference,
forward the result to Opayo, and handle the outcome like any other. From
`demo/notification.php`:

```php
if (! Secure3Dv2Notification::isRequest($_POST)) {
    header('Location: checkout.php');
    exit;
}

$orderRef = base64_decode((string) ($_POST['threeDSSessionData'] ?? ''), true);
$transactionId = $orderRef !== false ? recallTransaction($orderStore, $orderRef) : null;

if ($transactionId === null) {
    // Show "this 3D Secure result could not be matched to an order".
}

$response = ResponseFactory::fromHttpResponse($client->sendRequest(
    new CreateSecure3Dv2Challenge($endpoint, $auth, Secure3Dv2Notification::fromData($_POST), $transactionId)
));

$_SESSION['outcome'] = PaymentOutcome::fromResponse($response)->summary();
header('Location: complete.php');
```

The return is a browser POST, not a server-to-server call, so it reaches
`localhost` too. Opayo rejects a bare `localhost` hostname in the notification
URL, though; use `127.0.0.1`.

### Browser data

3D Secure needs a few values only the browser knows. Serve
[`resources/js/browser-data.js`](../resources/js/browser-data.js) from your
public assets and fill every payment form before it is submitted:

```html
<script src="/js/browser-data.js"></script>
<script>document.querySelectorAll('form[data-pay]').forEach(OpayoBrowserData.fill);</script>
```

On the server, `BrowserData::fromArray($_POST)` reads those fields, with safe
defaults for any that are missing.

## 3. Payment methods

### Card

#### Front end

Opayo's drop-in (`sagepay.js`) renders the card fields in an iframe and adds a
`card-identifier` to your form on submit. Your server never sees the card
number. From `demo/methods/card.php`:

```php
<form method="post" action="pay.php" data-pay class="space-y-4">
    <?= orderFields($order) ?>
    <input type="hidden" name="merchantSessionKey" value="<?= h($cardSessionKey) ?>">
    <div id="sp-container" class="border border-slate-200 rounded-lg"></div>
    <button type="submit">Pay by card</button>
</form>

<script src="<?= h($endpoint->getJavascriptUrl()) ?>"></script>
<script>
    sagepayCheckout({ merchantSessionKey: <?= json_encode($cardSessionKey) ?> }).form();
</script>
```

`$cardSessionKey` comes from `merchantSessionKey()` when the page renders.
Check the method is enabled on the server too: `pay.php` refuses methods you
do not offer.

#### Back-end config

Nothing beyond the core. `OPAYO_APPLY_3D_SECURE` sets the 3D Secure strength
for cards (`UseMSPSetting` by default, which follows your MyOpayo rules).

#### Extra endpoint

None. A challenge returns to the shared 3D Secure notification endpoint.

#### Testing

Completes on the sandbox. See the test cards and magic cardholder names in
`demo/README.md`, and [Card](CREDENTIALS-AND-SETUP.md#card).

### Google Pay

#### Front end

The package builds the Google Pay request objects
(`GooglePay\Configuration`), and Google's `pay.js` shows the button and the
sheet. The token is posted as `googlePayToken`. From
`demo/methods/googlepay.php`:

```php
$googlePay = (new GooglePayConfiguration(
    gatewayMerchantId: $config['googlePayMerchantId'],
    merchantName: $config['merchantName'],
    googleMerchantId: $config['googleMerchantId'],
    environment: $config['googlePayEnvironment'],
))->clientConfiguration((new Amount(new Currency('GBP'), 0))->withMajorUnit($order['amount']));
```

```html
<form id="googlepay-form" method="post" action="pay.php" data-pay>
    <?= orderFields($order) ?>
    <input type="hidden" name="googlePayToken">
    <div id="googlepay-button"></div>
</form>

<script src="https://pay.google.com/gp/p/js/pay.js"></script>
<script>
    const config = <?= json_encode($googlePay) ?>;
    const form = document.getElementById('googlepay-form');
    const client = new google.payments.api.PaymentsClient({ environment: config.environment });

    // Ask Google whether this browser can pay at all; only then show the button.
    client.isReadyToPay(config.isReadyToPayRequest).then((response) => {
        if (! response.result) return;
        document.getElementById('googlepay-button').appendChild(client.createButton({
            buttonType: 'pay',
            buttonSizeMode: 'fill',
            onClick: pay,
        }));
    });

    function pay() {
        client.loadPaymentData(config.paymentDataRequest).then((paymentData) => {
            form.elements.googlePayToken.value = paymentData.paymentMethodData.tokenizationData.token;
            form.submit();
        });
    }
</script>
```

Check the method is enabled on the server too: `pay.php` refuses methods you
do not offer.

#### Back-end config

- `OPAYO_GOOGLE_PAY_MERCHANT_ID`: the gatewayMerchantId from MyOpayo
  (Settings > Pay Methods > Google Pay). Not a secret.
- `GOOGLE_PAY_ENVIRONMENT`: `TEST` or `PRODUCTION`.
- `GOOGLE_PAY_MERCHANT_ID`: your Google merchant ID, required in `PRODUCTION`.

#### Extra endpoint

None.

#### Testing

Google's `TEST` environment returns a genuine token, signed with Google's test
key and addressed to `gateway:opayoelavon`. Opayo's sandbox accepts the
request and creates a transaction, then rejects the token with `6203 Invalid
Google Pay payload`: it does not accept TEST tokens. Reaching `6203` proves the
vendor is enrolled and the request is well formed. Completing a payment needs
`PRODUCTION` on a live account. See [Google Pay](CREDENTIALS-AND-SETUP.md#google-pay).

### Apple Pay

#### Front end

Safari shows the sheet. Before it opens, Apple asks your server to validate
the merchant. After the shopper authorises, post the token by `fetch`, because
the sheet must be completed with the real outcome before the page moves on.
From `demo/methods/applepay.php`:

```js
if (! window.ApplePaySession || ! ApplePaySession.supportsVersion(VERSION) || ! ApplePaySession.canMakePayments()) {
    status('Apple Pay needs Safari on an Apple device with a card in Wallet.');
    return;
}

session.onvalidatemerchant = function () {
    fetch('apple-session.php', { method: 'POST' })
        .then((r) => r.json())
        .then((data) => {
            form.elements.appleSessionValidationToken.value = data.sessionValidationToken || '';
            session.completeMerchantValidation(data.merchantSession);
        });
};

session.onpaymentauthorized = function (event) {
    form.elements.applePayToken.value = JSON.stringify(event.payment.token);

    fetch('pay.php', { method: 'POST', body: new FormData(form) })
        .then((r) => r.json())
        .then((result) => {
            session.completePayment(result.approved ? ApplePaySession.STATUS_SUCCESS : ApplePaySession.STATUS_FAILURE);
            window.location = result.completeUrl;
        });
};
```

The form carries `resultFormat=json`, so `pay.php` answers
`{"approved": bool, "completeUrl": "complete.php"}` instead of redirecting.
Check the method is enabled on the server too: `pay.php` refuses methods you
do not offer.

#### Back-end config

`OPAYO_APPLE_PAY_DOMAIN`: the domain registered in MyOpayo (Settings > Pay
Methods > Apple Pay). It must be HTTPS with no port.

#### Extra endpoint

`apple-session.php`, the merchant validation endpoint:

```php
$response = ResponseFactory::fromHttpResponse($client->sendRequest(
    new CreateApplePaySession($endpoint, $auth, $config['applePayDomain'])
));

if ($response instanceof ApplePaySession && $response->getMerchantSession()) {
    exit(json_encode([
        'merchantSession' => $response->getMerchantSession(),
        'sessionValidationToken' => $response->getSessionValidationToken(),
    ]));
}

// Opayo said no: pass on its own code and message (4006, 6118, 6125, ...).
```

#### Testing

Opayo's sandbox does not offer the Opayo-managed certificate mode this uses,
so merchant validation stops at `4006` there. It can only be completed on a
live account. Before that, `6125` means the domain carries a port and `6118`
means it is not registered. See
[Apple Pay](CREDENTIALS-AND-SETUP.md#apple-pay) and
[Apple Pay: which certificate mode?](CREDENTIALS-AND-SETUP.md#apple-pay-which-certificate-mode).

### PayPal

#### Front end

No token is made in the browser; the form just says "PayPal". From
`demo/methods/paypal.php`:

```php
<form method="post" action="pay.php" data-pay>
    <?= orderFields($order) ?>
    <input type="hidden" name="method" value="paypal">
    <button type="submit">Pay with PayPal</button>
</form>
```

Check the method is enabled on the server too: `pay.php` refuses methods you
do not offer.

#### Back-end config

Nothing beyond the core. PayPal must be enabled on your vendor.

#### Extra endpoint

`paypal-return.php`. Opayo sends the shopper back with the `transactionId` on
the URL, which says nothing about the result, so fetch the transaction. Trust
the transaction you recorded for the order (`pay.php` stored it when it
redirected to PayPal), not the URL: anyone can edit a query string.

```php
$transactionId = $_SESSION['transactionId'] ?? null;

if ($transactionId === null || (isset($_GET['transactionId']) && $_GET['transactionId'] !== $transactionId)) {
    // Show "this PayPal return does not match your order".
}

$response = ResponseFactory::fromHttpResponse($client->sendRequest(
    new FetchTransaction($endpoint, $auth, $transactionId)
));

$_SESSION['outcome'] = PaymentOutcome::fromResponse($response)->summary();
header('Location: complete.php');
```

Before you fulfil the order, check the fetched transaction matches it (at
least the amount). Until PayPal has reported back to Opayo, fetching the
transaction answers `Transaction not found`.

#### Testing

Completes on the sandbox with a PayPal sandbox buyer login. See
[PayPal](CREDENTIALS-AND-SETUP.md#paypal).

## 4. Going live

- [ ] Set `OPAYO_ENVIRONMENT=live` and use your live integration key and password.
- [ ] Enable each method you offer on your live vendor in MyOpayo.
- [ ] Register your live Apple Pay domain in MyOpayo.
- [ ] Set `GOOGLE_PAY_ENVIRONMENT=PRODUCTION` and `GOOGLE_PAY_MERCHANT_ID`.
- [ ] Make one real payment with each method, then refund it.

**Reporting a problem:** include the request and response bodies. They show
exactly what was sent and what Opayo answered. The demo's debug panel shows
them; in your application, log them.

## 5. The worked example

The demo is this guide applied. It is not included in Composer installs: clone
the repository to run it (see `demo/README.md`).

| Guide section | Demo file |
| ------------- | --------- |
| Config | `demo/bootstrap.php`, `demo/config.php` |
| The pay endpoint, handling the outcome | `demo/pay.php`, `demo/complete.php` |
| 3D Secure notification | `demo/notification.php` |
| Browser data | `resources/js/browser-data.js`, used in `demo/checkout.php` |
| Card | `demo/methods/card.php` |
| Google Pay | `demo/methods/googlepay.php` |
| Apple Pay | `demo/methods/applepay.php`, `demo/apple-session.php` |
| PayPal | `demo/methods/paypal.php`, `demo/paypal-return.php` |

The `demo/debug/` folder (wire panel, setup check, test card) is demo-only
tooling, attached by one marked line in `bootstrap.php`; delete both and what
remains is exactly this guide.
