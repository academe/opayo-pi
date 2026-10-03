# Demo: all payment types on one checkout page — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the tab-per-method demo with one checkout page that offers card, Google Pay, Apple Pay and PayPal together, each showing its own integration code, each falling back to an honest placeholder when it cannot run.

**Architecture:** One `pay.php` endpoint turns whatever credential the browser posted into a `PaymentMethodInterface` and runs a single `CreatePayment`; the response type decides the next step. `index.php` renders the order once, then includes one self-contained partial per method from `demo/methods/`. Each partial computes its own readiness (demo flag → config → gateway → browser) and renders either its live control or a placeholder stating the reason.

**Tech Stack:** PHP 8.1+, plain PHP demo (no framework), Tailwind via CDN, PHPUnit 10, the `academe/opayo-pi` library itself.

**Spec:** `docs/superpowers/specs/2026-09-05-demo-all-payment-types-design.md`

## Global Constraints

- **No git commits by the agent.** The user commits. Every task ends at a review checkpoint, not a `git commit`. Never run `git add`/`git commit`/`git push`.
- **Edits stay inside** `c:\Users\jason\Herd\opayo-pi\`. Never touch files elsewhere.
- **Run the demo** from repo root: `php -S 127.0.0.1:8000 -t demo` (not `localhost` — Opayo rejects a bare hostname).
- **Run PHPUnit** as `php vendor/phpunit/phpunit/phpunit --exclude-group integration` (the `vendor/bin/phpunit` shim fails in Git Bash on this machine).
- **SCA is always sent** on `CreatePayment`; you cannot know in advance whether a Google `PAN_ONLY` token will be challenged.
- **`apply3DSecure` is card-only.** Never force it on a wallet token.
- Demo namespace for the flags is `DEMO_*`, never `OPAYO_*`, so it never reads as "Opayo has this enabled on my vendor".
- Placeholders never overclaim: "Google Pay ready" means Google is ready, not that Opayo is enrolled.

---

## File structure

```
demo/
  index.php            REWRITE: order fields once, then four method panels + wire panel
  pay.php              REWRITE: one endpoint, all methods -> CreatePayment
  apple-session.php    NEW: onvalidatemerchant -> CreateApplePaySession
  notification.php     unchanged (3DS callback)
  paypal-return.php    unchanged (PayPal callback)
  shared.php           GROW: DEMO flag readers, readiness probes, payment-method
                       factory, SCA builder, outcome renderers
  methods/
    card.php           NEW: drop-in default + server-side capture toggle
    googlepay.php      NEW partial (logic moved from top-level googlepay.php)
    applepay.php       NEW partial
    paypal.php         NEW partial (logic moved from top-level paypal.php)
  googlepay.php        DELETE (logic split into pay.php + methods/googlepay.php)
  paypal.php           DELETE (logic split into pay.php + methods/paypal.php)

tests/
  Demo/                NEW: unit tests for the pure helpers in shared.php
    PaymentMethodFactoryTest.php
    ScaBuilderTest.php
    DemoFlagsTest.php

.env.example           GROW: DEMO_ENABLE_* flags + OPAYO_APPLE_PAY_DOMAIN
```

Note: `shared.php` currently defines functions in the global namespace and is `require`d by the demo scripts, not autoloaded. The new unit tests will `require` it directly. To make the pure helpers testable in isolation without side effects at require-time, the helper functions are plain global functions (matching the file's existing style); requiring the file must not emit output or open connections (it does not today — it only defines functions and calls `loadEnv()`/`session_start()`). **Guard `session_start()` and `loadEnv()` so requiring the file under PHPUnit is safe** (see Task 1).

---

### Task 1: Demo method flags + require-safety

**Files:**
- Modify: `demo/shared.php` (add flag readers near `demoAccount()`, guard session/env side effects)
- Create: `tests/Demo/DemoFlagsTest.php`
- Modify: `.env.example`

**Interfaces:**
- Produces:
  - `demoMethodEnabled(string $method): bool` — `$method` in `card|googlePay|applePay|payPal`; reads `DEMO_ENABLE_<UPPER_SNAKE>` from `$_ENV`, defaults `true` when unset.
  - `applePayDomain(): string` — `$_ENV['OPAYO_APPLE_PAY_DOMAIN']` or the request host from `baseUrl()` without scheme.

- [ ] **Step 1: Make requiring shared.php side-effect-safe under tests**

In `demo/shared.php`, wrap the two top-level side effects so they no-op in CLI/test context:

```php
// near the top, replace bare session_start();
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_start();
}
```

`loadEnv()` already guards on file existence; leave it, but confirm it does not fatal when `.env` is absent under CLI (it calls `exit` with a 500 — change that one line to only `http_response_code(500)`+`exit` when `PHP_SAPI !== 'cli'`, otherwise `return`, so tests can set `$_ENV` themselves).

- [ ] **Step 2: Write the failing test**

```php
<?php
namespace Academe\Opayo\Pi\Demo;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../demo/shared.php';

