# Integration Guide and Demo Restructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give integrators a clear per-method path (front end, back-end config, endpoints) by adding two small helpers to the package, rebuilding the demo as that path applied, moving diagnostics into a removable `demo/debug/` layer, and writing `docs/INTEGRATION.md`.

**Architecture:** New `Academe\Opayo\Pi\Checkout` namespace (`BrowserData`, `PaymentOutcome`, `OutcomeKind`) plus a browser-data JavaScript snippet. They only build and read existing message classes. The demo becomes a few core files (`bootstrap.php`, `checkout.php`, `pay.php`, `notification.php`, `complete.php`), one front-end partial per method, two add-on endpoints, and a `debug/` folder attached by a single marked line.

**Tech Stack:** PHP 8.1+, PHPUnit 10, Guzzle 7 (dev dependency, demo only), plain PHP + Tailwind CDN for the demo, vanilla JavaScript.

**Spec:** `docs/superpowers/specs/2026-10-03-integration-guide-and-demo-restructure-design.md`

## Global Constraints

- **Never commit.** The user reviews and commits. Each task ends with a checkpoint, not a commit.
- **Never touch files outside the repository** (`C:\Users\jason\Herd\opayo-pi`).
- **Messages are untouched:** no change to any existing class under `src/Request`, `src/Response`, `src/ServerRequest`, `src/Factory`, `src/Model` or `src/Money`. New code only builds them through existing constructors and reads them through existing getters.
- **The existing package test suite passes unchanged.** Only files under `tests/Demo/` may be removed or replaced.
- **`src/` never reads globals or environment:** no `getenv`, `$_ENV`, `$_SERVER`, `$_POST`, `$_GET`, `$_SESSION` in `src/`.
- **No new Composer dependencies.**
- **Debug tooling lives only in `demo/debug/`**, attached by the one marked line in `demo/bootstrap.php`.
- **Run tests with** `php vendor/phpunit/phpunit/phpunit --exclude-group integration` (the `vendor/bin/phpunit` shim fails in Git Bash with `env: 'php': No such file`).
- **PHP style:** `declare(strict_types=1);`, PSR-12, match the surrounding code's comment density.

## Plan-level decisions (refinements of the spec)

- `DEMO_DEBUG` is read from `$_ENV` (the demo loads `.env` into `$_ENV`, not into `getenv()`): `if (($_ENV['DEMO_DEBUG'] ?? '1') !== '0') require ...`.
- `PaymentOutcome` gains `summary(): array`, a plain array (kind, successful, transactionId, status, statusDetail, errors) that endpoints store in the session for `complete.php`. It is on the new class, not on any message.
- Card 3D Secure strength becomes integration config: `OPAYO_APPLY_3D_SECURE` (default `UseMSPSetting`) in `.env`, applied to card payments only. The old per-request "Use 3D Secure" checkbox is gone. Set it to `Force` to make the sandbox challenge.
- Google Pay environment becomes config: `GOOGLE_PAY_ENVIRONMENT` (`TEST` default, or `PRODUCTION`). The in-page environment and gatewayMerchantId inputs are removed.
- The Opayo endpoint honours the existing `OPAYO_ENVIRONMENT` (`test` default, `live`).
- `baseUrl()` respects HTTPS and `X-Forwarded-Proto`, so behind ngrok the notification and PayPal callback URLs are `https://`.
- The 3D Secure challenge page shows a "Continue to 3D Secure" button rather than auto-submitting, so the wire panel can be read before leaving. The guide notes a real site may auto-submit.
- Pure demo helpers live in `demo/config.php` (unit-tested); side effects live in `demo/bootstrap.php`; HTML helpers live in `demo/layout.php`.

## Review Focus

1. **Odd browser values** (`browserTz` = `"-60"`, `"abc"`, colour depth `30`, blank language): `BrowserData::fromArray()` keeps valid negatives and falls back to defaults for junk. Tested in Task 1.
2. **A 3D Secure-failed payment with no `transactionType`** (what Opayo returns after a failed challenge): classified `Finished`, not successful, never `UnexpectedValueException`. Tested in Task 2.
3. **Branching mistakes:** calling `redirectUrl()` on a `Finished` outcome throws `LogicException` with a message naming both kinds. Tested in Task 2.
4. **A POST carrying two credentials** (for example `card-identifier` and `googlePayToken`): `postedMethod()` returns `null` rather than guessing, so `pay.php` refuses it. Tested in Task 4.
5. **Served behind ngrok** (`X-Forwarded-Proto: https`, no port): `baseUrl()` returns `https://host`, so the 3D Secure notification and PayPal callback URLs are reachable. Tested in Task 4.

---

## File structure

| File | Responsibility |
| ---- | -------------- |
| `src/Checkout/BrowserData.php` (new) | Browser fields for 3D Secure; builds a `StrongCustomerAuthentication` |
| `src/Checkout/OutcomeKind.php` (new) | Enum: Finished, Challenge, Redirect, Rejected |
| `src/Checkout/PaymentOutcome.php` (new) | Classifies a payment response into one `OutcomeKind` |
| `resources/js/browser-data.js` (new) | Fills the browser fields in a form |
| `tests/Checkout/BrowserDataTest.php` (new) | |
| `tests/Checkout/PaymentOutcomeTest.php` (new) | |
| `tests/Checkout/BrowserDataSnippetTest.php` (new) | Snippet field names match `BrowserData::FIELDS` |
| `demo/config.php` (new) | Pure demo helpers: env parsing, enabled methods, URLs, posted method, `h()` |
| `demo/layout.php` (new) | `pageTop()`, `pageBottom()`, `orderFields()`, `placeholder()` |
| `demo/bootstrap.php` (new) | Loads `.env`, builds `$endpoint`, `$auth`, `$client`, `$enabledMethods`, `$config`, `$baseUrl`; the debug line; `merchantSessionKey()` |
| `demo/index.php` (rewrite) | Redirects to `checkout.php` |
| `demo/checkout.php` (new) | Order block + one partial per enabled method |
| `demo/methods/{card,googlepay,applepay,paypal}.php` (rewrite) | Front end per method |
| `demo/pay.php` (rewrite) | The pay endpoint |
| `demo/notification.php` (rewrite) | 3D Secure return |
| `demo/complete.php` (new) | The one result page |
| `demo/apple-session.php` (rewrite) | Apple Pay merchant validation |
| `demo/paypal-return.php` (rewrite) | PayPal return |
| `demo/debug/enable.php`, `wire.php`, `accounts.php`, `explain.php`, `check.php`, `test-card.php` (new) | Debug layer |
| `demo/shared.php`, `demo/result.php` (delete) | |
| `tests/Demo/ConfigTest.php` (new), `tests/Demo/BareIntegrationTest.php` (new) | |
| `tests/Demo/DemoFlagsTest.php`, `PaymentMethodFactoryTest.php`, `ScaBuilderTest.php` (delete) | |
| `.gitattributes` (new) | Dist export-ignore |
| `.env.example` (modify) | New keys documented |
| `docs/INTEGRATION.md` (new), `README.md`, `demo/README.md` (modify) | Docs |

---

### Task 1: `BrowserData`

**Files:**
- Create: `src/Checkout/BrowserData.php`
- Test: `tests/Checkout/BrowserDataTest.php`

**Interfaces:**
- Consumes: `Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication::__construct(string $notificationUrl, string $browserIp, string $browserAcceptHeader, bool $browserJavascriptEnabled, string $browserLanguage, string $browserUserAgent, string|ChallengeWindowSize $challengeWindowSize, string|TransType $transType, array $additionalOptions = [])`
- Produces:
  - `final class Academe\Opayo\Pi\Checkout\BrowserData`
  - `public const FIELDS = ['browserLanguage', 'browserColorDepth', 'browserScreenHeight', 'browserScreenWidth', 'browserTz'];`
  - `public static function fromArray(array $fields): self`
  - `public function toStrongCustomerAuthentication(string $notificationUrl, string $clientIp, string $acceptHeader, string $userAgent): StrongCustomerAuthentication`
  - readonly properties `string $language, int $colorDepth, int $screenHeight, int $screenWidth, int $timezoneOffset`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

use Academe\Opayo\Pi\Request\Enums\ChallengeWindowSize;
use Academe\Opayo\Pi\Request\Enums\TransType;
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;
use PHPUnit\Framework\TestCase;

class BrowserDataTest extends TestCase
{
    public function testReadsPostedFields()
    {
        $data = BrowserData::fromArray([
            'browserLanguage' => 'fr-FR',
            'browserColorDepth' => '32',
            'browserScreenHeight' => '1080',
            'browserScreenWidth' => '1920',
            'browserTz' => '-60',
        ]);

        $this->assertSame('fr-FR', $data->language);
        $this->assertSame(32, $data->colorDepth);
        $this->assertSame(1080, $data->screenHeight);
        $this->assertSame(1920, $data->screenWidth);
        $this->assertSame(-60, $data->timezoneOffset);
    }

    public function testDefaultsWhenFieldsMissing()
    {
        $data = BrowserData::fromArray([]);

        $this->assertSame('en-GB', $data->language);
        $this->assertSame(24, $data->colorDepth);
        $this->assertSame(0, $data->screenHeight);
        $this->assertSame(0, $data->screenWidth);
        $this->assertSame(0, $data->timezoneOffset);
    }

    public function testJunkFallsBackToDefaults()
    {
        $data = BrowserData::fromArray([
            'browserLanguage' => '   ',
            'browserColorDepth' => '30',
            'browserScreenHeight' => 'tall',
            'browserTz' => 'abc',
        ]);

        $this->assertSame('en-GB', $data->language);
        $this->assertSame(24, $data->colorDepth);
        $this->assertSame(0, $data->screenHeight);
        $this->assertSame(0, $data->timezoneOffset);
    }

    public function testFieldsConstantListsWhatIsRead()
    {
        $this->assertSame(
            ['browserLanguage', 'browserColorDepth', 'browserScreenHeight', 'browserScreenWidth', 'browserTz'],
            BrowserData::FIELDS
        );
    }

    public function testBuildsScaIdenticalToOneBuiltByHand()
    {
        $sca = BrowserData::fromArray([
            'browserLanguage' => 'en-GB',
            'browserColorDepth' => '24',
            'browserScreenHeight' => '1080',
            'browserScreenWidth' => '1920',
            'browserTz' => '0',
        ])->toStrongCustomerAuthentication(
            'https://shop.example/notification.php',
            '10.0.0.1',
            'text/html',
            'Mozilla/5.0'
        );

        $byHand = new StrongCustomerAuthentication(
            'https://shop.example/notification.php',
            '10.0.0.1',
            'text/html',
            true,
            'en-GB',
            'Mozilla/5.0',
            ChallengeWindowSize::Medium,
            TransType::GoodsAndServicePurchase,
            [
                'browserJavaEnabled' => false,
                'browserColorDepth' => 24,
                'browserScreenHeight' => 1080,
                'browserScreenWidth' => 1920,
                'browserTz' => 0,
            ]
        );

        $this->assertSame(json_encode($byHand), json_encode($sca));
    }

    public function testIpv6ClientIpBecomesIpv4Loopback()
    {
        $sca = BrowserData::fromArray([])->toStrongCustomerAuthentication('https://x.example/n', '::1', 'text/html', 'UA');

        $this->assertSame('127.0.0.1', $sca->jsonSerialize()['browserIP']);
    }

    public function testIpv4ClientIpIsKept()
    {
        $sca = BrowserData::fromArray([])->toStrongCustomerAuthentication('https://x.example/n', '203.0.113.9', 'text/html', 'UA');

        $this->assertSame('203.0.113.9', $sca->jsonSerialize()['browserIP']);
    }