class DemoFlagsTest extends TestCase
{
    protected function setUp(): void
    {
        foreach (['DEMO_ENABLE_CARD','DEMO_ENABLE_GOOGLE_PAY','DEMO_ENABLE_APPLE_PAY','DEMO_ENABLE_PAY_PAL'] as $k) {
            unset($_ENV[$k]);
        }
    }

    public function testDefaultsToEnabledWhenUnset()
    {
        $this->assertTrue(demoMethodEnabled('card'));
        $this->assertTrue(demoMethodEnabled('googlePay'));
        $this->assertTrue(demoMethodEnabled('applePay'));
        $this->assertTrue(demoMethodEnabled('payPal'));
    }

    public function testZeroDisables()
    {
        $_ENV['DEMO_ENABLE_GOOGLE_PAY'] = '0';
        $this->assertFalse(demoMethodEnabled('googlePay'));
        $this->assertTrue(demoMethodEnabled('card'));
    }

    public function testApplePayDomainFallsBackToHost()
    {
        $_ENV['OPAYO_APPLE_PAY_DOMAIN'] = 'shop.example.com';
        $this->assertSame('shop.example.com', applePayDomain());
    }
}
```

- [ ] **Step 3: Run it, expect failure**

Run: `php vendor/phpunit/phpunit/phpunit tests/Demo/DemoFlagsTest.php`
Expected: FAIL — `Call to undefined function demoMethodEnabled()`.

- [ ] **Step 4: Implement the readers in shared.php**

```php
/**
 * Which demo panels are switched on. Defaults to enabled; set
 * DEMO_ENABLE_<METHOD>=0 in .env to hide one you are not working on.
 */
function demoMethodEnabled(string $method): bool
{
    $key = 'DEMO_ENABLE_' . strtoupper(preg_replace('/([a-z])([A-Z])/', '$1_$2', $method));
    return ($_ENV[$key] ?? '1') !== '0';
}

/**
 * The domain Apple Pay merchant validation runs against. Defaults to the
 * host the demo is served from.
 */
function applePayDomain(): string
{
    if (! empty($_ENV['OPAYO_APPLE_PAY_DOMAIN'])) {
        return $_ENV['OPAYO_APPLE_PAY_DOMAIN'];
    }
    return preg_replace('#^https?://#', '', baseUrl());
}
```

Note the snake mapping: `googlePay` → `DEMO_ENABLE_GOOGLE_PAY`, `payPal` → `DEMO_ENABLE_PAY_PAL`. Keep the test's env keys in step with this.

- [ ] **Step 5: Run it, expect pass**

Run: `php vendor/phpunit/phpunit/phpunit tests/Demo/DemoFlagsTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Add the flags to .env.example**

Append after the Google Pay block:

```
# Which methods the demo offers (demo/index.php). All on by default; set any
# to 0 to hide that panel while you work on the others. DEMO_, not OPAYO_:
# this is about the demo page, not whether Opayo has the wallet enabled.
DEMO_ENABLE_CARD=1
DEMO_ENABLE_GOOGLE_PAY=1
DEMO_ENABLE_APPLE_PAY=1
DEMO_ENABLE_PAY_PAL=1

# The domain Apple Pay merchant validation runs against; must be registered on
# the vendor in MyOpayo. Defaults to the host the demo is served from.
OPAYO_APPLE_PAY_DOMAIN=
```

- [ ] **Step 7: Checkpoint** — run the full unit suite (`php vendor/phpunit/phpunit/phpunit --exclude-group integration`), confirm still green, summarise for the user. Do not commit; the user reviews and commits.

---

### Task 2: SCA builder

**Files:**
- Modify: `demo/shared.php`
- Create: `tests/Demo/ScaBuilderTest.php`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: `scaFromRequest(array $post, string $notificationUrl, string $clientIp): StrongCustomerAuthentication` — builds the SCA object from posted browser fields (`browserColorDepth`, `browserScreenHeight`, `browserScreenWidth`, `browserTz`, `browserLanguage`), using `ChallengeWindowSize::Medium` and `TransType::GoodsAndServicePurchase`, `browserJavascriptEnabled: true`, `browserJavaEnabled: false`. This centralises the block currently duplicated in `pay.php` and `googlepay.php`.

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Academe\Opayo\Pi\Demo;
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../demo/shared.php';

class ScaBuilderTest extends TestCase
{
    public function testBuildsScaFromPostedBrowserFields()
    {
        $post = [
            'browserColorDepth' => '24',
            'browserScreenHeight' => '1080',
            'browserScreenWidth' => '1920',
            'browserTz' => '0',
            'browserLanguage' => 'en-GB',
        ];
        $sca = scaFromRequest($post, 'http://127.0.0.1:8000/notification.php', '10.0.0.1');

        $this->assertInstanceOf(StrongCustomerAuthentication::class, $sca);
        $data = $sca->jsonSerialize();
        $this->assertSame('http://127.0.0.1:8000/notification.php', $data['notificationURL']);
        $this->assertSame('10.0.0.1', $data['browserIP']);
        $this->assertSame(1080, $data['browserScreenHeight']);
    }

    public function testForcesIpv4LoopbackForIpv6ClientIp()
    {
        $sca = scaFromRequest([], 'http://x/n.php', '::1');
        $this->assertSame('127.0.0.1', $sca->jsonSerialize()['browserIP']);
    }
}
```

(If a property name in the asserts does not match `StrongCustomerAuthentication::jsonSerialize()` output, read that class and adjust the assert to the real key — do not change the class.)

- [ ] **Step 2: Run it, expect failure** — `undefined function scaFromRequest()`.

Run: `php vendor/phpunit/phpunit/phpunit tests/Demo/ScaBuilderTest.php`

- [ ] **Step 3: Implement, lifting the block from the current pay.php**

```php
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;
use Academe\Opayo\Pi\Request\Enums\ChallengeWindowSize;
use Academe\Opayo\Pi\Request\Enums\TransType;

/**
 * The strongCustomerAuthentication object, from the browser fields the page
 * collected. Always sent: a Google PAN_ONLY token may still be challenged.
 */
function scaFromRequest(array $post, string $notificationUrl, string $clientIp): StrongCustomerAuthentication
{
    return new StrongCustomerAuthentication(
        $notificationUrl,
        str_contains($clientIp, ':') ? '127.0.0.1' : $clientIp, // IPv4 only
        $_SERVER['HTTP_ACCEPT'] ?? '*/*',
        true,
        ($post['browserLanguage'] ?? '') ?: 'en-GB',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        ChallengeWindowSize::Medium,
        TransType::GoodsAndServicePurchase,
        [
            'browserJavaEnabled' => false,
            'browserColorDepth' => (int)($post['browserColorDepth'] ?? 24),
            'browserScreenHeight' => (int)($post['browserScreenHeight'] ?? 0),
            'browserScreenWidth' => (int)($post['browserScreenWidth'] ?? 0),
            'browserTz' => (int)($post['browserTz'] ?? 0),
        ]
    );
}
```

- [ ] **Step 4: Run it, expect pass.**

Run: `php vendor/phpunit/phpunit/phpunit tests/Demo/ScaBuilderTest.php`

- [ ] **Step 5: Checkpoint** — full unit suite green, summarise, hand to user.

---

### Task 3: Payment-method factory

**Files:**
- Modify: `demo/shared.php`
- Create: `tests/Demo/PaymentMethodFactoryTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `paymentMethodFromRequest(array $post, string $sessionKey, string $clientIp): PaymentMethodInterface`. Dispatch on posted fields, one credential only:
  - `card-identifier` present → `new SingleUseCard($sessionKey, $post['card-identifier'])`
  - `googlePayToken` present → `GooglePayPayment::fromGoogleToken($sessionKey, $clientIp, $post['googlePayToken'])`
  - `applePayToken` present → `ApplePayPayment::fromAppleToken($sessionKey, $clientIp, $post['applePayToken'], $post['appleSessionValidationToken'] ?? null)`
  - `method === 'paypal'` → `new PayPalPayment($sessionKey, baseUrl() . '/paypal-return.php')`
  - none → `InvalidArgumentException`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Academe\Opayo\Pi\Demo;
use Academe\Opayo\Pi\Request\Model\{SingleUseCard, GooglePayPayment, ApplePayPayment, PayPalPayment};
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../demo/shared.php';

class PaymentMethodFactoryTest extends TestCase
{
    public function testCardIdentifierMakesSingleUseCard()
    {
        $m = paymentMethodFromRequest(['card-identifier' => 'CI-1'], 'MSK-1', '10.0.0.1');
        $this->assertInstanceOf(SingleUseCard::class, $m);
    }

    public function testGoogleTokenMakesGooglePayPayment()
    {
        $m = paymentMethodFromRequest(['googlePayToken' => 'tok'], 'MSK-1', '10.0.0.1');
        $this->assertInstanceOf(GooglePayPayment::class, $m);
        $this->assertSame('dG9r', $m->jsonSerialize()['googlePay']['payload']); // base64('tok')
    }

    public function testAppleTokenMakesApplePayPayment()
    {
        $token = json_encode(['paymentData' => ['x' => 1], 'paymentMethod' => [], 'transactionIdentifier' => 't']);
        $m = paymentMethodFromRequest(
            ['applePayToken' => $token, 'appleSessionValidationToken' => 'SVT'],
            'MSK-1', '10.0.0.1'
        );
        $this->assertInstanceOf(ApplePayPayment::class, $m);
    }

    public function testPaypalMethodMakesPayPalPayment()
    {
        $m = paymentMethodFromRequest(['method' => 'paypal'], 'MSK-1', '10.0.0.1');
        $this->assertInstanceOf(PayPalPayment::class, $m);
    }

    public function testNothingThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        paymentMethodFromRequest([], 'MSK-1', '10.0.0.1');
    }
}
```

- [ ] **Step 2: Run it, expect failure.**

Run: `php vendor/phpunit/phpunit/phpunit tests/Demo/PaymentMethodFactoryTest.php`

- [ ] **Step 3: Implement**

```php
use Academe\Opayo\Pi\Request\Model\PaymentMethodInterface;
use Academe\Opayo\Pi\Request\Model\SingleUseCard;
use Academe\Opayo\Pi\Request\Model\GooglePayPayment;
use Academe\Opayo\Pi\Request\Model\ApplePayPayment;
use Academe\Opayo\Pi\Request\Model\PayPalPayment;