    public function testBlankHeadersGetSafeValues()
    {
        $data = BrowserData::fromArray([])->toStrongCustomerAuthentication('https://x.example/n', '10.0.0.1', '', '')->jsonSerialize();

        $this->assertSame('*/*', $data['browserAcceptHeader']);
        $this->assertSame('Unknown', $data['browserUserAgent']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/phpunit/phpunit/phpunit --exclude-group integration tests/Checkout/BrowserDataTest.php`
Expected: FAIL with `Class "Academe\Opayo\Pi\Checkout\BrowserData" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

use Academe\Opayo\Pi\Request\Enums\ChallengeWindowSize;
use Academe\Opayo\Pi\Request\Enums\TransType;
use Academe\Opayo\Pi\Request\Model\StrongCustomerAuthentication;

/**
 * What the shopper's browser reports for 3D Secure v2.
 *
 * The browser collects these values (see resources/js/browser-data.js) and
 * posts them with the payment form. Build this from the posted fields, then
 * turn it into the strongCustomerAuthentication object CreatePayment needs.
 * Send it for every payment method: a wallet token can be challenged too.
 */
final class BrowserData
{
    /** The form field names this class reads, and the snippet fills. */
    public const FIELDS = [
        'browserLanguage',
        'browserColorDepth',
        'browserScreenHeight',
        'browserScreenWidth',
        'browserTz',
    ];

    /** Colour depths Opayo accepts; anything else is reported as 24. */
    private const COLOR_DEPTHS = [1, 4, 8, 15, 16, 24, 32, 48];

    public function __construct(
        public readonly string $language = 'en-GB',
        public readonly int $colorDepth = 24,
        public readonly int $screenHeight = 0,
        public readonly int $screenWidth = 0,
        public readonly int $timezoneOffset = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $fields Usually the posted form fields.
     */
    public static function fromArray(array $fields): self
    {
        $int = static fn (string $name, int $default): int =>
            isset($fields[$name]) && is_numeric($fields[$name]) ? (int) $fields[$name] : $default;

        $depth = $int('browserColorDepth', 24);
        $language = trim((string) ($fields['browserLanguage'] ?? ''));

        return new self(
            language: $language !== '' ? $language : 'en-GB',
            colorDepth: in_array($depth, self::COLOR_DEPTHS, true) ? $depth : 24,
            screenHeight: $int('browserScreenHeight', 0),
            screenWidth: $int('browserScreenWidth', 0),
            timezoneOffset: $int('browserTz', 0),
        );
    }

    /**
     * @param string $notificationUrl Where the shopper's browser returns after a challenge.
     * @param string $clientIp        The shopper's IP; Opayo accepts IPv4 only, so anything
     *                                else is sent as 127.0.0.1.
     */
    public function toStrongCustomerAuthentication(
        string $notificationUrl,
        string $clientIp,
        string $acceptHeader,
        string $userAgent
    ): StrongCustomerAuthentication {
        return new StrongCustomerAuthentication(
            $notificationUrl,
            filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? $clientIp : '127.0.0.1',
            $acceptHeader !== '' ? $acceptHeader : '*/*',
            true,
            $this->language,
            $userAgent !== '' ? $userAgent : 'Unknown',
            ChallengeWindowSize::Medium,
            TransType::GoodsAndServicePurchase,
            [
                'browserJavaEnabled' => false,
                'browserColorDepth' => $this->colorDepth,
                'browserScreenHeight' => $this->screenHeight,
                'browserScreenWidth' => $this->screenWidth,
                'browserTz' => $this->timezoneOffset,
            ]
        );
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php vendor/phpunit/phpunit/phpunit --exclude-group integration tests/Checkout/BrowserDataTest.php`
Expected: PASS (8 tests). If `testBuildsScaIdenticalToOneBuiltByHand` fails on a key name, read `StrongCustomerAuthentication::jsonSerialize()` and fix the test's expectation of keys only where the class differs; do not change the class.

- [ ] **Step 5: Run the whole suite**

Run: `php vendor/phpunit/phpunit/phpunit --exclude-group integration`
Expected: all pass (existing 267 plus the new ones).

- [ ] **Step 6: Checkpoint.** Report the two new files to the user. Do not commit.

---

### Task 2: `OutcomeKind` and `PaymentOutcome`

**Files:**
- Create: `src/Checkout/OutcomeKind.php`, `src/Checkout/PaymentOutcome.php`
- Test: `tests/Checkout/PaymentOutcomeTest.php`

**Interfaces:**
- Consumes: `ResponseFactory::fromData(mixed $data, ?int $httpCode)`, `Response\Payment`, `Response\Secure3Dv2Redirect::getAcsUrl()/getPaRequestFields(?string)`, `Response\PayPalRedirect::getRedirectUrl()`, `Response\ErrorCollection` (iterates `Response\Model\Error` with `getCode()`, `getDescription()`, `getProperty()`), `AbstractTransaction::getTransactionId()/getStatus()/getStatusDetail()/isSuccessful()`.
- Produces:
  - `enum Academe\Opayo\Pi\Checkout\OutcomeKind: string { Finished='finished'; Challenge='challenge'; Redirect='redirect'; Rejected='rejected' }`
  - `final class PaymentOutcome` with `public readonly OutcomeKind $kind`
  - `static fromResponse(object $response): self`
  - `isFinished(): bool`, `isChallenge(): bool`, `isRedirect(): bool`, `isRejected(): bool`
  - `response(): object`
  - `transactionId(): ?string` (Finished, Challenge, Redirect)
  - `isSuccessful(): bool`, `status(): ?string`, `statusDetail(): ?string` (Finished)
  - `acsUrl(): ?string`, `formFields(?string $threeDSSessionData = null): array` (Challenge)
  - `redirectUrl(): ?string` (Redirect)
  - `errors(): list<array{code: string|int|null, description: ?string, property: ?string}>` (Rejected)
  - `summary(): array{kind: string, successful: bool, transactionId: ?string, status: ?string, statusDetail: ?string, errors: list<array>}` (any kind)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Response\Secure3DRedirect;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

class PaymentOutcomeTest extends TestCase
{
    private function authorised(): object
    {
        return ResponseFactory::fromData([
            'transactionId' => 'T-OK',
            'transactionType' => 'Payment',
            'status' => 'Ok',
            'statusCode' => '0000',
            'statusDetail' => 'The Authorisation was Successful.',
        ], 201);
    }

    private function declined(): object
    {
        return ResponseFactory::fromData([
            'transactionId' => 'T-NO',
            'transactionType' => 'Payment',
            'status' => 'NotAuthed',
            'statusCode' => '2000',
            'statusDetail' => 'The Authorisation was Declined by the bank.',
        ], 201);
    }

    /** What Opayo returns after a failed 3D Secure challenge: no transactionType. */
    private function failedChallenge(): object
    {
        return ResponseFactory::fromData([
            'transactionId' => 'T-3DF',
            'status' => 'Rejected',
            'statusCode' => '2001',
            'statusDetail' => 'The Transaction was rejected because of the 3D-Authentication failed.',
            'paymentMethod' => ['card' => ['cardType' => 'Visa', 'lastFourDigits' => '0006', 'expiryDate' => '1229']],
            'amount' => ['totalAmount' => 999, 'saleAmount' => 999, 'surchargeAmount' => 0],
        ], 200);
    }

    private function challenge(): object
    {
        return ResponseFactory::fromData([
            'statusCode' => '2021',
            'statusDetail' => 'Please redirect your customer to the ACS to continue the 3D Secure process.',
            'transactionId' => 'T-3DS',
            'status' => '3DAuth',
            'acsUrl' => 'https://acs.example/challenge',
            'cReq' => 'eyJjcmVxIjoidGVzdCJ9',
            'dsTranId' => 'ds-1',
        ], 202);
    }

    private function payPal(): object
    {
        return ResponseFactory::fromData([
            'transactionId' => 'T-PP',
            'transactionType' => 'Payment',
            'status' => 'Redirect',
            'statusCode' => '2023',
            'statusDetail' => 'Transaction registered, redirect client to wallet server.',
            'paymentMethod' => ['paypal' => [
                'redirectUrl' => 'https://www.sandbox.paypal.com/checkoutnow?token=ABC',
                'orderId' => 'ABC',
            ]],
        ], 201);
    }

    private function errors(): object
    {
        return ResponseFactory::fromData([
            'errors' => [
                ['code' => 1003, 'description' => 'Missing mandatory field', 'property' => 'amount'],
                ['code' => 1004, 'description' => 'Invalid length', 'property' => 'vendorTxCode'],
            ],
        ], 422);
    }

    public function testAuthorisedIsFinishedAndSuccessful()
    {
        $outcome = PaymentOutcome::fromResponse($this->authorised());

        $this->assertSame(OutcomeKind::Finished, $outcome->kind);
        $this->assertTrue($outcome->isFinished());
        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame('T-OK', $outcome->transactionId());
        $this->assertSame('Ok', $outcome->status());
        $this->assertSame('The Authorisation was Successful.', $outcome->statusDetail());
    }

    public function testDeclinedIsFinishedNotSuccessful()
    {
        $outcome = PaymentOutcome::fromResponse($this->declined());

        $this->assertTrue($outcome->isFinished());
        $this->assertFalse($outcome->isSuccessful());
        $this->assertSame('NotAuthed', $outcome->status());
    }

    public function testFailedChallengeWithoutTransactionTypeIsFinished()
    {
        $outcome = PaymentOutcome::fromResponse($this->failedChallenge());

        $this->assertTrue($outcome->isFinished());
        $this->assertFalse($outcome->isSuccessful());
        $this->assertSame('T-3DF', $outcome->transactionId());
    }

    public function testChallenge()
    {
        $outcome = PaymentOutcome::fromResponse($this->challenge());

        $this->assertTrue($outcome->isChallenge());
        $this->assertSame('T-3DS', $outcome->transactionId());
        $this->assertSame('https://acs.example/challenge', $outcome->acsUrl());
        $this->assertSame(['creq' => 'eyJjcmVxIjoidGVzdCJ9'], $outcome->formFields());
        $this->assertSame(
            ['creq' => 'eyJjcmVxIjoidGVzdCJ9', 'threeDSSessionData' => 'abc'],
            $outcome->formFields('abc')
        );
    }

    public function testPayPalRedirect()
    {
        $outcome = PaymentOutcome::fromResponse($this->payPal());

        $this->assertTrue($outcome->isRedirect());
        $this->assertSame('T-PP', $outcome->transactionId());
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=ABC', $outcome->redirectUrl());
    }

    public function testErrorsAreRejected()
    {
        $outcome = PaymentOutcome::fromResponse($this->errors());

        $this->assertTrue($outcome->isRejected());
        $this->assertSame([
            ['code' => 1003, 'description' => 'Missing mandatory field', 'property' => 'amount'],
            ['code' => 1004, 'description' => 'Invalid length', 'property' => 'vendorTxCode'],
        ], $outcome->errors());
    }

    public function testResponseIsTheSameInstance()
    {
        $response = $this->authorised();

        $this->assertSame($response, PaymentOutcome::fromResponse($response)->response());
    }

    public function testWrongKindGetterThrows()
    {
        $outcome = PaymentOutcome::fromResponse($this->authorised());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('redirectUrl() is only available on a redirect outcome; this is a finished outcome.');

        $outcome->redirectUrl();
    }

    public function testTransactionIdThrowsOnRejected()
    {
        $this->expectException(LogicException::class);

        PaymentOutcome::fromResponse($this->errors())->transactionId();
    }

    public function testRetired3DSecureV1IsUnexpected()
    {
        $v1 = Secure3DRedirect::fromData([
            'statusCode' => '2007',
            'status' => '3DAuth',
            'transactionId' => 'T-V1',
            'acsUrl' => 'https://acs.example/v1',
            'paReq' => 'PAREQ',
        ], 202);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Secure3DRedirect');

        PaymentOutcome::fromResponse($v1);
    }

    public function testUnknownObjectIsUnexpected()
    {
        $this->expectException(UnexpectedValueException::class);

        PaymentOutcome::fromResponse(new stdClass());
    }

    public function testSummaryForEachKind()
    {
        $this->assertSame([
            'kind' => 'finished',
            'successful' => true,
            'transactionId' => 'T-OK',
            'status' => 'Ok',
            'statusDetail' => 'The Authorisation was Successful.',
            'errors' => [],
        ], PaymentOutcome::fromResponse($this->authorised())->summary());

        $this->assertSame([
            'kind' => 'redirect',
            'successful' => false,
            'transactionId' => 'T-PP',
            'status' => 'Redirect',
            'statusDetail' => 'Transaction registered, redirect client to wallet server.',
            'errors' => [],
        ], PaymentOutcome::fromResponse($this->payPal())->summary());

        $rejected = PaymentOutcome::fromResponse($this->errors())->summary();
        $this->assertSame('rejected', $rejected['kind']);
        $this->assertFalse($rejected['successful']);
        $this->assertNull($rejected['transactionId']);
        $this->assertCount(2, $rejected['errors']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/phpunit/phpunit/phpunit --exclude-group integration tests/Checkout/PaymentOutcomeTest.php`
Expected: FAIL with `Class "Academe\Opayo\Pi\Checkout\PaymentOutcome" not found`.

- [ ] **Step 3: Write `OutcomeKind`**

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

/**
 * What a payment response means for the next step, whatever the payment method.
 */
enum OutcomeKind: string
{
    /** Authorised or declined: show the result. */
    case Finished = 'finished';

    /** 3D Secure challenge: send the shopper's browser to the issuer's ACS. */
    case Challenge = 'challenge';

    /** PayPal: send the shopper to PayPal to approve. */
    case Redirect = 'redirect';

    /** Opayo rejected the request: show the errors. */
    case Rejected = 'rejected';
}
```

- [ ] **Step 4: Write `PaymentOutcome`**

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

use Academe\Opayo\Pi\Response\ErrorCollection;
use Academe\Opayo\Pi\Response\Payment;
use Academe\Opayo\Pi\Response\PayPalRedirect;
use Academe\Opayo\Pi\Response\Secure3Dv2Redirect;
use LogicException;
use UnexpectedValueException;

/**
 * The next step after a payment, for every payment method.
 *
 * Pass it the response from CreatePayment (or from CreateSecure3Dv2Challenge,
 * or a FetchTransaction for PayPal) and branch on its kind. It only reads the
 * response; response() hands back the original object.
 */
final class PaymentOutcome
{
    private function __construct(
        public readonly OutcomeKind $kind,
        private readonly object $response
    ) {
    }

    /**
     * @throws UnexpectedValueException for any response that is not a payment
     *   outcome, including the retired 3D Secure v1 Secure3DRedirect.
     */
    public static function fromResponse(object $response): self
    {
        $kind = match (true) {
            $response instanceof ErrorCollection => OutcomeKind::Rejected,
            $response instanceof Secure3Dv2Redirect => OutcomeKind::Challenge,
            $response instanceof PayPalRedirect => OutcomeKind::Redirect,
            $response instanceof Payment => OutcomeKind::Finished,
            default => throw new UnexpectedValueException(sprintf(
                'PaymentOutcome cannot classify a %s; expected a payment, 3D Secure v2, PayPal or error response.',
                $response::class
            )),
        };

        return new self($kind, $response);
    }

    public function isFinished(): bool
    {
        return $this->kind === OutcomeKind::Finished;
    }

    public function isChallenge(): bool
    {
        return $this->kind === OutcomeKind::Challenge;
    }

    public function isRedirect(): bool
    {
        return $this->kind === OutcomeKind::Redirect;
    }

    public function isRejected(): bool
    {
        return $this->kind === OutcomeKind::Rejected;
    }

    /**
     * The original response object, untouched.
     */
    public function response(): object
    {
        return $this->response;
    }

    public function transactionId(): ?string
    {
        if ($this->kind === OutcomeKind::Rejected) {
            throw new LogicException('transactionId() is not available on a rejected outcome; there is no transaction.');
        }

        return $this->response->getTransactionId();
    }

    public function isSuccessful(): bool
    {
        $this->expect(OutcomeKind::Finished, __FUNCTION__);

        return $this->response->isSuccessful();
    }

    public function status(): ?string
    {
        $this->expect(OutcomeKind::Finished, __FUNCTION__);

        return $this->response->getStatus();
    }

    public function statusDetail(): ?string
    {
        $this->expect(OutcomeKind::Finished, __FUNCTION__);

        return $this->response->getStatusDetail();
    }

    public function acsUrl(): ?string
    {
        $this->expect(OutcomeKind::Challenge, __FUNCTION__);

        return $this->response->getAcsUrl();
    }

    /**
     * The fields the shopper's browser must POST to acsUrl().
     *
     * @param string|null $threeDSSessionData Returned to your notification URL
     *   untouched; must not be the transactionId (Opayo rejects that).
     * @return array<string, string>
     */
    public function formFields(?string $threeDSSessionData = null): array
    {
        $this->expect(OutcomeKind::Challenge, __FUNCTION__);

        return $this->response->getPaRequestFields($threeDSSessionData);
    }

    public function redirectUrl(): ?string
    {
        $this->expect(OutcomeKind::Redirect, __FUNCTION__);

        return $this->response->getRedirectUrl();
    }

    /**
     * @return list<array{code: string|int|null, description: ?string, property: ?string}>
     */
    public function errors(): array
    {
        $this->expect(OutcomeKind::Rejected, __FUNCTION__);

        $errors = [];
        foreach ($this->response as $error) {
            $errors[] = [
                'code' => $error->getCode(),
                'description' => $error->getDescription(),
                'property' => $error->getProperty(),
            ];
        }

        return $errors;
    }

    /**
     * A plain array of the outcome, safe for any kind: suitable for a session,
     * a log line or a result page.
     *
     * @return array{kind: string, successful: bool, transactionId: ?string, status: ?string, statusDetail: ?string, errors: list<array>}
     */
    public function summary(): array
    {
        $rejected = $this->kind === OutcomeKind::Rejected;

        return [
            'kind' => $this->kind->value,
            'successful' => $this->kind === OutcomeKind::Finished && $this->response->isSuccessful(),
            'transactionId' => $rejected ? null : $this->response->getTransactionId(),
            'status' => $rejected ? null : $this->response->getStatus(),
            'statusDetail' => $rejected ? null : $this->response->getStatusDetail(),
            'errors' => $rejected ? $this->errors() : [],
        ];
    }

    private function expect(OutcomeKind $kind, string $method): void
    {
        if ($this->kind !== $kind) {
            throw new LogicException(sprintf(
                '%s() is only available on a %s outcome; this is a %s outcome.',
                $method,
                $kind->value,
                $this->kind->value
            ));
        }
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php vendor/phpunit/phpunit/phpunit --exclude-group integration tests/Checkout/PaymentOutcomeTest.php`
Expected: PASS (12 tests). If a fixture is routed by `ResponseFactory` to a different class than the test expects, fix the fixture data to match what Opayo really returns (see `tests/Response/*Test.php`), never `ResponseFactory`.

- [ ] **Step 6: Run the whole suite.** Expected: all pass.

- [ ] **Step 7: Checkpoint.** Report the three new files. Do not commit.

---

### Task 3: Browser-data snippet

**Files:**
- Create: `resources/js/browser-data.js`
- Test: `tests/Checkout/BrowserDataSnippetTest.php`

**Interfaces:**
- Consumes: `BrowserData::FIELDS` (Task 1).
- Produces: global `window.OpayoBrowserData = { collect(): object, fill(form: HTMLFormElement): void }`. `fill()` creates any missing hidden inputs and sets all five values.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Checkout;

use PHPUnit\Framework\TestCase;

/**
 * The JavaScript snippet and BrowserData must agree on field names, or the
 * server silently falls back to defaults for every shopper.
 */
class BrowserDataSnippetTest extends TestCase
{
    public function testSnippetFillsExactlyTheFieldsBrowserDataReads()
    {
        $js = file_get_contents(__DIR__ . '/../../resources/js/browser-data.js');

        $this->assertNotFalse($js);

        preg_match_all('/^\s*(browser[A-Za-z]+):/m', $js, $matches);

        $this->assertSame(BrowserData::FIELDS, $matches[1]);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php vendor/phpunit/phpunit/phpunit --exclude-group integration tests/Checkout/BrowserDataSnippetTest.php`
Expected: FAIL (`file_get_contents(...): Failed to open stream`).

- [ ] **Step 3: Write the snippet**

```js
/*
 * Opayo Pi: browser data for 3D Secure v2.
 *
 * Fills hidden inputs browserLanguage, browserColorDepth, browserScreenHeight,
 * browserScreenWidth and browserTz in a payment form, creating them if they
 * are missing. On the server, read them with
 * Academe\Opayo\Pi\Checkout\BrowserData::fromArray($_POST).
 *
 * Serve this file from your public assets, then:
 *
 *   <script src="/js/browser-data.js"></script>
 *   <script>OpayoBrowserData.fill(document.getElementById('pay-form'));</script>
 *
 * Call fill() any time before the form is submitted. No dependencies.
 */
(function (global) {
    'use strict';

    var COLOR_DEPTHS = [1, 4, 8, 15, 16, 24, 32, 48];

    function collect() {
        return {
            browserLanguage: navigator.language || 'en-GB',
            browserColorDepth: COLOR_DEPTHS.indexOf(screen.colorDepth) !== -1 ? screen.colorDepth : 24,
            browserScreenHeight: screen.height,
            browserScreenWidth: screen.width,
            browserTz: new Date().getTimezoneOffset()
        };
    }

    function fill(form) {
        var data = collect();

        Object.keys(data).forEach(function (name) {
            var input = form.querySelector('input[name="' + name + '"]');

            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                form.appendChild(input);
            }

            input.value = String(data[name]);
        });
    }

    global.OpayoBrowserData = { collect: collect, fill: fill };
})(window);
```

- [ ] **Step 4: Run it to verify it passes.** Expected: PASS.

- [ ] **Step 5: Checkpoint.** Do not commit.

---

### Task 4: Demo pure helpers (`demo/config.php`)

**Files:**
- Create: `demo/config.php`
- Test: `tests/Demo/ConfigTest.php`
- Delete: `tests/Demo/DemoFlagsTest.php`, `tests/Demo/ScaBuilderTest.php`, `tests/Demo/PaymentMethodFactoryTest.php`

The three old tests `require` `demo/shared.php`, which also defines `h()` and `baseUrl()`. PHPUnit runs every test in one process, so they would collide with `config.php` ("Cannot redeclare"). Their coverage is replaced: `ScaBuilderTest` by Task 1, `DemoFlagsTest` by this task, `PaymentMethodFactoryTest` by `postedMethod()` below (the factory itself is removed).

**Interfaces:**
- Produces (global functions, demo only):
  - `const DEMO_METHODS = ['card' => 'DEMO_ENABLE_CARD', 'googlepay' => 'DEMO_ENABLE_GOOGLE_PAY', 'applepay' => 'DEMO_ENABLE_APPLE_PAY', 'paypal' => 'DEMO_ENABLE_PAY_PAL'];`
  - `function parseEnvFile(string $path): array<string,string>`
  - `function enabledMethods(array $env): list<string>` (subset of `card`, `googlepay`, `applepay`, `paypal`, in that order)
  - `function baseUrl(array $server): string` (no trailing slash)
  - `function dottedHostRedirect(array $server): ?string`
  - `function applePayDomain(array $env, array $server): string`
  - `function postedMethod(array $post): ?string`
  - `function h(?string $value): string`

- [ ] **Step 1: Delete the three old demo tests**

```bash
rm tests/Demo/DemoFlagsTest.php tests/Demo/ScaBuilderTest.php tests/Demo/PaymentMethodFactoryTest.php
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Demo;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../demo/config.php';

class ConfigTest extends TestCase
{
    public function testAllMethodsEnabledByDefault()
    {
        $this->assertSame(['card', 'googlepay', 'applepay', 'paypal'], \enabledMethods([]));
    }

    public function testZeroSwitchesAMethodOff()
    {
        $this->assertSame(
            ['card', 'applepay', 'paypal'],
            \enabledMethods(['DEMO_ENABLE_GOOGLE_PAY' => '0'])
        );
    }

    public function testParsesEnvFile()
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "# comment\nOPAYO_VENDOR_NAME = shop \n\nBROKEN LINE\nKEY=a=b\n");

        $this->assertSame(['OPAYO_VENDOR_NAME' => 'shop', 'KEY' => 'a=b'], \parseEnvFile($file));

        unlink($file);
    }

    public function testBaseUrlPlainHttp()
    {
        $this->assertSame('http://127.0.0.1:8000', \baseUrl(['HTTP_HOST' => '127.0.0.1:8000']));
    }

    public function testBaseUrlHttps()
    {
        $this->assertSame('https://shop.example', \baseUrl(['HTTP_HOST' => 'shop.example', 'HTTPS' => 'on']));
    }

    public function testBaseUrlBehindTunnel()
    {
        $this->assertSame(
            'https://abc.ngrok-free.dev',
            \baseUrl(['HTTP_HOST' => 'abc.ngrok-free.dev', 'HTTP_X_FORWARDED_PROTO' => 'https'])
        );
    }

    public function testRedirectsLocalhostToDottedHost()
    {
        $this->assertSame(
            'http://127.0.0.1:8000/checkout.php?x=1',
            \dottedHostRedirect(['HTTP_HOST' => 'localhost:8000', 'REQUEST_URI' => '/checkout.php?x=1'])
        );
        $this->assertNull(\dottedHostRedirect(['HTTP_HOST' => '127.0.0.1:8000', 'REQUEST_URI' => '/']));
    }

    public function testApplePayDomainFromEnvOrHost()
    {
        $this->assertSame('shop.example', \applePayDomain(['OPAYO_APPLE_PAY_DOMAIN' => 'shop.example'], []));
        $this->assertSame('abc.ngrok-free.dev', \applePayDomain([], ['HTTP_HOST' => 'abc.ngrok-free.dev']));
    }

    public function testPostedMethod()
    {
        $this->assertSame('card', \postedMethod(['card-identifier' => 'CI']));
        $this->assertSame('googlepay', \postedMethod(['googlePayToken' => '{}']));
        $this->assertSame('applepay', \postedMethod(['applePayToken' => '{}']));
        $this->assertSame('paypal', \postedMethod(['method' => 'paypal']));
        $this->assertNull(\postedMethod([]));
        $this->assertNull(\postedMethod(['card-identifier' => '']));
    }

    public function testTwoCredentialsAreRefused()
    {
        $this->assertNull(\postedMethod(['card-identifier' => 'CI', 'googlePayToken' => '{}']));
    }

    public function testEscapes()
    {
        $this->assertSame('&lt;a href=&quot;x&quot;&gt;', \h('<a href="x">'));
        $this->assertSame('', \h(null));
    }
}
```

- [ ] **Step 3: Run it to verify it fails**

Run: `php vendor/phpunit/phpunit/phpunit --exclude-group integration tests/Demo/ConfigTest.php`
Expected: FAIL (`require_once(...demo/config.php): Failed to open stream`).

- [ ] **Step 4: Write `demo/config.php`**

```php
<?php

/**
 * Pure helpers for the demo: no side effects, so they can be unit tested.
 * In your own application these are your framework's config and request
 * helpers. Nothing here is part of the package.
 */

declare(strict_types=1);

/**
 * The payment methods the demo can offer, and the .env switch for each.
 * Set a switch to 0 to stop offering that method.
 */
const DEMO_METHODS = [
    'card' => 'DEMO_ENABLE_CARD',
    'googlepay' => 'DEMO_ENABLE_GOOGLE_PAY',
    'applepay' => 'DEMO_ENABLE_APPLE_PAY',
    'paypal' => 'DEMO_ENABLE_PAY_PAL',
];

/**
 * Parse a simple KEY=value .env file. Blank lines, # comments and lines with
 * no "=" are skipped. Values keep any further "=".
 *
 * @return array<string, string>
 */
function parseEnvFile(string $path): array
{
    $values = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $values[trim($name)] = trim($value);
    }

    return $values;
}

/**
 * The methods this site offers. Each defaults to on.
 *
 * @param array<string, string> $env
 * @return list<string>
 */
function enabledMethods(array $env): array
{
    $enabled = [];

    foreach (DEMO_METHODS as $method => $switch) {
        if (($env[$switch] ?? '1') !== '0') {
            $enabled[] = $method;
        }
    }

    return $enabled;
}

/**
 * The URL this demo is served from, e.g. http://127.0.0.1:8000. Behind a
 * tunnel such as ngrok the scheme comes from X-Forwarded-Proto, so callback
 * URLs built from it are https.
 *
 * @param array<string, mixed> $server
 */
function baseUrl(array $server): string
{
    $https = ($server['HTTPS'] ?? '') !== '' && ($server['HTTPS'] ?? '') !== 'off';
    $forwarded = strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $scheme = ($https || $forwarded === 'https') ? 'https' : 'http';

    return $scheme . '://' . ($server['HTTP_HOST'] ?? '127.0.0.1:8000');
}

/**
 * Opayo rejects "localhost" in the 3D Secure notification URL, and the session
 * cookie must survive the round trip, so localhost is sent to 127.0.0.1.
 *
 * @param array<string, mixed> $server
 */
function dottedHostRedirect(array $server): ?string
{
    $host = (string) ($server['HTTP_HOST'] ?? '');

    if (! str_starts_with($host, 'localhost')) {
        return null;
    }

    return 'http://' . str_replace('localhost', '127.0.0.1', $host) . ($server['REQUEST_URI'] ?? '/');
}

/**
 * The domain registered for Apple Pay in MyOpayo; defaults to the host the
 * demo is served from.
 *
 * @param array<string, string> $env
 * @param array<string, mixed> $server
 */
function applePayDomain(array $env, array $server): string
{
    if (($env['OPAYO_APPLE_PAY_DOMAIN'] ?? '') !== '') {
        return $env['OPAYO_APPLE_PAY_DOMAIN'];
    }

    return (string) ($server['HTTP_HOST'] ?? '127.0.0.1');
}

/**
 * Which method a payment POST is paying with, from the one credential it
 * carries. Null when there is none, or more than one.
 *
 * @param array<string, mixed> $post
 */
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

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES);
}
```

- [ ] **Step 5: Run it to verify it passes.** Expected: PASS (11 tests). Then run the whole suite: all pass.

- [ ] **Step 6: Checkpoint.** Note to the user that the old demo pages still use `shared.php` until Task 8; they are not broken by this task. Do not commit.

---

### Task 5: `bootstrap.php`, `layout.php`, `.env.example`

**Files:**
- Create: `demo/bootstrap.php`, `demo/layout.php`
- Modify: `.env.example`

**Interfaces:**
- Consumes: Task 4 functions.
- Produces, in the scope of any file that does `require __DIR__ . '/bootstrap.php';`:
  - `$endpoint` (`Academe\Opayo\Pi\Model\Endpoint`), `$auth` (`Auth`), `$client` (`GuzzleHttp\Client`, a PSR-18 client), `$handler` (`GuzzleHttp\HandlerStack`)
  - `$enabledMethods` (`list<string>`), `$baseUrl` (`string`)
  - `$config` array: `merchantName` (string), `googlePayMerchantId` (string), `googleMerchantId` (?string), `googlePayEnvironment` (`Academe\Opayo\Pi\GooglePay\Environment`), `applePayDomain` (string), `apply3DSecure` (string)
  - `function merchantSessionKey(Psr\Http\Client\ClientInterface $client, Endpoint $endpoint, Auth $auth): string` (throws `RuntimeException`)
  - from `layout.php`: `pageTop(string $title): void`, `pageBottom(): void`, `orderFields(array $order): string`, `placeholder(string $title, string $detail): string`, `const DEBUG_PANEL_MARKER = '<!-- debug-panel -->'`

- [ ] **Step 1: Write `demo/layout.php`**

```php
<?php

/**
 * Page chrome for the demo. Presentation only: your application has its own.
 */

declare(strict_types=1);

/** Where the optional debug layer may insert its panel. Inert without it. */
const DEBUG_PANEL_MARKER = '<!-- debug-panel -->';

function pageTop(string $title): void
{
    $title = h($title);

    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$title} - Opayo Pi demo</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-100 min-h-screen">
    <div class="max-w-6xl mx-auto p-6">
        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Opayo Pi demo <span class="text-slate-400 font-normal">/ {$title}</span></h1>
        </header>
        <div class="flex flex-col lg:flex-row gap-6">
            <main class="flex-1 min-w-0 space-y-6">
    HTML;
}

function pageBottom(): void
{
    echo '</main>' . DEBUG_PANEL_MARKER . '</div></div></body></html>';
}

/**
 * The order as hidden inputs, so every payment form posts the same order.
 * data-order lets checkout.php mirror edits from the visible order block.
 *
 * @param array<string, string> $order
 */
function orderFields(array $order): string
{
    $html = '';
    foreach ($order as $name => $value) {
        $html .= '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '" data-order="' . h($name) . '">';
    }

    return $html;
}

/**
 * The box a method shows instead of its button when it cannot be used.
 */
function placeholder(string $title, string $detail): string
{
    return '<div class="border border-dashed border-slate-300 bg-slate-50 rounded-lg p-4 text-sm text-slate-600">'
        . '<span class="font-medium">' . h($title) . '</span>'
        . ($detail !== '' ? '<p class="mt-1 text-slate-500">' . h($detail) . '</p>' : '')
        . '</div>';
}
```

- [ ] **Step 2: Write `demo/bootstrap.php`**

```php
<?php

/**
 * Everything an integration configures, in one place. In your application
 * this is your framework's config and service container.
 *
 * Every demo page requires this file and then has:
 *
 *   $endpoint        the Opayo endpoint (test or live)
 *   $auth            your vendor name, integration key and password
 *   $client          any PSR-18 HTTP client (Guzzle here)
 *   $enabledMethods  the payment methods this site offers
 *   $baseUrl         where this site is served from (for callback URLs)
 *   $config          the settings individual methods need
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';

use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\GooglePay\Environment as GooglePayEnvironment;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Request\CreateSessionKey;
use Academe\Opayo\Pi\Response\SessionKey;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Client\ClientInterface;

session_start();

$envFile = __DIR__ . '/../.env';
if (! is_file($envFile)) {
    http_response_code(500);
    exit('No .env file found. Copy .env.example to .env and add your Opayo credentials.');
}
$_ENV = $_ENV + parseEnvFile($envFile);

if (($target = dottedHostRedirect($_SERVER)) !== null) {
    header('Location: ' . $target);
    exit;
}

$handler = HandlerStack::create();

// Demo only: wire panel, setup checks, test tools.
// Delete this line and demo/debug/ to get the bare integration.
if (($_ENV['DEMO_DEBUG'] ?? '1') !== '0') require __DIR__ . '/debug/enable.php';

$endpoint = new Endpoint(($_ENV['OPAYO_ENVIRONMENT'] ?? 'test') === 'live' ? Endpoint::MODE_LIVE : Endpoint::MODE_TEST);
$auth = new Auth($_ENV['OPAYO_VENDOR_NAME'], $_ENV['OPAYO_INTEGRATION_KEY'], $_ENV['OPAYO_INTEGRATION_PASSWORD']);
$client = new Client(['handler' => $handler, 'http_errors' => false]);

$enabledMethods = enabledMethods($_ENV);
$baseUrl = baseUrl($_SERVER);

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

/**
 * An Opayo merchant session key: every payment needs one (it lasts about 400
 * seconds and allows three uses).
 */
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

- [ ] **Step 3: Create a stub `demo/debug/enable.php`** so the require line works before Task 9 fills it in:

```php
<?php

/**
 * Demo debug layer (filled in by a later task).
 */

declare(strict_types=1);
```

- [ ] **Step 4: Document the new keys in `.env.example`.** Append, after the existing `OPAYO_APPLE_PAY_DOMAIN=` line:

```text

# Card 3D Secure: UseMSPSetting (default, your MyOpayo rules), Force, Disable
# or ForceIgnoringRules. Use Force on the public sandbox profile to see a
# challenge with the cardholder name CHALLENGE.
OPAYO_APPLY_3D_SECURE=

# Google Pay sheet environment: TEST (default; always a placeholder token,
# Opayo answers 6203) or PRODUCTION (needs GOOGLE_PAY_MERCHANT_ID).
GOOGLE_PAY_ENVIRONMENT=

# Demo only: 0 turns off the wire panel, setup checks and test tools, leaving
# the bare integration.
DEMO_DEBUG=1
```

- [ ] **Step 5: Lint**

Run: `php -l demo/bootstrap.php && php -l demo/layout.php && php -l demo/debug/enable.php`
Expected: `No syntax errors detected` for each.

- [ ] **Step 6: Checkpoint.** Do not commit.

---

### Task 6: Checkout page and method partials

**Files:**
- Create: `demo/checkout.php`
- Rewrite: `demo/index.php`, `demo/methods/card.php`, `demo/methods/googlepay.php`, `demo/methods/applepay.php`, `demo/methods/paypal.php`

**Interfaces:**
- Consumes: Task 5 variables and functions; `resources/js/browser-data.js` (Task 3).
- Produces: every payment form has `data-pay`, posts to `pay.php`, carries `orderFields($order)` and the five browser fields. Field names posted: `card-identifier` + `merchantSessionKey` (card), `googlePayToken`, `applePayToken` + `appleSessionValidationToken` + `resultFormat=json`, `method=paypal`. Order fields: `amount`, `description`, `firstName`, `lastName`, `email`.

- [ ] **Step 1: Rewrite `demo/index.php`**

```php
<?php

declare(strict_types=1);

header('Location: checkout.php');
```

- [ ] **Step 2: Write `demo/checkout.php`**

```php
<?php

/**
 * The checkout page: the order, then one front-end partial per payment method
 * this site offers. Choosing which methods to offer is the include list below,
 * driven by config ($enabledMethods), not code edits.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$order = [
    'amount' => '9.99',
    'description' => 'Demo purchase',
    'firstName' => 'Sam',
    'lastName' => 'Jones',
    'email' => 'sam.jones@example.com',
];

$labels = ['card' => 'Card', 'googlepay' => 'Google Pay', 'applepay' => 'Apple Pay', 'paypal' => 'PayPal'];

pageTop('Checkout');
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Your order</h2>
    <div class="grid grid-cols-2 gap-4">
        <?php foreach ($order as $name => $value): ?>
            <label class="block text-sm">
                <span class="text-slate-600"><?= h(ucfirst($name)) ?></span>
                <input type="text" value="<?= h($value) ?>" data-order-src="<?= h($name) ?>" class="mt-1 w-full rounded border-slate-300">
            </label>
        <?php endforeach; ?>
    </div>
</section>

<?php foreach (DEMO_METHODS as $method => $switch): ?>
    <?php if (in_array($method, $enabledMethods, true)): ?>
        <?php include __DIR__ . '/methods/' . $method . '.php'; ?>
    <?php else: ?>
        <section class="bg-white rounded-xl shadow p-6">
            <?= placeholder($labels[$method] . ' is not offered', 'Switched off in .env (' . $switch . '=0).') ?>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

<!-- In your app, serve resources/js/browser-data.js from your public assets. -->
<script><?= file_get_contents(__DIR__ . '/../resources/js/browser-data.js') ?></script>
<script>
    // Every payment form carries the 3D Secure browser fields.
    document.querySelectorAll('form[data-pay]').forEach(OpayoBrowserData.fill);

    // Keep each form's hidden order fields in step with the order block.
    document.querySelectorAll('[data-order-src]').forEach(function (src) {
        src.addEventListener('input', function () {
            document.querySelectorAll('[data-order="' + src.dataset.orderSrc + '"]').forEach(function (hidden) {
                hidden.value = src.value;
            });
        });
    });
</script>
<?php
pageBottom();
```

- [ ] **Step 3: Write `demo/methods/card.php`**

```php
<?php

/**
 * Card, with Opayo's hosted card fields (sagepay.js drop-in).
 *
 * The shopper types card details into Opayo's iframe; sagepay.js tokenises
 * them in the browser and adds a card-identifier to the form. Your server
 * never sees the card number. The form posts to pay.php.
 *
 * Back end: nothing beyond the core. Extra endpoint: none (3D Secure returns to
 * notification.php, which every method shares).
 *
 * From checkout.php: $endpoint, $client, $auth, $order.
 */

use Academe\Opayo\Pi\Model\Endpoint;

/** @var Endpoint $endpoint */

try {
    // The drop-in tokenises in the browser, so it needs a session key now.
    $cardSessionKey = merchantSessionKey($client, $endpoint, $auth);
} catch (RuntimeException $e) {
    $cardSessionKey = null;
}
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Card</h2>

    <?php if ($cardSessionKey === null): ?>
        <?= placeholder('Card payments are unavailable', 'Opayo would not issue a merchant session key; check your credentials.') ?>
    <?php else: ?>
        <form method="post" action="pay.php" data-pay class="space-y-4">
            <?= orderFields($order) ?>
            <input type="hidden" name="merchantSessionKey" value="<?= h($cardSessionKey) ?>">
            <div id="sp-container" class="border border-slate-200 rounded-lg"></div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg">Pay by card</button>
        </form>

        <script src="<?= h($endpoint->getJavascriptUrl()) ?>"></script>
        <script>
            // Renders the card fields into #sp-container and, on submit, adds
            // a hidden card-identifier to the surrounding form.
            sagepayCheckout({ merchantSessionKey: <?= json_encode($cardSessionKey) ?> }).form();
        </script>
    <?php endif; ?>
</section>
```

- [ ] **Step 4: Write `demo/methods/googlepay.php`**

```php
<?php

/**
 * Google Pay.
 *
 * The package builds the Google Pay request objects (GooglePay\Configuration);
 * this page hands them to Google's pay.js. Google's sheet returns a token,
 * which is posted to pay.php as googlePayToken. Google encrypts the card to
 * Opayo's key, so the token is opaque to you.
 *
 * Back end: $config['googlePayMerchantId'] (gatewayMerchantId, from MyOpayo),
 * $config['googlePayEnvironment'], and in PRODUCTION $config['googleMerchantId'].
 * Extra endpoint: none.
 *
 * From checkout.php: $config, $order.
 */

use Academe\Opayo\Pi\GooglePay\Configuration as GooglePayConfiguration;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;

try {
    $googlePay = (new GooglePayConfiguration(
        gatewayMerchantId: $config['googlePayMerchantId'],
        merchantName: $config['merchantName'],
        googleMerchantId: $config['googleMerchantId'],
        environment: $config['googlePayEnvironment'],
    ))->clientConfiguration((new Amount(new Currency('GBP'), 0))->withMajorUnit($order['amount']));
    $googlePayError = null;
} catch (InvalidArgumentException $e) {
    $googlePay = null;
    $googlePayError = $e->getMessage();
}
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Google Pay</h2>

    <?php if ($googlePay === null): ?>
        <?= placeholder('Google Pay is misconfigured', (string) $googlePayError) ?>
    <?php else: ?>
        <form id="googlepay-form" method="post" action="pay.php" data-pay>
            <?= orderFields($order) ?>
            <input type="hidden" name="googlePayToken">
            <div id="googlepay-button" class="min-h-[44px]"></div>
            <p id="googlepay-status" class="text-xs text-slate-500"></p>
        </form>

        <script src="https://pay.google.com/gp/p/js/pay.js"></script>
        <script>
        (function () {
            const config = <?= json_encode($googlePay) ?>;
            const form = document.getElementById('googlepay-form');
            const status = (message) => { document.getElementById('googlepay-status').textContent = message; };
            const client = new google.payments.api.PaymentsClient({ environment: config.environment });

            // Ask Google whether this browser can pay at all; only then show the button.
            client.isReadyToPay(config.isReadyToPayRequest).then((response) => {
                if (! response.result) {
                    status('Google Pay is not available in this browser.');
                    return;
                }
                document.getElementById('googlepay-button').appendChild(client.createButton({
                    buttonType: 'pay',
                    buttonSizeMode: 'fill',
                    onClick: pay,
                }));
            }).catch((error) => status('Google Pay check failed: ' + error));

            function pay() {
                const request = structuredClone(config.paymentDataRequest);
                request.transactionInfo.totalPrice = form.querySelector('[data-order="amount"]').value.trim();

                client.loadPaymentData(request).then((paymentData) => {
                    form.elements.googlePayToken.value = paymentData.paymentMethodData.tokenizationData.token;
                    form.submit();
                }).catch((error) => {
                    if (error.statusCode !== 'CANCELED') {
                        status('Google Pay: ' + (error.statusMessage || error.statusCode || error));
                    }
                });
            }
        })();
        </script>
    <?php endif; ?>
</section>
```

- [ ] **Step 5: Write `demo/methods/applepay.php`**

```php
<?php

/**
 * Apple Pay (web, Opayo-managed certificate).
 *
 * Safari shows the Apple Pay sheet. Before it opens, Apple asks your server to
 * validate the merchant: apple-session.php asks Opayo to open a merchant
 * session for your registered domain. After the shopper authorises, the token
 * is posted to pay.php by fetch, because the sheet must be completed with the
 * real outcome before the page moves on.
 *
 * Back end: $config['applePayDomain'], registered in MyOpayo.
 * Extra endpoint: apple-session.php.
 *
 * Opayo's sandbox does not support this certificate mode (it answers 4006),
 * so this flow can only be completed on a live account.
 *
 * From checkout.php: $config, $order.
 */
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Apple Pay</h2>

    <form id="applepay-form" method="post" action="pay.php" data-pay>
        <?= orderFields($order) ?>
        <input type="hidden" name="applePayToken">
        <input type="hidden" name="appleSessionValidationToken">
        <input type="hidden" name="resultFormat" value="json">
        <div id="applepay-button" class="hidden" style="-apple-pay-button-style: black; -webkit-appearance: -apple-pay-button; height: 44px; width: 100%;"></div>
        <p id="applepay-status" class="text-xs text-slate-500"></p>
    </form>

    <script>
    (function () {
        const VERSION = 6;
        const form = document.getElementById('applepay-form');
        const button = document.getElementById('applepay-button');
        const status = (message) => { document.getElementById('applepay-status').textContent = message; };

        // Only Safari on an Apple device with a card in Wallet can pay.
        if (! window.ApplePaySession || ! ApplePaySession.supportsVersion(VERSION) || ! ApplePaySession.canMakePayments()) {
            status('Apple Pay needs Safari on an Apple device with a card in Wallet.');
            return;
        }
        button.classList.remove('hidden');

        button.addEventListener('click', function () {
            const session = new ApplePaySession(VERSION, {
                countryCode: 'GB',
                currencyCode: 'GBP',
                merchantCapabilities: ['supports3DS'],
                supportedNetworks: ['visa', 'masterCard', 'amex'],
                total: { label: <?= json_encode($config['merchantName']) ?>, amount: form.querySelector('[data-order="amount"]').value.trim() },
            });

            session.onvalidatemerchant = function () {
                fetch('apple-session.php', { method: 'POST' })
                    .then((r) => r.json())
                    .then((data) => {
                        if (data.error) {
                            status('Merchant validation failed: ' + data.error + (data.code ? ' (' + data.code + ')' : ''));
                            session.abort();
                            return;
                        }
                        form.elements.appleSessionValidationToken.value = data.sessionValidationToken || '';
                        session.completeMerchantValidation(data.merchantSession);
                    })
                    .catch((error) => { status('Merchant validation error: ' + error); session.abort(); });
            };

            session.onpaymentauthorized = function (event) {
                form.elements.applePayToken.value = JSON.stringify(event.payment.token);

                fetch('pay.php', { method: 'POST', body: new FormData(form) })
                    .then((r) => r.json())
                    .then((result) => {
                        session.completePayment(result.approved ? ApplePaySession.STATUS_SUCCESS : ApplePaySession.STATUS_FAILURE);
                        window.location = result.completeUrl;
                    })
                    .catch((error) => {
                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                        status('Payment error: ' + error);
                    });
            };

            session.begin();
        });
    })();
    </script>
</section>
```

- [ ] **Step 6: Write `demo/methods/paypal.php`**

```php
<?php

/**
 * PayPal.
 *
 * No token is made in the browser: the form says "PayPal" and pay.php asks
 * Opayo to register the payment, then sends the shopper to PayPal. PayPal
 * returns them, via Opayo, to paypal-return.php.
 *
 * Back end: nothing beyond the core (PayPal is enabled on your Opayo account).
 * Extra endpoint: paypal-return.php.
 *
 * From checkout.php: $order.
 */
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">PayPal</h2>

    <form method="post" action="pay.php" data-pay>
        <?= orderFields($order) ?>
        <input type="hidden" name="method" value="paypal">
        <button type="submit" class="w-full bg-amber-400 hover:bg-amber-500 text-slate-900 font-semibold py-2 rounded-lg">Pay with PayPal</button>
    </form>
</section>
```

- [ ] **Step 7: Lint all six files.** Run `php -l` on each. Expected: no syntax errors.

- [ ] **Step 8: Checkpoint.** `checkout.php` posts to the old `pay.php` until Task 7; end-to-end checks happen in Task 13. Do not commit.

---

### Task 7: The pay endpoint and the result page

**Files:**
- Rewrite: `demo/pay.php`
- Create: `demo/complete.php`

**Interfaces:**
- Consumes: Tasks 1, 2, 4, 5; form fields from Task 6.
- Produces:
  - Session keys: `$_SESSION['outcome']` (a `PaymentOutcome::summary()` array), `$_SESSION['transactionId']` (string, for the 3D Secure or PayPal return), `$_SESSION['paymentMethod']` (string, the method being paid with).
  - JSON for `resultFormat=json`: `{"approved": bool, "completeUrl": "complete.php"}`; refusal: HTTP 400 `{"approved": false, "error": string}`.

- [ ] **Step 1: Rewrite `demo/pay.php`**

```php
<?php

/**
 * The pay endpoint. Every payment method posts here, and every method follows
 * the same steps; only step 2 differs between them.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Academe\Opayo\Pi\Checkout\BrowserData;
use Academe\Opayo\Pi\Checkout\OutcomeKind;
use Academe\Opayo\Pi\Checkout\PaymentOutcome;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Request\CreatePayment;
use Academe\Opayo\Pi\Request\Enums\EntryMethod;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\ApplePayPayment;
use Academe\Opayo\Pi\Request\Model\GooglePayPayment;
use Academe\Opayo\Pi\Request\Model\PayPalPayment;
use Academe\Opayo\Pi\Request\Model\Person;
use Academe\Opayo\Pi\Request\Model\SingleUseCard;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

$json = ($_POST['resultFormat'] ?? '') === 'json';

// 1. Which method is this, and does this site offer it? Check here, not just
//    in the page: the server decides what it accepts.
$method = postedMethod($_POST);
if ($method === null || ! in_array($method, $enabledMethods, true)) {
    http_response_code(400);
    if ($json) {
        header('Content-Type: application/json');
        exit(json_encode(['approved' => false, 'error' => 'This payment method is not offered.']));
    }
    exit('This payment method is not offered.');
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// The card drop-in made its session key at checkout (it tokenised the card
// with it); every other method needs a fresh one.
$sessionKey = ($_POST['merchantSessionKey'] ?? '') ?: merchantSessionKey($client, $endpoint, $auth);

// 2. The payment method: the only line that differs between methods.
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

// 3. 3D Secure data from the shopper's browser, sent for every method: a
//    wallet token can be challenged too.
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

// Card only: wallet tokens are already authenticated on the device.
if ($method === 'card') {
    $options['apply3DSecure'] = $config['apply3DSecure'];
}

// Your own order reference; it must be unique per attempt.
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

// 4. Send it, and find out what happens next.
$outcome = PaymentOutcome::fromResponse(
    ResponseFactory::fromHttpResponse($client->sendRequest($request))
);

$_SESSION['paymentMethod'] = $method;

// Apple Pay posted by fetch: answer with JSON so the sheet can close with the
// real result, then the page goes to complete.php.
if ($json) {
    $_SESSION['outcome'] = $outcome->summary();
    header('Content-Type: application/json');
    exit(json_encode(['approved' => $outcome->summary()['successful'], 'completeUrl' => 'complete.php']));
}

// 5. Act on the outcome. Identical for every method.
if ($outcome->kind === OutcomeKind::Challenge) {
    // Send the browser to the card issuer, who returns it to notification.php.
    $_SESSION['transactionId'] = $outcome->transactionId();
    $fields = $outcome->formFields(base64_encode($vendorTxCode));

    pageTop('3D Secure');
    echo '<section class="bg-white rounded-xl shadow p-6 space-y-4">'
        . '<h2 class="text-lg font-semibold text-slate-800">Your card issuer wants to check it is you</h2>'
        . '<form method="post" action="' . h($outcome->acsUrl()) . '">';
    foreach ($fields as $name => $value) {
        echo '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '">';
    }
    // A real site would usually submit this form automatically.
    echo '<button class="w-full bg-blue-600 text-white font-semibold py-2 rounded-lg">Continue to 3D Secure</button>'
        . '</form></section>';
    pageBottom();
    exit;
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

- [ ] **Step 2: Write `demo/complete.php`**

```php
<?php

/**
 * The one result page. Whichever method was used, and whichever route the
 * result came back by (pay.php, notification.php, paypal-return.php), it ends
 * here with a PaymentOutcome summary in the session.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$outcome = $_SESSION['outcome'] ?? null;
unset($_SESSION['outcome'], $_SESSION['transactionId'], $_SESSION['paymentMethod']);

if ($outcome === null) {
    header('Location: checkout.php');
    exit;
}

[$heading, $colour] = match (true) {
    $outcome['successful'] => ['Payment successful', 'text-emerald-600'],
    $outcome['kind'] === 'redirect' => ['Waiting for PayPal approval', 'text-amber-600'],
    $outcome['kind'] === 'rejected' => ['Opayo rejected the payment request', 'text-red-600'],
    default => ['Payment not authorised', 'text-red-600'],
};

pageTop('Result');
?>
<section class="bg-white rounded-xl shadow p-6 space-y-3">
    <h2 class="text-lg font-semibold <?= $colour ?>"><?= h($heading) ?></h2>

    <?php if ($outcome['kind'] === 'rejected'): ?>
        <ul class="text-sm text-slate-700 list-disc pl-5 space-y-1">
            <?php foreach ($outcome['errors'] as $error): ?>
                <li><?= h($error['description'] ?? 'Unknown error') ?>
                    <span class="text-slate-400">(<?= h((string) ($error['code'] ?? $error['property'] ?? '-')) ?>)</span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <dl class="text-sm grid grid-cols-[10rem_1fr] gap-y-1">
            <dt class="text-slate-500">Status</dt><dd class="font-mono"><?= h($outcome['status']) ?></dd>
            <dt class="text-slate-500">Detail</dt><dd class="font-mono"><?= h($outcome['statusDetail']) ?></dd>
            <dt class="text-slate-500">Transaction ID</dt><dd class="font-mono"><?= h($outcome['transactionId']) ?></dd>
        </dl>
    <?php endif; ?>

    <a href="checkout.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Back to checkout</a>
</section>
<?php
pageBottom();
```

- [ ] **Step 3: Lint both files.** Expected: no syntax errors.

- [ ] **Step 4: Checkpoint.** Do not commit.

---

### Task 8: Callback endpoints

**Files:**
- Rewrite: `demo/notification.php`, `demo/paypal-return.php`, `demo/apple-session.php`

**Interfaces:**
- Consumes: `$_SESSION['transactionId']`, `$_SESSION['outcome']` contract from Task 7; `Secure3Dv2Notification::isRequest(mixed): bool`, `::fromData(array)`; `CreateSecure3Dv2Challenge($endpoint, $auth, $notification, $transactionId)`; `FetchTransaction($endpoint, $auth, $transactionId)`; `CreateApplePaySession($endpoint, $auth, $domain)`; `Response\ApplePaySession::getMerchantSession(): array`, `getSessionValidationToken(): ?string`.
- Produces: `apple-session.php` JSON `{merchantSession, sessionValidationToken}` or HTTP 400 `{error, code}`.

- [ ] **Step 1: Rewrite `demo/notification.php`**

```php
<?php

/**
 * 3D Secure return. After the challenge, the card issuer sends the shopper's
 * browser here with the result ("cres"). Forward it to Opayo to finish the
 * payment, then show the result like any other.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Academe\Opayo\Pi\Checkout\PaymentOutcome;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Request\CreateSecure3Dv2Challenge;
use Academe\Opayo\Pi\ServerRequest\Secure3Dv2Notification;

$transactionId = $_SESSION['transactionId'] ?? null;

if (! Secure3Dv2Notification::isRequest($_POST) || $transactionId === null) {
    // Opened directly, or the session was lost on the way back.
    header('Location: checkout.php');
    exit;
}

$response = ResponseFactory::fromHttpResponse($client->sendRequest(
    new CreateSecure3Dv2Challenge($endpoint, $auth, Secure3Dv2Notification::fromData($_POST), $transactionId)
));

$_SESSION['outcome'] = PaymentOutcome::fromResponse($response)->summary();
header('Location: complete.php');
```

- [ ] **Step 2: Rewrite `demo/paypal-return.php`**

```php
<?php

/**
 * PayPal return. After the shopper approves (or cancels) at PayPal, Opayo
 * sends their browser here with the transactionId on the URL. The URL says
 * nothing about the result, so fetch the transaction from Opayo.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Academe\Opayo\Pi\Checkout\PaymentOutcome;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Request\FetchTransaction;

if (! in_array('paypal', $enabledMethods, true)) {
    http_response_code(400);
    exit('PayPal is not offered.');
}

$transactionId = $_GET['transactionId'] ?? $_SESSION['transactionId'] ?? null;

if ($transactionId === null) {
    header('Location: checkout.php');
    exit;
}

$response = ResponseFactory::fromHttpResponse($client->sendRequest(
    new FetchTransaction($endpoint, $auth, $transactionId)
));

$_SESSION['outcome'] = PaymentOutcome::fromResponse($response)->summary();
header('Location: complete.php');
```

- [ ] **Step 3: Rewrite `demo/apple-session.php`**

```php
<?php

/**
 * Apple Pay merchant validation. Safari asks for this before showing the
 * sheet. Ask Opayo to open a merchant session for your registered domain and
 * hand it back to the browser, with the token pay.php will need.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Request\CreateApplePaySession;
use Academe\Opayo\Pi\Response\ApplePaySession;
use Academe\Opayo\Pi\Response\ErrorCollection;

header('Content-Type: application/json');

if (! in_array('applepay', $enabledMethods, true)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Apple Pay is not offered.', 'code' => null]));
}

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
$error = ['error' => 'Opayo would not open an Apple Pay merchant session.', 'code' => null];
if ($response instanceof ErrorCollection) {
    foreach ($response as $first) {
        $error = ['error' => $first->getDescription(), 'code' => $first->getCode()];
        break;
    }
}

http_response_code(400);
echo json_encode($error);
```

- [ ] **Step 4: Lint the three files.** Expected: no syntax errors.

- [ ] **Step 5: Checkpoint.** Do not commit.

---

### Task 9: Debug layer core (wire panel, account switcher, incoming data)

**Files:**
- Rewrite: `demo/debug/enable.php` (replaces the Task 5 stub)
- Create: `demo/debug/wire.php`, `demo/debug/accounts.php`

**Interfaces:**
- Consumes: `$handler` (`HandlerStack`, in scope of `enable.php` because it is required from `bootstrap.php`); `DEBUG_PANEL_MARKER`; `h()`.
- Produces:
  - `debugRecord(string $label, mixed $data): void` (appends to `$_SESSION['debugWire']`)
  - `debugWireMiddleware(): callable` (Guzzle middleware)
  - `debugRecordIncoming(): void`
  - `debugInjectPanel(string $buffer): string` (output-buffer callback)
  - `debugApplyAccount(): void` and `const DEBUG_PUBLIC_SANDBOX`

- [ ] **Step 1: Write `demo/debug/accounts.php`** (the public sandbox profile moves here unchanged from `shared.php`)

```php
<?php

/**
 * Account switcher: run the whole demo against your .env account or Elavon's
 * public sandbox profile (which has the 3D Secure simulation and PayPal
 * enabled). Overrides the .env credentials before bootstrap.php builds Auth.
 */

declare(strict_types=1);

/** Published by Elavon on developer.elavon.com ("Test in Sandbox"). */
const DEBUG_PUBLIC_SANDBOX = [
    'OPAYO_VENDOR_NAME' => 'sandbox',
    'OPAYO_INTEGRATION_KEY' => 'hJYxsw7HLbj40cB8udES8CDRFLhuJ8G54O6rDpUXvE6hYDrria',
    'OPAYO_INTEGRATION_PASSWORD' => 'o2iHSrFybYMZpmWOQMuhsXP52V4fBtpuSDshrKDSWsBY1OiN6hwd9Kb12z4j5Us5u',
    'OPAYO_ENVIRONMENT' => 'test',
];

function debugApplyAccount(): void
{
    if (isset($_GET['debugAccount'])) {
        $_SESSION['debugAccount'] = $_GET['debugAccount'] === 'sandbox' ? 'sandbox' : 'env';
    }

    if (($_SESSION['debugAccount'] ?? 'env') === 'sandbox') {
        // Left-hand keys win, so the sandbox values replace the .env ones.
        $_ENV = DEBUG_PUBLIC_SANDBOX + $_ENV;
    }
}
```

- [ ] **Step 2: Write `demo/debug/wire.php`**

```php
<?php

/**
 * The wire panel: every request and response body, the data arriving at the
 * callback endpoints, and the session. Entries are kept in the session so
 * they survive the redirects between pay.php, notification.php and
 * complete.php, and are shown (then cleared) on the next page that renders.
 */

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function debugRecord(string $label, mixed $data): void
{
    $_SESSION['debugWire'][] = [
        'label' => $label,
        'page' => basename($_SERVER['SCRIPT_NAME'] ?? ''),
        'data' => $data,
    ];
}

function debugDecode(string $body): mixed
{
    return json_decode($body) ?? $body;
}

/**
 * Guzzle middleware: records each request and response body as it passes.
 */
function debugWireMiddleware(): callable
{
    return static function (callable $next): callable {
        return static function (RequestInterface $request, array $options) use ($next) {
            debugRecord(
                'Request ' . $request->getMethod() . ' ' . $request->getUri()->getPath(),
                debugDecode((string) $request->getBody())
            );

            return $next($request, $options)->then(static function (ResponseInterface $response) {
                debugRecord('Response HTTP ' . $response->getStatusCode(), debugDecode((string) $response->getBody()));
                $response->getBody()->rewind();

                return $response;
            });
        };
    };
}

/**
 * What the shopper's browser brought to a callback endpoint.
 */
function debugRecordIncoming(): void
{
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');

    if ($page === 'notification.php' && $_POST) {
        debugRecord('Incoming 3D Secure result (POSTed by the browser)', [
            'cres' => substr((string) ($_POST['cres'] ?? ''), 0, 60) . '... (truncated)',
            'threeDSSessionData (decoded)' => base64_decode((string) ($_POST['threeDSSessionData'] ?? '')),
        ]);
    }

    if ($page === 'paypal-return.php') {
        debugRecord('Incoming PayPal return (query string)', $_GET);
    }
}

/**
 * Output-buffer callback: puts the panel where layout.php left its marker.
 * Pages that redirect or answer JSON have no marker, so their entries wait
 * in the session for the next page.
 */
function debugInjectPanel(string $buffer): string
{
    if (! str_contains($buffer, DEBUG_PANEL_MARKER)) {
        return $buffer;
    }

    $account = $_SESSION['debugAccount'] ?? 'env';
    $html = '<aside class="lg:w-1/2 min-w-0 space-y-4">'
        . '<div class="bg-slate-800 text-slate-100 rounded-xl p-4 text-sm space-y-2">'
        . '<div class="font-semibold">Debug (demo only: DEMO_DEBUG=0 hides this)</div>'
        . '<div class="flex flex-wrap gap-3">'
        . '<a class="underline" href="/debug/check.php">Setup check</a>'
        . '<a class="underline" href="/debug/test-card.php">Test card</a>'
        . '<a class="underline" href="?debugAccount=' . ($account === 'sandbox' ? 'env' : 'sandbox') . '">'
        . 'Account: ' . ($account === 'sandbox' ? 'public sandbox' : '.env') . ' (switch)</a>'
        . '</div></div>';

    foreach ($_SESSION['debugWire'] ?? [] as $entry) {
        $html .= '<div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">'
            . h($entry['page'] . ': ' . $entry['label']) . '</div>'
            . '<pre class="bg-slate-900 text-emerald-300 text-xs rounded-lg p-3 overflow-x-auto">'
            . h(json_encode($entry['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></div>';
    }

    $session = $_SESSION;
    unset($session['debugWire']);
    $html .= '<div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">PHP session</div>'
        . '<pre class="bg-slate-800 text-sky-300 text-xs rounded-lg p-3 overflow-x-auto">'
        . h(json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></div></aside>';

    // Shown once. Write the session now: this runs while PHP shuts down.
    $_SESSION['debugWire'] = [];
    session_write_close();

    return str_replace(DEBUG_PANEL_MARKER, $html, $buffer);
}
```

- [ ] **Step 3: Rewrite `demo/debug/enable.php`**

```php
<?php

/**
 * Demo debug layer. Required by one marked line in bootstrap.php, after .env
 * is loaded and before Auth and the HTTP client are built. Nothing outside
 * demo/debug/ calls into this folder: delete it and that line, and the demo is
 * the bare integration.
 *
 * In scope from bootstrap.php: $handler (GuzzleHttp\HandlerStack).
 */

declare(strict_types=1);

require __DIR__ . '/accounts.php';
require __DIR__ . '/wire.php';
require __DIR__ . '/explain.php';

/** @var \GuzzleHttp\HandlerStack $handler */

debugApplyAccount();
$handler->push(debugWireMiddleware(), 'debug-wire');
debugRecordIncoming();
ob_start('debugInjectPanel');
```

- [ ] **Step 4: Create `demo/debug/explain.php`** with the code-specific hints (used by Task 10; carried over from `applePayReadiness()` and `explainKnownFailures()` in `shared.php`)

```php
<?php

/**
 * What each error code the demo is known to reach means, and what to do.
 */

declare(strict_types=1);

function debugExplain(string|int|null $code): string
{
    return match ((string) $code) {
        '6125' => 'The served host carries a port (e.g. 127.0.0.1:8000), which is not a valid Apple Pay domain. '
            . 'Serve the demo from a real HTTPS domain: see demo/README.md, "Running it publicly".',
        '6118' => 'The domain is not registered in MyOpayo > Settings > Pay Methods > Apple Pay. Register it, '
            . 'or correct OPAYO_APPLE_PAY_DOMAIN in .env.',
        '4006' => 'The domain is fine; this account cannot open an Opayo-managed Apple Pay session. Elavon have '
            . 'confirmed the sandbox only supports the merchant-managed certificate mode, so 4006 is the expected '
            . 'and final result on a test account. It can only be verified on a live account.',
        '6203' => 'Google Pay is enabled on this account: Opayo read the payload and rejected it. Google\'s TEST '
            . 'environment only returns a placeholder token, so this is as far as the sandbox goes.',
        '6401' => 'This wallet is not enabled for the vendor. Ask Opayo to enable it, or switch to the public '
            . 'sandbox account.',
        '1030' => 'PayPal is not enabled for the vendor. Ask Opayo to enable it, or switch to the public sandbox '
            . 'account.',
        default => 'See docs/CREDENTIALS-AND-SETUP.md for the setup steps and the error-code table.',
    };
}
```

- [ ] **Step 5: Lint the four files.** Expected: no syntax errors.

- [ ] **Step 6: Smoke test in a browser.** Start `php -S 127.0.0.1:8000 -t demo`, open `http://127.0.0.1:8000`. Expected: the checkout renders with the debug panel on the right showing the merchant session key request and response. Switch the account link; the panel shows "Account: public sandbox". Reload: the previous entries are gone (shown once).

- [ ] **Step 7: Checkpoint.** Do not commit.

---

### Task 10: Setup check page

**Files:**
- Create: `demo/debug/check.php`

**Interfaces:**
- Consumes: bootstrap variables, `merchantSessionKey()`, `debugExplain()`, `BrowserData`, `PaymentOutcome`.
- Produces: a page listing each probe as OK / not OK with code and explanation. Probes that create transactions (Google Pay, PayPal) run only when `$endpoint->isTesting()`.

- [ ] **Step 1: Write `demo/debug/check.php`**

```php
<?php

/**
 * Setup check: asks Opayo, once, whether each method can work for this
 * account. Demo only. Google Pay and PayPal are probed by starting a payment
 * that cannot complete, so they only run against the test endpoint.
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Academe\Opayo\Pi\Checkout\BrowserData;
use Academe\Opayo\Pi\Checkout\PaymentOutcome;
use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Money\Currency;
use Academe\Opayo\Pi\Request\CreateApplePaySession;
use Academe\Opayo\Pi\Request\CreatePayment;
use Academe\Opayo\Pi\Request\Enums\EntryMethod;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Request\Model\GooglePayPayment;
use Academe\Opayo\Pi\Request\Model\PayPalPayment;
use Academe\Opayo\Pi\Request\Model\PaymentMethodInterface;
use Academe\Opayo\Pi\Request\Model\Person;
use Academe\Opayo\Pi\Response\ApplePaySession;
use Academe\Opayo\Pi\Response\ErrorCollection;

$results = [];

$send = static fn ($request) => ResponseFactory::fromHttpResponse($client->sendRequest($request));

$probePayment = static function (PaymentMethodInterface $paymentMethod) use ($endpoint, $auth, $baseUrl, $send): PaymentOutcome {
    return PaymentOutcome::fromResponse($send(new CreatePayment(
        $endpoint,
        $auth,
        $paymentMethod,
        'CHECK-' . bin2hex(random_bytes(6)),
        (new Amount(new Currency('GBP'), 0))->withMajorUnit('1.00'),
        'Setup check',
        new Address('1', '1 Test Street', 'London', 'EC2A 4DP', 'GB'),
        new Person('Setup', 'Check', 'check@example.com'),
        options: [
            'entryMethod' => EntryMethod::Ecommerce,
            'strongCustomerAuthentication' => BrowserData::fromArray([])
                ->toStrongCustomerAuthentication($baseUrl . '/notification.php', '127.0.0.1', 'text/html', 'Setup check'),
        ]
    )));
};

$firstCode = static fn (PaymentOutcome $o): string|int|null => $o->isRejected() ? ($o->errors()[0]['code'] ?? null) : null;

// Credentials: every method needs a merchant session key.
try {
    $sessionKey = merchantSessionKey($client, $endpoint, $auth);
    $results[] = ['Credentials (merchant session key)', true, null, 'Opayo accepted your integration key and password.'];
} catch (RuntimeException $e) {
    $sessionKey = null;
    $results[] = ['Credentials (merchant session key)', false, null, $e->getMessage()];
}

// Apple Pay: can Opayo open a merchant session for this domain?
$apple = $send(new CreateApplePaySession($endpoint, $auth, $config['applePayDomain']));
if ($apple instanceof ApplePaySession && $apple->getMerchantSession()) {
    $results[] = ['Apple Pay for ' . $config['applePayDomain'], true, null, 'Opayo opened a merchant session.'];
} else {
    $code = null;
    if ($apple instanceof ErrorCollection) {
        foreach ($apple as $error) {
            $code = $error->getCode();
            break;
        }
    }
    $results[] = ['Apple Pay for ' . $config['applePayDomain'], false, $code, debugExplain($code)];
}

if ($sessionKey !== null && $endpoint->isTesting()) {
    // Google Pay: Google's TEST placeholder token. 6203 means enabled (the
    // payload was read); 6401 means not enabled for this vendor.
    $google = $probePayment(GooglePayPayment::fromGoogleToken($sessionKey, '127.0.0.1', 'examplePaymentMethodToken'));
    $code = $firstCode($google);
    $results[] = ['Google Pay enabled', (string) $code === '6203', $code, debugExplain($code)];

    // PayPal: a Redirect means enabled; 1030 means not.
    $payPal = $probePayment(new PayPalPayment(merchantSessionKey($client, $endpoint, $auth), $baseUrl . '/paypal-return.php'));
    $code = $firstCode($payPal);
    $results[] = ['PayPal enabled', $payPal->isRedirect(), $code, $payPal->isRedirect()
        ? 'Opayo registered a PayPal payment (left unfinished).'
        : debugExplain($code)];
} else {
    $results[] = ['Google Pay and PayPal', false, null, 'Only probed against the test endpoint (they start a payment).'];
}

pageTop('Setup check');
?>
<section class="bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-lg font-semibold text-slate-800">Setup check (<?= $endpoint->isTesting() ? 'test' : 'live' ?>)</h2>
    <ul class="space-y-3 text-sm">
        <?php foreach ($results as [$name, $ok, $code, $detail]): ?>
            <li class="border-l-4 pl-3 <?= $ok ? 'border-emerald-500' : 'border-amber-500' ?>">
                <div class="font-medium"><?= $ok ? 'OK' : 'Not available' ?>: <?= h($name) ?><?= $code !== null ? ' (' . h((string) $code) . ')' : '' ?></div>
                <div class="text-slate-600"><?= h($detail) ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
    <a href="../checkout.php" class="inline-block text-sm text-blue-600 hover:underline">&larr; Back to checkout</a>
</section>
<?php
pageBottom();
```

- [ ] **Step 2: Lint.** Expected: no syntax errors.

- [ ] **Step 3: Run it against the sandbox.** Open `http://127.0.0.1:8000/debug/check.php`. Expected on the `academe1` `.env` account from `127.0.0.1:8000`: credentials OK; Apple Pay not available with `6125`; Google Pay OK (`6203`); PayPal according to the account (switch to the public sandbox account: PayPal OK).

- [ ] **Step 4: Checkpoint.** Do not commit.

---

### Task 11: Test card page

**Files:**
- Create: `demo/debug/test-card.php`

**Interfaces:**
- Consumes: bootstrap variables, `merchantSessionKey()`, `CreateCardIdentifier($endpoint, $auth, $sessionKey, $name, $number, $expiry, ?$cvv)`, `Response\CardIdentifier::getCardIdentifier()`.
- Produces: a form that posts `merchantSessionKey`, `card-identifier` and order fields to `../pay.php`, so the payment goes through the normal endpoint.

- [ ] **Step 1: Write `demo/debug/test-card.php`**

```php
<?php

/**
 * Test card: tokenise a sandbox card on the server, choosing the cardholder
 * name that drives the sandbox's 3D Secure simulation, then pay through the
 * normal pay.php. Demo only, and never against live: real card numbers must
 * not touch your server.
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Academe\Opayo\Pi\Factory\ResponseFactory;
use Academe\Opayo\Pi\Request\CreateCardIdentifier;
use Academe\Opayo\Pi\Response\CardIdentifier;

if (! $endpoint->isTesting()) {
    http_response_code(403);
    exit('The test card page only runs against the Opayo test endpoint.');
}

$names = ['CHALLENGE', 'SUCCESSFUL', 'NOTAUTH', 'PROOFATTEMPT', 'NOTENROLLED', 'REJECT', 'TECHDIFFICULTIES', 'ERROR'];
$expiry = date('my', strtotime('+2 years'));
$order = ['amount' => '9.99', 'description' => 'Test card purchase', 'firstName' => 'Sam', 'lastName' => 'Jones', 'email' => 'sam.jones@example.com'];

pageTop('Test card');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionKey = merchantSessionKey($client, $endpoint, $auth);
    $response = ResponseFactory::fromHttpResponse($client->sendRequest(new CreateCardIdentifier(
        $endpoint,
        $auth,
        $sessionKey,
        $_POST['cardholderName'] ?? 'CHALLENGE',
        $_POST['cardNumber'] ?? '4929000000006',
        $_POST['cardExpiry'] ?? $expiry,
        ($_POST['cardCvv'] ?? '') ?: null
    )));

    if (! $response instanceof CardIdentifier) {
        echo placeholder('Opayo would not tokenise the card', 'See the wire panel for the error.');
    } else {
        ?>
        <section class="bg-white rounded-xl shadow p-6 space-y-4">
            <p class="text-sm text-slate-700">Card tokenised. This posts the card-identifier to the normal <code>pay.php</code>.</p>
            <form method="post" action="../pay.php" data-pay>
                <?= orderFields($order) ?>
                <input type="hidden" name="merchantSessionKey" value="<?= h($sessionKey) ?>">
                <input type="hidden" name="card-identifier" value="<?= h($response->getCardIdentifier()) ?>">
                <button class="w-full bg-blue-600 text-white font-semibold py-2 rounded-lg">Pay with this card</button>
            </form>
        </section>
        <script><?= file_get_contents(__DIR__ . '/../../resources/js/browser-data.js') ?></script>
        <script>document.querySelectorAll('form[data-pay]').forEach(OpayoBrowserData.fill);</script>
        <?php
    }
} else {
    ?>
    <section class="bg-white rounded-xl shadow p-6 space-y-4">
        <p class="text-sm text-slate-700">The sandbox picks the 3D Secure outcome from the cardholder name. To be
            challenged, set <code>OPAYO_APPLY_3D_SECURE=Force</code> in <code>.env</code> and use the public sandbox account.</p>
        <form method="post" class="space-y-3 text-sm">
            <label class="block">Cardholder name
                <select name="cardholderName" class="mt-1 w-full rounded border-slate-300">
                    <?php foreach ($names as $name): ?><option><?= h($name) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="block">Card number <input name="cardNumber" value="4929000000006" class="mt-1 w-full rounded border-slate-300"></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block">Expiry (MMYY) <input name="cardExpiry" value="<?= h($expiry) ?>" class="mt-1 w-full rounded border-slate-300"></label>
                <label class="block">CVV <input name="cardCvv" value="123" class="mt-1 w-full rounded border-slate-300"></label>
            </div>
            <button class="w-full bg-blue-600 text-white font-semibold py-2 rounded-lg">Tokenise the test card</button>
        </form>
    </section>
    <?php
}

pageBottom();
```

- [ ] **Step 2: Lint.** Expected: no syntax errors.

- [ ] **Step 3: Checkpoint.** Do not commit.

---

### Task 12: Remove the old demo code; enforce the bare integration

**Files:**
- Delete: `demo/shared.php`, `demo/result.php`
- Create: `tests/Demo/BareIntegrationTest.php`

**Interfaces:**
- Consumes: the finished demo.
- Produces: a test that fails if integration files depend on `debug/` or on `shared.php`.

- [ ] **Step 1: Confirm nothing still uses the old files**

Use Grep for `shared.php|result.php|sendAndRecord|recordWire|renderResult|demoAccount` in `demo/`. Expected: matches only in `demo/README.md` (fixed in Task 14). If any PHP file matches, fix it before deleting.

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Demo;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Deleting demo/debug/ and the one marked line in bootstrap.php must leave the
 * bare integration. So no integration file may reach into debug/, and the old
 * all-in-one helper file must stay gone.
 */
class BareIntegrationTest extends TestCase
{
    private function integrationFiles(): array
    {
        $root = realpath(__DIR__ . '/../../demo');
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($file->getExtension() === 'php' && ! str_contains($path, '/demo/debug/')) {
                $files[$path] = file_get_contents($path);
            }
        }

        return $files;
    }

    public function testOnlyBootstrapRequiresTheDebugLayerAndOnlyOnce()
    {
        foreach ($this->integrationFiles() as $path => $source) {
            $count = substr_count($source, 'debug/');
            $expected = str_ends_with($path, '/demo/bootstrap.php') ? 2 : 0;

            $this->assertSame($expected, $count, "$path references debug/ $count time(s)");
        }
    }

    public function testOldHelpersAreGone()
    {
        $this->assertFileDoesNotExist(__DIR__ . '/../../demo/shared.php');
        $this->assertFileDoesNotExist(__DIR__ . '/../../demo/result.php');

        foreach ($this->integrationFiles() as $path => $source) {
            $this->assertStringNotContainsString('shared.php', $source, $path);
        }
    }
}
```

The `2` for `bootstrap.php` is the marked comment line ("demo/debug/") plus the `require` line ("/debug/enable.php").

- [ ] **Step 3: Run it to verify it fails**

Run: `php vendor/phpunit/phpunit/phpunit --exclude-group integration tests/Demo/BareIntegrationTest.php`
Expected: FAIL on `testOldHelpersAreGone` (`demo/shared.php` exists).

- [ ] **Step 4: Delete the old files**

```bash
rm demo/shared.php demo/result.php
```

- [ ] **Step 5: Run the test and the whole suite.** Expected: all pass.

- [ ] **Step 6: Checkpoint.** Do not commit.

---

### Task 13: Sandbox run-through

**Files:** none (verification only; fix anything it finds in the owning task's files).

- [ ] **Step 1:** Start the demo: `php -S 127.0.0.1:8000 -t demo`. Open `http://127.0.0.1:8000`.
- [ ] **Step 2: Card without a challenge.** `.env` account, `OPAYO_APPLY_3D_SECURE` unset. Pay with `4929000000006`, CVV `123`, any name. Expected: `complete.php` shows "Payment successful"; the wire panel shows session key, card identifier traffic, and the payment request/response. Reload: redirected to checkout (no double payment).
- [ ] **Step 3: Card with a challenge.** Switch to the public sandbox account, set `OPAYO_APPLY_3D_SECURE=Force`, open `debug/test-card.php`, choose `CHALLENGE`, tokenise, pay. Expected: "Continue to 3D Secure" page; the ACS challenge; back through `notification.php` to "Payment successful"; the panel shows the incoming `cres`.
- [ ] **Step 4: PayPal.** Public sandbox account. Pay with PayPal, log in with a PayPal sandbox buyer, approve. Expected: back through `paypal-return.php` to "Payment successful".
- [ ] **Step 5: Google Pay.** In Chrome signed into Google. Expected: the button shows; after choosing a card, `complete.php` shows "Opayo rejected the payment request" with `Invalid Google Pay payload` (`6203`).
- [ ] **Step 6: Apple Pay.** Run `./demo/serve-public.sh` (or `.ps1`), open the ngrok URL in Safari on the iPhone. Expected: the button shows; tapping it ends with "Merchant validation failed: ... (4006)". On desktop Chrome: "Apple Pay needs Safari ...".
- [ ] **Step 7: Switches.** Set `DEMO_ENABLE_PAY_PAL=0`. Expected: PayPal shows "PayPal is not offered"; posting `method=paypal` to `pay.php` directly answers HTTP 400.
- [ ] **Step 8: Bare integration.** Set `DEMO_DEBUG=0`. Repeat step 2. Expected: same result, no debug panel, no errors.
- [ ] **Step 9: Behind ngrok, card with a challenge.** Repeat step 3 through the ngrok URL. Expected: the notification URL in the payment request (wire panel) is `https://...ngrok-free.dev/notification.php` and the challenge completes.
- [ ] **Step 10: Checkpoint.** Report each step's result to the user, with any failures and the fix made.

---

### Task 14: Docs and packaging

**Files:**
- Create: `docs/INTEGRATION.md`, `.gitattributes`
- Modify: `README.md`, `demo/README.md`

**Interfaces:**
- Consumes: the finished demo files (snippets are copied from them, never written separately).

- [ ] **Step 1: Write `.gitattributes`**

```text
# Keep Composer installs (GitHub dist archives) to the package itself.
# The demo and tests are available by cloning the repository.
/demo           export-ignore
/tests          export-ignore
/phpunit.xml    export-ignore
/.env.example   export-ignore
```

- [ ] **Step 2: Write `docs/INTEGRATION.md`** with exactly these sections and contents:

1. `# Integrating Opayo Pi` with a three-sentence introduction: what the guide covers, that `docs/CREDENTIALS-AND-SETUP.md` explains where each credential comes from, and that the demo (`demo/`) is this guide applied.
2. `## 1. Choose your payment methods`: a table with columns Method / What the shopper sees / Endpoints you host / Sandbox / Live, rows for Card (`pay`, `notification`; sandbox: complete), Google Pay (`pay`, `notification`; sandbox: stops at `6203`), Apple Pay (`apple-session`, `pay`; sandbox: stops at `4006`), PayPal (`pay`, `paypal-return`; sandbox: complete). Then the three layers that decide whether a method is "on": your config, Opayo enrolment (cannot be queried; `6401`, `1030` or `4006` on the first payment; check at go-live), the shopper's device (the browser checks in each front end).
3. `## 2. The core`: `### Config` (quote `demo/bootstrap.php` from `$endpoint = ` to the end of `$config`); `### The pay endpoint` (quote `demo/pay.php` steps 1 to 5 with one sentence before each); `### Handling the outcome` (a table of the four `OutcomeKind` cases: what it means, what to do, which `PaymentOutcome` getters apply, and the sentence "The same four outcomes arrive whatever the method, so this code is written once."); `### The 3D Secure notification endpoint` (quote `demo/notification.php`); `### Browser data` (how to serve `resources/js/browser-data.js`, one usage line, and that `BrowserData::fromArray($_POST)` reads it).
4. `## 3. Payment methods`: for each of Card, Google Pay, Apple Pay, PayPal, the four headings `#### Front end` (quote the partial's form and script), `#### Back-end config` (the `$config` keys and `.env` variables it uses), `#### Extra endpoint` (quote `apple-session.php` or `paypal-return.php`, or "None"), `#### Testing` (what the sandbox proves and what waits for live, linking the matching section of `docs/CREDENTIALS-AND-SETUP.md`). Under each "Front end", one line: "Check the method is enabled on the server too: `pay.php` refuses methods you do not offer."
5. `## 4. Going live`: checklist: `OPAYO_ENVIRONMENT=live` and live credentials; each method enabled in MyOpayo; Apple domain registered for live; `GOOGLE_PAY_ENVIRONMENT=PRODUCTION` and `GOOGLE_PAY_MERCHANT_ID`; one real payment per method, then refund it. Then "Reporting a problem": send the request and response bodies (the demo's debug panel shows them; in your app, log them).
6. `## 5. The worked example`: one sentence that the demo is this guide applied and is run by cloning the repository; a table mapping each section above to its demo file; one line: "The `demo/debug/` folder (wire panel, setup check, test card) is demo-only tooling, attached by one marked line in `bootstrap.php`; delete both and what remains is exactly this guide."

- [ ] **Step 3: Update `README.md`.** Add, near the top under the description, a short "Getting started" paragraph linking `docs/INTEGRATION.md` (how to integrate) and `docs/CREDENTIALS-AND-SETUP.md` (where credentials come from), and the line "To run the demo, clone this repository: the demo is not included in Composer installs."

- [ ] **Step 4: Rewrite `demo/README.md`.** Keep "Running", "Running it publicly (for Apple Pay / wallets)", "Test cards" and the magic-names table. Replace "What it demonstrates", "Files" and "Wallets" with: a "Layout" section mirroring the spec's file tree with one line per file; a "Debug layer" section (what it adds, `DEMO_DEBUG=0`, the setup check and test card pages, the account switcher); and a "Settings" block listing every `.env` key the demo reads (`OPAYO_*`, `GOOGLE_PAY_*`, `DEMO_ENABLE_*`, `DEMO_DEBUG`). Point to `docs/INTEGRATION.md` at the top. Remove references to `shared.php`, `result.php`, `index.php` as the checkout, the 3D Secure checkbox, and the in-page Google Pay knobs.

- [ ] **Step 5: Check the docs against the code.** Use Grep for every file name and function the docs mention (`bootstrap.php`, `merchantSessionKey`, `PaymentOutcome`, `BrowserData`, `OutcomeKind`, each `.env` key); each must exist. Use Grep on `docs/INTEGRATION.md` and `demo/README.md` for `shared.php|result.php|use3ds`; expected: no matches.

- [ ] **Step 6: Run the whole suite one last time.** Expected: all pass.

- [ ] **Step 7: Final checkpoint.** Summarise every file created, modified and deleted for the user to review and commit. Do not commit.