/**
 * Turn whatever credential the browser posted into a payment method. One
 * credential only: the panels post exactly one of these. This is the single
 * point in the demo where the method matters; everything after is identical.
 */
function paymentMethodFromRequest(array $post, string $sessionKey, string $clientIp): PaymentMethodInterface
{
    if (! empty($post['card-identifier'])) {
        return new SingleUseCard($sessionKey, $post['card-identifier']);
    }
    if (! empty($post['googlePayToken'])) {
        return GooglePayPayment::fromGoogleToken($sessionKey, $clientIp, $post['googlePayToken']);
    }
    if (! empty($post['applePayToken'])) {
        return ApplePayPayment::fromAppleToken(
            $sessionKey, $clientIp, $post['applePayToken'], $post['appleSessionValidationToken'] ?? null
        );
    }
    if (($post['method'] ?? '') === 'paypal') {
        return new PayPalPayment($sessionKey, baseUrl() . '/paypal-return.php');
    }
    throw new \InvalidArgumentException('No payment credential in the request.');
}
```

- [ ] **Step 4: Run it, expect pass.**

Run: `php vendor/phpunit/phpunit/phpunit tests/Demo/PaymentMethodFactoryTest.php`

- [ ] **Step 5: Checkpoint** — full unit suite green, summarise, hand to user.

---

### Task 4: Readiness probes

**Files:**
- Modify: `demo/shared.php`

**Interfaces:**
- Consumes: `demoMethodEnabled()`, `applePayDomain()` (Task 1); existing `opayoAuth()`, `opayoEndpoint()`, `httpClient()`, `sendAndRecord()`, `googlePayMerchantId()`, `createMerchantSessionKey()`.
- Produces one shape, `array{available: bool, reason: ?string, detail: ?string}`, from:
  - `cardReadiness(): array`
  - `googlePayReadiness(): array`
  - `applePayReadiness(): array` — this one calls Opayo (`CreateApplePaySession`) at render and records to the wire panel.
  - `payPalReadiness(): array`
  `available` drives live-control-vs-placeholder; `reason` is the short label; `detail` is the longer honest sentence.

This task is verified live, not by unit test (the probes call Opayo). Keep each function short and single-purpose.

- [ ] **Step 1: Implement the four probes**

```php
/**
 * @return array{available: bool, reason: ?string, detail: ?string}
 */
function cardReadiness(): array
{
    if (! demoMethodEnabled('card')) {
        return ['available' => false, 'reason' => 'Switched off in .env (DEMO_ENABLE_CARD=0)', 'detail' => null];
    }
    return ['available' => true, 'reason' => null, 'detail' => null];
}

function googlePayReadiness(): array
{
    if (! demoMethodEnabled('googlePay')) {
        return ['available' => false, 'reason' => 'Switched off in .env (DEMO_ENABLE_GOOGLE_PAY=0)', 'detail' => null];
    }
    if (googlePayMerchantId() === '') {
        return ['available' => false, 'reason' => 'No gatewayMerchantId configured',
                'detail' => 'Set OPAYO_GOOGLE_PAY_MERCHANT_ID (MyOpayo > Settings > Pay Methods).'];
    }
    // Opayo has no pre-flight for Google Pay enrolment; the browser decides
    // readiness, and enrolment is only proven by paying.
    return ['available' => true, 'reason' => null,
            'detail' => 'Browser readiness is checked by Google below. Opayo enrolment for this vendor '
                      . 'is only tested by paying: a vendor without the wallet fails at 6401 on click.'];
}

function applePayReadiness(): array
{
    if (! demoMethodEnabled('applePay')) {
        return ['available' => false, 'reason' => 'Switched off in .env (DEMO_ENABLE_APPLE_PAY=0)', 'detail' => null];
    }
    // The one wallet whose render-time probe is a real Opayo call.
    try {
        $response = sendAndRecord(
            new \Academe\Opayo\Pi\Request\CreateApplePaySession(opayoEndpoint(), opayoAuth(), applePayDomain()),
            'Apple Pay merchant session (readiness probe)'
        );
    } catch (\Throwable $e) {
        return ['available' => false, 'reason' => 'Apple Pay session probe failed', 'detail' => $e->getMessage()];
    }
    if ($response instanceof \Academe\Opayo\Pi\Response\ApplePaySession && $response->getMerchantSession()) {
        return ['available' => true, 'reason' => null,
                'detail' => 'Opayo returned a merchant session for ' . h(applePayDomain()) . '. '
                          . 'The button still needs Safari on an Apple device.'];
    }
    $code = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null;
    $detail = method_exists($response, 'getStatusDetail') ? $response->getStatusDetail() : null;
    return ['available' => false,
            'reason' => 'Opayo will not open a merchant session' . ($code ? " ($code)" : ''),
            'detail' => ($detail ?: 'Register the domain in MyOpayo > Settings > Pay Methods > Apple Pay.')
                      . ' On 127.0.0.1 this is expected: 6118 Domain not registered.'];
}

function payPalReadiness(): array
{
    if (! demoMethodEnabled('payPal')) {
        return ['available' => false, 'reason' => 'Switched off in .env (DEMO_ENABLE_PAY_PAL=0)', 'detail' => null];
    }
    return ['available' => true, 'reason' => null,
            'detail' => 'Enrolment is only visible after redirect: an unenrolled vendor fails at 1030.'];
}
```

- [ ] **Step 2: Verify the Apple probe live**

Start the server (`php -S 127.0.0.1:8000 -t demo`), then:

Run: `php -r 'require "demo/shared.php"; $_REQUEST["account"]="sandbox"; var_dump(applePayReadiness());'`
Expected: `available => false`, reason mentions the status code, detail mentions `6118` / domain registration. (This confirms the probe reaches Opayo and reads the error, which is the demo's teaching point.)

- [ ] **Step 3: Checkpoint** — summarise the four probe shapes and the live Apple result for the user.

---

### Task 5: Rewrite pay.php as the one endpoint

**Files:**
- Modify (rewrite): `demo/pay.php`
- Modify: `demo/shared.php` (add outcome renderers)

**Interfaces:**
- Consumes: `paymentMethodFromRequest()` (T3), `scaFromRequest()` (T2), existing `createMerchantSessionKey()`, `opayoEndpoint()`, `opayoAuth()`, `sendAndRecord()`, `pageTop()`, `pageBottom()`.
- Produces: outcome renderers in `shared.php`: `renderAcsRedirect(Secure3Dv2Redirect $r, string $vendorTxCode): void`, `renderPayPalRedirect(PayPalRedirect $r): void`, `renderErrors($response, string $heading): void`, `renderResult($response): void`. (These consolidate rendering currently spread across `pay.php`, `googlepay.php`, `paypal.php`. Preserve the existing teaching text for `6203`, `6401`, `1030`.)

- [ ] **Step 1: Move the outcome renderers into shared.php**

Lift `showTransactionResult()` (from current `pay.php`) → `renderResult()`, the 3DS redirect block → `renderAcsRedirect()`, the PayPal redirect block (from `paypal.php`) → `renderPayPalRedirect()`, and a unified `renderErrors()` that keeps the `6203`/`6401`/`1030` explanations. Keep them as global functions with `echo`, matching the file's style.

- [ ] **Step 2: Rewrite pay.php to the single flow**

```php
<?php
declare(strict_types=1);
require __DIR__ . '/shared.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$clientIp = ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

// A wallet/PayPal payment needs a session key created here; the card drop-in
// posts its own. Reuse the posted one if present.
$sessionKey = $_POST['merchantSessionKey'] ?? null;
if (! $sessionKey) {
    $sessionKey = createMerchantSessionKey();
}

// Server-side card capture path: tokenise the posted PAN first (unchanged from
// the old pay.php — keep the CreateCardIdentifier call and its error handling,
// producing a card-identifier added into $_POST for the factory).
if (! empty($_POST['cardNumber']) && empty($_POST['card-identifier'])) {
    // ... existing CreateCardIdentifier block, on failure: pageTop/renderErrors/pageBottom/exit ...
    $_POST['card-identifier'] = $cardIdentifier;
}

// 1. One credential -> one payment method.
try {
    $paymentMethod = paymentMethodFromRequest($_POST, $sessionKey, $clientIp);
} catch (\InvalidArgumentException $e) {
    pageTop('Payment error');
    echo '<div class="bg-white rounded-xl shadow p-6"><p class="text-red-600">' . h($e->getMessage()) . '</p></div>';
    pageBottom();
    exit;
}

// 2. One request for every method. SCA always; apply3DSecure card-only.
$vendorTxCode = 'DEMO-' . uniqid() . '-' . time();
$options = [
    'entryMethod' => \Academe\Opayo\Pi\Request\Enums\EntryMethod::Ecommerce,
    'strongCustomerAuthentication' => scaFromRequest($_POST, baseUrl() . '/notification.php', $clientIp),
];
if (! empty($_POST['card-identifier']) && ! empty($_POST['use3ds'])) {
    $options['apply3DSecure'] = \Academe\Opayo\Pi\Request\CreatePayment::APPLY_3D_SECURE_FORCE;
}

$request = new \Academe\Opayo\Pi\Request\CreatePayment(
    opayoEndpoint(), opayoAuth(), $paymentMethod, $vendorTxCode,
    (new \Academe\Opayo\Pi\Money\Amount(new \Academe\Opayo\Pi\Money\Currency('GBP'), 0))->withMajorUnit($_POST['amount'] ?? '9.99'),
    $_POST['description'] ?? 'Demo purchase',
    new \Academe\Opayo\Pi\Request\Model\Address('88', '88 Avenue Road', 'London', 'EC2A 4DP', 'GB'),
    new \Academe\Opayo\Pi\Request\Model\Person($_POST['firstName'] ?? 'Sam', $_POST['lastName'] ?? 'Jones', $_POST['email'] ?? 'sam.jones@example.com'),
    options: $options
);

$response = sendAndRecord($request, 'Payment');

// 3. Pi decides the next step, not the method.
if ($response instanceof \Academe\Opayo\Pi\Response\Secure3Dv2Redirect) {
    $_SESSION['transactionId'] = $response->getTransactionId();
    $_SESSION['vendorTxCode'] = $vendorTxCode;
    pageTop('3D Secure challenge'); renderAcsRedirect($response, $vendorTxCode); pageBottom(); exit;
}
if ($response instanceof \Academe\Opayo\Pi\Response\PayPalRedirect) {
    $_SESSION['paypalTransactionId'] = $response->getTransactionId();
    $_SESSION['vendorTxCode'] = $vendorTxCode;
    pageTop('PayPal redirect'); renderPayPalRedirect($response); pageBottom(); exit;
}
pageTop('Payment result');
if ($response instanceof \Academe\Opayo\Pi\Response\ErrorCollection) {
    renderErrors($response, 'The payment request was rejected.');
} else {
    renderResult($response);
}
echo '<a href="index.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Back to checkout</a>';
pageBottom();
```

(Fill the server-side `CreateCardIdentifier` block from the current `pay.php` verbatim — it is existing working code being kept.)

- [ ] **Step 3: Verify card + Google live**

Server-side card, no 3DS:
Run: `curl -s -X POST http://127.0.0.1:8000/pay.php --data-urlencode account=sandbox --data-urlencode cardNumber=4929000000006 --data-urlencode cardholderName='Sam Jones' --data-urlencode cardExpiry=$(date -d '+2 years' +%m%y 2>/dev/null || echo 1228) --data-urlencode cardCvv=123 --data-urlencode amount=9.99 --data-urlencode description=Demo --data-urlencode firstName=Sam --data-urlencode lastName=Jones --data-urlencode email=s@example.com | grep -o 'Payment successful\|Payment not authorised\|Fatal error'`
Expected: `Payment successful`.

Google placeholder token:
Run: `curl -s -X POST http://127.0.0.1:8000/pay.php --data-urlencode account=sandbox --data-urlencode googlePayToken=examplePaymentMethodToken --data-urlencode amount=9.99 --data-urlencode description=Demo --data-urlencode firstName=Sam --data-urlencode lastName=Jones --data-urlencode email=s@example.com | grep -o '6203\|Fatal error'`
Expected: `6203`.

- [ ] **Step 4: Checkpoint** — `php -l demo/pay.php` clean, both curls as expected, full unit suite still green. Summarise; hand to user.

---

### Task 6: apple-session.php endpoint

**Files:**
- Create: `demo/apple-session.php`

**Interfaces:**
- Consumes: `opayoEndpoint()`, `opayoAuth()`, `applePayDomain()`, `httpClient()`, `CreateApplePaySession`, `Response\ApplePaySession`.
- Produces: JSON endpoint the Apple JS calls on `onvalidatemerchant`. Returns `{merchantSession: {...}, sessionValidationToken: "..."}` on success, HTTP 400 + `{error}` on failure. The browser passes `merchantSession` to `completeMerchantValidation()` and keeps `sessionValidationToken` to post with the payment.

- [ ] **Step 1: Write apple-session.php**

```php
<?php
declare(strict_types=1);
require __DIR__ . '/shared.php';
header('Content-Type: application/json');

try {
    $response = sendAndRecord(
        new \Academe\Opayo\Pi\Request\CreateApplePaySession(opayoEndpoint(), opayoAuth(), applePayDomain()),
        'Apple Pay merchant session (onvalidatemerchant)'
    );
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

if ($response instanceof \Academe\Opayo\Pi\Response\ApplePaySession && $response->getMerchantSession()) {
    echo json_encode([
        'merchantSession' => $response->getMerchantSession(),
        'sessionValidationToken' => $response->getSessionValidationToken(),
    ]);
    exit;
}

http_response_code(400);
echo json_encode([
    'error' => method_exists($response, 'getStatusDetail') ? $response->getStatusDetail() : 'Merchant validation failed',
    'code' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
]);
```

- [ ] **Step 2: Verify live**

Run: `curl -s -X POST "http://127.0.0.1:8000/apple-session.php?account=sandbox" -w '\nHTTP %{http_code}\n'`
Expected: HTTP 400 with an `error` mentioning domain registration / `6118` (correct on `127.0.0.1`).

- [ ] **Step 3: Checkpoint** — `php -l` clean, curl as expected, summarise.

---

### Task 7: methods/card.php partial

**Files:**
- Create: `demo/methods/card.php`

**Interfaces:**
- Consumes: `cardReadiness()`, `$merchantSessionKey`, `$testExpiry`, `opayoEndpoint()`; posts to `pay.php`.
- Produces: a panel. Drop-in (`sagepay.js`) is the default and labelled recommended. A toggle reveals server-side capture (raw PAN fields). Both submit to `pay.php`. Contains the browser-field hidden inputs + the JS that fills them and the `use3ds`→cardholder-name behaviour (moved from current `index.php`).

- [ ] **Step 1: Build the partial** using the card markup + script from the current `index.php` (lines for the drop-in container, the server-side fieldset, the `use3ds` checkbox, the browser-field inputs and the two `<script>` blocks). Wrap it in a readiness guard:

```php
<?php $r = cardReadiness(); ?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
  <div class="flex items-center justify-between">
    <h2 class="text-lg font-semibold text-slate-800">Card</h2>
    <span class="text-xs text-emerald-600 font-medium">Recommended: hosted fields</span>
  </div>
  <?php if (! $r['available']): ?>
    <?= methodPlaceholder($r) ?>
  <?php else: ?>
    <!-- drop-in container (default) + a toggle revealing the server-side fieldset -->
    <!-- form posts to pay.php; includes merchantSessionKey, browser fields, use3ds -->
  <?php endif; ?>
</section>
```

Add a small shared `methodPlaceholder(array $r): string` helper to `shared.php` that renders the greyed reason/detail box (used by all four partials).

- [ ] **Step 2: Verify** the panel renders and the drop-in tokenises: load `http://127.0.0.1:8000/` and confirm (browse) the card panel shows, the hosted fields iframe mounts, and the server-side toggle reveals the PAN fields.

- [ ] **Step 3: Checkpoint** — summarise; hand to user.

---

### Task 8: methods/googlepay.php partial

**Files:**
- Create: `demo/methods/googlepay.php`

**Interfaces:**
- Consumes: `googlePayReadiness()`, `googlePayMerchantId()`, `googlePayGoogleMerchantId()`, and the library `GooglePay\Configuration` to build the client config (as the current top-level `googlepay.php`/`index.php` block does); posts `googlePayToken` (+ browser fields) to `pay.php`.
- Produces: the Google Pay panel — button when ready, placeholder otherwise, with the honest "enrolment only tested by paying" line from `googlePayReadiness()['detail']`.

- [ ] **Step 1: Build the partial**, moving the working Google Pay JS from the current `index.php` (the `OPAYO_GOOGLE_PAY` config blob, `gpRequests()`, `gpRenderButton()`, `gpPay()`), changing its form to post to `pay.php` with `googlePayToken`. Guard with `googlePayReadiness()`; show `$r['detail']` under the button.

- [ ] **Step 2: Verify** (browse) the button renders on `http://127.0.0.1:8000/` and the readiness detail line is visible; confirm no console errors.

- [ ] **Step 3: Checkpoint** — summarise.

---

### Task 9: methods/applepay.php partial

**Files:**
- Create: `demo/methods/applepay.php`

**Interfaces:**
- Consumes: `applePayReadiness()`; calls `apple-session.php` on `onvalidatemerchant`; posts `applePayToken` + `appleSessionValidationToken` + browser fields to `pay.php`.
- Produces: the Apple Pay panel. On `127.0.0.1`/non-Safari it is the placeholder (from the live probe). The `ApplePaySession` JS is written from Apple's + Opayo's docs and **marked unverified in a code comment**.

- [ ] **Step 1: Build the partial**

Readiness guard first (the probe already ran server-side). Then the Apple JS, clearly commented as doc-derived/unverified:

```php
<?php $r = applePayReadiness(); ?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
  <h2 class="text-lg font-semibold text-slate-800">Apple Pay</h2>
  <?php if (! $r['available']): ?>
    <?= methodPlaceholder($r) ?>
  <?php else: ?>
    <div id="apple-pay-button" ...></div>
    <script>
    // NOTE: browser half written from Apple + Opayo docs; unverified until run on
    // Safari/iOS against a registered domain. Server half (apple-session.php) is
    // verified live. See README "Verify Apple Pay on your iPhone".
    // onvalidatemerchant -> fetch('apple-session.php') -> completeMerchantValidation(merchantSession)
    // onpaymentauthorized -> post payment.token JSON as applePayToken + the sessionValidationToken
    </script>
  <?php endif; ?>
</section>
```

Also render a short "needs Safari on an Apple device" note inside the panel via JS when `window.ApplePaySession` is absent, even if the server probe said available.

- [ ] **Step 2: Verify** the placeholder path live: on `http://127.0.0.1:8000/` (Chrome/headless) the Apple panel shows the `6118`/domain reason from the probe. Confirm `php -l` clean and no PHP notices.

- [ ] **Step 3: Checkpoint** — summarise, and explicitly flag the unverified browser half to the user.

---

### Task 10: methods/paypal.php partial

**Files:**
- Create: `demo/methods/paypal.php`

**Interfaces:**
- Consumes: `payPalReadiness()`; posts `method=paypal` (+ order fields already on the page) to `pay.php`.
- Produces: the PayPal panel — one button, the redirect flow. Placeholder only when switched off in `.env`.

- [ ] **Step 1: Build the partial** — a small form posting `method=paypal` to `pay.php`, with the explanatory text from the current top-level `paypal.php`/`index.php` PayPal block, guarded by `payPalReadiness()`.

- [ ] **Step 2: Verify** (browse/curl) that clicking through posts to `pay.php` and reaches a `Redirect (2023)` from the sandbox (the existing PayPal behaviour, now via the unified endpoint).

Run: `curl -s -X POST http://127.0.0.1:8000/pay.php --data-urlencode account=sandbox --data-urlencode method=paypal --data-urlencode amount=9.99 --data-urlencode description=Demo --data-urlencode firstName=Sam --data-urlencode lastName=Jones --data-urlencode email=s@example.com | grep -o 'Redirect to PayPal\|Continue to PayPal\|Fatal error'`
Expected: a PayPal redirect page (`Continue to PayPal`).

- [ ] **Step 3: Checkpoint** — summarise.

---

### Task 11: Rewrite index.php as the checkout, remove old endpoints

**Files:**
- Modify (rewrite): `demo/index.php`
- Delete: `demo/googlepay.php`, `demo/paypal.php`

**Interfaces:**
- Consumes: all four partials, the account selector + wire panel (unchanged), `pageTop()`/`pageBottom()`.
- Produces: one checkout page. Order fields (amount, description, customer) rendered once at the top; the four `methods/*.php` partials included in order (card, Google Pay, Apple Pay, PayPal); the env-account warning and wire panel kept. No `?js`/`?paypal`/`?googlepay` tabs.

- [ ] **Step 1: Rewrite index.php**

Structure: `require shared.php; requireDottedHost();` → account selector (kept) → env warning (kept) → `pageTop()` → order-details block (shared amount/description/customer, rendered once, referenced by each panel's form via shared hidden inputs or a small JS that copies them into each posting form) → `include 'methods/card.php'` … → `pageBottom()`. Remove `$useJs/$usePayPal/$useGooglePay` and the nav.

Decision to encode: the order fields live in one visible block; each method form includes the current values. Simplest robust approach: render the order fields inside each partial's form as hidden inputs populated from shared PHP variables (`$amount`, `$description`, …) with the visible editable block at the top wired by a tiny script that mirrors edits into every form. If that proves fiddly during implementation, fall back to each partial rendering its own order fields — note which was chosen at the checkpoint.

- [ ] **Step 2: Delete the superseded endpoints**

Remove `demo/googlepay.php` and `demo/paypal.php`. Confirm nothing references them: `grep -rn "googlepay.php\|paypal.php" demo/` should show only `methods/` and `paypal-return.php` (the PayPal *callback*, which stays).

- [ ] **Step 3: Verify the whole page** (browse): all four panels present; card + Google live controls; Apple + (if any) placeholders honest; wire panel shows the Apple readiness probe. Screenshot for the user. Re-run the Task 5/10 curls to confirm the endpoint still serves every method.

- [ ] **Step 4: Checkpoint** — full unit suite green, `php -l` on every touched PHP file, screenshot shown, summarise. Hand to user.

---

### Task 12: Docs — .env.example final, README, testing guide, handover status

**Files:**
- Modify: `README.md` (demo section), `demo/README.md`, `docs/TESTING-GUIDE.md`, `docs/wallets-handover.md`

**Interfaces:** none (docs).

- [ ] **Step 1: Update `demo/README.md`** to describe the single checkout page, the `methods/` partials, the `DEMO_ENABLE_*` flags, and add a "Verify Apple Pay on your iPhone" section: register a public HTTPS domain in MyOpayo > Settings > Pay Methods > Apple Pay, set `OPAYO_APPLE_PAY_DOMAIN`, serve the demo from that domain, open in Safari, confirm the button appears and a sandbox-tester card completes without a `3DAuth`.

- [ ] **Step 2: Update `README.md`** demo bullet(s) and `docs/TESTING-GUIDE.md` wallet section to reflect the one-endpoint / one-page structure and that Apple Pay uses the Opayo-managed path.

- [ ] **Step 3: Mark resolved handover points** in `docs/wallets-handover.md`: the Opayo-managed Apple path is implemented (not merchant-managed); demo file list matches what was built. Leave genuinely open points (exact Apple `paymentMethod` field names to confirm against fetched vendor docs; whether Opayo returns `3DAuth` for Apple) flagged.

- [ ] **Step 4: Checkpoint** — summarise doc changes; hand to user for final review and commit.

---

## Self-review notes

- **Spec coverage:** file structure (Tasks 5–11), readiness four-layer model (Tasks 1,4 + partials), `.env` flags (Task 1), one-endpoint `pay.php` with always-on SCA and card-only 3DS (Task 5), card drop-in+toggle (Task 7), Opayo-managed Apple depth (Tasks 4,6,9), testing split (unit Tasks 1–3, live Tasks 4–11, user-later Task 9) — all mapped.
- **Out of scope preserved:** no `scripts/check-wallets.php`, no package graduation, no merchant-managed Apple path.
- **Type consistency:** readiness shape `{available, reason, detail}` used identically in Task 4 and every partial; `paymentMethodFromRequest`/`scaFromRequest` signatures match between definition (Tasks 2,3) and use (Task 5).
- **Open risk flagged in-plan:** Task 11 Step 1 order-fields wiring may fall back to per-partial fields; the SCA `jsonSerialize()` key names in Task 2 test may need adjusting to the real class output.
