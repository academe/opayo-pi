[![Build Status](https://travis-ci.org/academe/SagePay-Integration.svg?branch=PSR-7)](https://travis-ci.org/academe/SagePay-Integration)
[![Latest Stable Version](http://poser.pugx.org/academe/sagepaymsg/v)](https://packagist.org/packages/academe/sagepaymsg) [![Total Downloads](http://poser.pugx.org/academe/sagepaymsg/downloads)](https://packagist.org/packages/academe/sagepaymsg) [![Latest Unstable Version](http://poser.pugx.org/academe/sagepaymsg/v/unstable)](https://packagist.org/packages/academe/sagepaymsg) [![License](http://poser.pugx.org/academe/sagepaymsg/license)](https://packagist.org/packages/academe/sagepaymsg)

<!-- TOC -->

- [Opayo Pi PSR-7 Message REST API Library](#opayo-pi-psr-7-message-rest-api-library)
    - [Package Development](#package-development)
    - [Want to Help?](#want-to-help)
    - [Overview; How to use](#overview-how-to-use)
        - [Installation](#installation)
        - [Create a Session Key](#create-a-session-key)
        - [Get a Card Identifier](#get-a-card-identifier)
        - [Submit a Transaction](#submit-a-transaction)
        - [Fetch a Transaction Result Again](#fetch-a-transaction-result-again)
        - [Repeat Payments](#repeat-payments)
        - [Using 3D Secure](#using-3d-secure)
        - [3D Secure Versions 1](#3d-secure-versions-1)
            - [3D Secure Version 1 Redirect](#3d-secure-version-1-redirect)
            - [Final Transaction After 3D Secure](#final-transaction-after-3d-secure)
        - [3D Secure Versions 2](#3d-secure-versions-2)
            - [SCA: Strong Customer Authentication](#sca-strong-customer-authentication)
            - [3D Secure Version 2 Redirect](#3d-secure-version-2-redirect)
            - [3D Secure Version 2 Notification Handling](#3d-secure-version-2-notification-handling)
    - [Payment Methods](#payment-methods)

<!-- /TOC -->

# Opayo Pi PSR-7 Message REST API Library

This package provides the data models for the [Opayo Pi](https://developer-eu.elavon.com/docs/opayo)
(was *Sage Pay Integration*) payment gateway.
It does not provide the transport mechanism, so you can use what PSR-18 client you like for that,
for example Guzzle (7+ or 6+HTTPlug adapter), curl or another PSR-7 library.

You can use this library as a PSR-7 message generator/consumer, or go a level down and handle all the
data through arrays - both are supported.

This library is fairly old, so a bit kludgy in places, but should improve over time.

## Package Development

The Sage Pay Integration payment gateway is a RESTful API run by by [Sage Pay](https://sagepay.com/).
You can [apply for an account here](https://www.opayo.co.uk/apply?partner_id=3F7A4119-8671-464F-A091-9E59EB47B80C)
(my partner link).

From v3.0.0 this package is being rebranded for Opayo, gets a new composer name, and a new base namespace.

| Major Release Number | Package Name | Base Namespace |
| -------------------- | ------------ | -------------- |
| 2.x.x | academe/sagepaymsg | Academe\SagePay\Psr7 |
| 3.x.x | academe/opayo-pi | Academe\Opayo\Pi |

The `PSR7` branch is now in maintenance mode only, and won't have any major changes - just bugfixes if they are reported.
The aim is to release on the master branch as soon as a demo (and some units tests) are up and running.

The aim is for this package to support, at the backend, all functionality that the gateway supports.

## Want to Help?

Issues, comments, suggestions and PRs are all welcome. So far as I know, this is the first API for the
Opayo Pi REST API, so do get involved, as there is a lot of work to do.

Tests need to be written. I can extend tests, but have not got to the stage where I can set up a test
framework from scratch.

More examples of how to handle errors is also needed. Exceptions can be raised in many places.
Some exceptions are issues at the remote end, some fatal authentication errors, and some just relate
to validation errors on the payment form, needing the user to fix their details. Temporary tokens
expire over a period and after a small number of uses, so those all need to be caught and the
user taken back to the relevant place in the protocal without losing anything they have entered
so far (that has not expired).

## Overview; How to use

Note that this example code deals only with using the gateway from the back-end.
There is a JavaScript front-end too, with hooks to deal with expired
session keys and card tokens.
This library does provide support for the front end though, and this is noted where relevant.

### Installation

Get the latest release:

    composer.phar require academe/opayo-pi

Until this library has been released to packagist, include the VCS in `composer.json`:

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/academe/SagePay-Integration.git"
        }
    ]

### Create a Session Key

The `CreateSessionKey` message can be used like this:

```php
// composer require guzzlehttp/guzzle
// This will bring in guzzle/psr7 too, which is what we will use.

use GuzzleHttp\Client; // Or your favourite PSR-18 client
use GuzzleHttp\Exception\ClientException;
use Academe\Opayo\Pi\Model\Auth;
use Academe\Opayo\Pi\Model\Endpoint;
use Academe\Opayo\Pi\Request\CreateSessionKey;
use Academe\Opayo\Pi\Factory;
use Academe\Opayo\Pi\Request\CreateCardIdentifier;
use Academe\Opayo\Pi\Factory\ResponseFactory;

// Set up authentication details object.

$auth = new Auth('vendor-name', 'your-key', 'your-password');

// Also the endpoint.
// This one is set as the test API endpoint.

$endpoint = new Endpoint(Endpoint::MODE_TEST); // or MODE_LIVE

// Request object to construct the session key message.

$keyRequest = new CreateSessionKey($endpoint, $auth);

// PSR-18 HTTP client to send this message.
// If using Guzzle 6, then wrap it with an adapter such as HTTPlug,
// see https://docs.php-http.org/en/latest/clients/guzzle6-adapter.html

$client = new Client();

// Send the PSR-7 message to request a session key.
// The message will be generated by guzzle/psr7 or zendframework/zend-diactoros, with discovery
// on which is installed. You can explictly create the PSR-7 factory instead and pass that in
// as a third parameter when creating Request\CreateSessionKey.

$keyResponse = $client->sendRequest($keyRequest);

// Capture the result in our local response model.
// Use the ResponseFactory to automatically choose the correct message class.

$sessionKey = ResponseFactory::fromHttpResponse($keyResponse);

// If an error is indicated, then you will be returned an ErrorCollection instead
// of the session key. Look into that to diagnose the problem.

if ($sessionKey->isError()) {
    // $session_key will be Response\ErrorCollection
    var_dump($sessionKey->first());
    exit; // Better handling needed than this!
}

// The result we want:

echo "Session key is: " . $sessionKey->getMerchantSessionKey();
```

The session key will be valid for 20 minutes, and allows the front end of your
site to save card details at Opayo, using the
[form integration JavaScript](https://developer-eu.elavon.com/docs/opayo/integrate-your-own-form)
or the [Drop-In Checkout](https://developer-eu.elavon.com/docs/opayo/integrate-our-drop-checkout).
This way the card details stay at the front end, and do not go near your server.

### Get a Card Identifier

The Card Identifier (a temporary, tokenised card detail, where teh card is actually
stored at Opayo) can be created using the equally temporary session key.

Normally it would be created on the front end, using an AJAX request from your
browser, so the card details would never touch your application. For testing and
development, the card details can be sent from your test script, emulating
the front end, and that is detailed below.

```php
use Academe\Opayo\Pi\Request\CreateCardIdentifier;

// Create a card indentifier on the API.
// Note the MMYY order is most often used for GB gateways like Sage Pay. Many European
// gateways tend to go MSN first, i.e. YYMM.
// $endpoint, $auth and $session_key from before:

$cardIdentifierRequest = new CreateCardIdentifier(
    $endpoint, $auth, $sessionKey,
    'Fred', '4929000000006', '1220', '123' // name, card, MMYY, CVV
);

// Send the PSR-7 message.
// The same error handling as shown earlier can be used.

$cardIdentifierResponse = $client->sendRequest($cardIdentifierRequest);

// Grab the result as a local model.
// If all is well, we will have a CardIdentifier that will be valid for use
// for the next 400 seconds.

$cardIdentifier = ResponseFactory::fromHttpResponse($cardIdentifierResponse);

// Again, an ErrorCollection will be returned in the event of an error:

if ($cardIdentifier->isError()) {
    // $session_key will be Response\ErrorCollection
    var_dump($cardIdentifier->first());
    exit; // Don't do this in production.
}

// When the card is stored by the front end browser only, the following three
// items will be posted back to your application.

echo "Card identifier = " . $cardIdentifier->getCardIdentifier();
echo "Card type = " . $cardIdentifier->getCardType(); // e.g. Visa

// This card identifier will expire at the given time. Do note that this
// will be the timestamp at the Sage Pay server, not locally. You may be
// better off just starting your own 400 second timer here.

var_dump($cardIdentifier->getExpiry()); // DateTime object.
```

At this point the card details are *sane* and have been saved in the remote
API. Nothing has been checked against the bank, so we have no idea yet if these
details will be authenticated or not.

What is a mystery to me is just why the card identifier is needed at all.
The session key is only valid for one set of card details, so the session
key should be all the Sage Pay needs to know to access those card details
when the final purchase is requested. But no, this additional
"card identifier" also needs to be sent to the gateway.

The `merchantSessionKey` identifies a short-lived storage area in the gateway
for passing the card details from client to gateway. The `cardIdentifier`
then identifies a single card within the storage area.

### Submit a Transaction

A transaction can be initiated using the card identifier.

```php
use Academe\Opayo\Pi\Money;
use Academe\Opayo\Pi\PaymentMethod;
use Academe\Opayo\Pi\Request\CreatePayment;
use Academe\Opayo\Pi\Request\Model\SingleUseCard;
use Academe\Opayo\Pi\Money\Amount;
use Academe\Opayo\Pi\Request\Model\Person;
use Academe\Opayo\Pi\Request\Model\Address;
use Academe\Opayo\Pi\Money\MoneyAmount;
use Money\Money as MoneyPhp;

// We need a billing address.
// Sage Pay has many mandatory fields that many gateways leave as optional.
// Sage Pay also has strict validation on these fields, so at the front end
// they must be presented to the user so they can modify the details if
// submission fails validation.

$billingAddress = Address::fromData([
    'address1' => 'address one',
    'postalCode' => 'NE26',
    'city' => 'Whitley',
    'state' => 'AL',
    'country' => 'US',
]);

// We have a customer to bill.

$customer = new Person(
    'Bill Firstname',
    'Bill Lastname',
    'billing@example.com',
    '+44 191 12345678'
);

// We have an amount to bill.
// This example is £9.99 (999 pennies).

$amount = Amount::GBP()->withMinorUnit(999);

// Or better to use the moneyphp/money package:

$amount = new MoneyAmount(MoneyPhp::GBP(999));

// We have a card to charge (we get the session key and captured the card identifier earlier).
// See below for details of the various card request objects.

$card = new SingleUseCard($session_key, $card_identifier);

// If you want the card to be reusable, then set its "save" flag:

$card = $card->withSave();

// Put it all together into a payment transaction.

$paymentRequest = new CreatePayment(
    $endpoint,
    $auth,
    $card,
    'MyVendorTxCode-' . rand(10000000, 99999999), // This will be your local unique transaction ID.
    $amount,
    'My Purchase Description',
    $billingAddress,
    $customer,
    null, // Optional shipping address
    null, // Optional shipping recipient
    [
        // Don't use 3DSecure this time.
        'Apply3DSecure' => CreatePayment::APPLY_3D_SECURE_DISABLE,
        // Or force 3D Secure.
        'Apply3DSecure' => CreatePayment::APPLY_3D_SECURE_FORCE,
        // There are other options available.
        'ApplyAvsCvcCheck' => CreatePayment::APPLY_AVS_CVC_CHECK_FORCE
    ]
);

// Send it to Sage Pay.

$paymentResponse = $client->sendRequest($paymentRequest);

// Assuming we got no exceptions, extract the response details.

$payment = ResponseFactory::fromHttpResponse($paymentResponse);

// Again, an ErrorCollection will be returned in the event of an error.
if ($payment->isError()) {
    // $payment_response will be Response\ErrorCollection
    var_dump($payment->first());
    exit;
}

if ($payment->isRedirect()) {
    // If the result is "3dAuth" then we will need to send the user off to do their 3D Secure
    // authorisation (more about that process in a bit).
    // A status of "Ok" means the transaction was successful.
    // A number of validation errors can be captured and linked to specific submitted
    // fields (more about that in a bit too).
    // In future gateway releases there may be other reasons to redirect, such as PayPal
    // authorisation.
    // ...
}

// Statuses are listed in `AbstractTransaction` and can be obtained as an array using the static
// helper method:
// AbstractTransaction::constantList('STATUS')

echo "Final status is " . $payment->getStatus();

if ($payment->isSuccess()) {
    // Payment is successfully authorised.
    // Store everything, then tell the user they have paid.
}
```

### Fetch a Transaction Result Again

Given the TransactionId, you can fetch the transaction details.
If the transaction was successful, then it will be available immediately.
If a 3D Secure action was needed, then the 3D Secure results need to be sent
to Sage Pay before you can fetch the transaction.
Either way, this is how you do it:

```php
use Academe\Opayo\Pi\Request\FetchTransaction;

// Prepare the message.

$transactionResult = new FetchTransaction(
    $endpoint,
    $auth,
    $transaction_response->getTransactionId() // From earlier
);

// Send it to Sage Pay.

$response = $client->sendRequest($transactionResult);

// Assuming no exceptions, this gives you the payment or repeat payment record.
// But do check for errors in the usual way (i.e. you could get an error collection here).

$fetchedTransaction = ResponseFactory::fromHttpResponse($response);
```

### Repeat Payments

A previous transaction can be used as a base for a repeat payment.
You can amend the shipping details and the amount (with no limit)
but not the payee details or address.

```php
use Academe\Opayo\Pi\Request\CreateRepeatPayment;

$repeat_payment = new CreateRepeatPayment(
    $endpoint,
    $auth,
    $previous_transaction_id, // The previous payment to take card details from.
    'MyVendorTxCode-' . rand(10000000, 99999999), // This will be your local unique transaction ID.
    $amount, // Not limited by the original amount.
    'My Repeat Purchase Description',
    null, // Optional shipping address
    null // Optional shipping recipient
);
```

All other options remain the same as for the original transaction
(though it does appear that giftAid can now be set in the API).

### Using 3D Secure

Now, if you want to use 3D Secure (and you really should, and will be forced to in 2022).

To turn on 3D Secure, use the appropriate option when sending the payment:

```php
$paymentRequest = new CreatePayment(
    ...
    [
        // Also available: APPLY_3D_SECURE_USEMSPSETTING and APPLY_3D_SECURE_FORCEIGNORINGRULES
        'Apply3DSecure' => CreatePayment::APPLY_3D_SECURE_FORCE,
        // or set APPLY_3D_SECURE_USEMSPSETTING to control it from the MyOpayo panel.
    ]
);
```

### 3D Secure Versions 1

This is being phased out in the EC by the end 2021 and the the UK by March 2022.
Please use Version 2, which will be mandatory.

#### 3D Secure Version 1 Redirect

The result of the transaction, assuming all is otherwise fine, will be a `Secure3DRedirect` object.
This message will return true for `isRedirect()`.
Given this, a POST redirection is needed.
Note also that even if the card details were invalid, a 3D Secure redirect may still be returned.
It is not clear why the banks do this, but you need to be ready for it.

This minimal form will demonstrate how the redirect is done:

```php
// $transaction_response is the message we get back after sending the payment request.

if ($transactionResponse->isRedirect()) {
    // This is the bank URL that Sage Pay wants us to send the user to.

    $url = $transactionResponse->getAcsUrl();

    // This is where the bank will return the user when they are finished there.
    // It needs to be an SSL URL to avoid browser errors. That is a consequence of
    // the way the banks do the redirect back to the merchant siteusing POST and not GET,
    // and something we cannot control.

    $termUrl = 'https://example.com/your-3dsecure-result-handler-post-path/';

    // $md is optional and is usually a key to help find the transaction in storage.
    // For demo, we will just send the vendorTxCode here, but you should avoid exposing
    // that value in a real site. You could leave it unused and just store the vendorTxCode
    // in the session, since it will always only be used when the user session is available
    // (i.e. all callbacks are done through the user's browser).

    $md = $transactionResponse->getTransactionId();

    // Based on the 3D Secure redirect message, our callback URL and our optional MD,
    // we can now get all the POST fields to perform the redirect:

    $paRequestFields = $transactionResponse->getPaRequestFields($termUrl, $md);

    // All these fields will normally be hidden form items and the form would auto-submit
    // using JavaScript. In this example we display the fields and don't auto-submit, so
    // you can se what is happening:

    echo "<p>Do 3DSecure</p>";
    echo "<form method='post' action='$url'>";
    foreach($paRequestFields as $field_name => $field_value) {
        echo "<p>$field_name <input type='text' name='$field_name' value='$field_value' /></p>";
    }
    echo "<button type='submit'>Click here if not redirected in five seconds</button>";
    echo "</form>";

    // Exit in the appropriate way for your application or framework.
    exit;
}
```

The above example does not take into account how you would show the 3D Secure form in an iframe instead
of inline. That is out of scope for this simple description.
Two main things need to be considered when using an iframe: 1) the above form must `target` the iframe
by name; and 2) on return to the $termUrl, the page must break itself out of the iframe. That's the absolute
essentials.

This form will then take the user off to the 3D Secure password page. For Sage Pay testing, use the code
`password` to get a successful response when you reach the test 3D Secure form.

Now you need to handle the return from the bank. Using Guzzle you can catch the return
message as a PSR-7 ServerRequest like this:

```php
use Academe\Opayo\Pi\ServerRequest\Secure3DAcs;

$serverRequest = \GuzzleHttp\Psr7\ServerRequest::fromGlobals();
// or if using a framework that supplies a PSR-7 server request, just use that.

// isRequest() is just a sanity check before diving in with assumptions about the
// incoming request.

if (Secure3DAcs::isRequest($serverRequest->getBody()))
    // Yeah, we got a 3d Secure server request coming at us. Process it here.

    $secure3dServerRequest = new Secure3DAcs($serverRequest);
    ...
}
```

or

```php
use Academe\Opayo\Pi\ServerRequest\Secure3DAcs;

if (Secure3DAcs::isRequest($_POST)) {
    $secure3dServerRequest = Secure3DAcs::fromData($_POST);
    ...
}
```

Both will work fine, but it's just about what works best for your framework and application.

Handling the 3D Secure result involves two steps:

1. Passing the result to Sage Pay to get the 3D Secure state (CAUTION: see note below).
2. Fetching the final transaction result from Sage Pay.

```php
    use Academe\Opayo\Pi\Request\CreateSecure3D;

    $request = new CreateSecure3D(
        $endpoint,
        $auth,
        $secure3dServerRequest,
        // Include the transaction ID.
        // For this demo we sent that as `MD` data rather than storing it in the session.
        // The transaction ID will generally be in the session; putting it in MD exposes it
        // to the end user, so don't do this unless use a nonce!
        $secure3dServerRequest->getMD()
    );

    // Send to Sage Pay and get the final 3D Secure result.

    $response = $client->send($request);
    $secure3dResponse = ResponseFactory::fromHttpResponse($response);

    // This will be the result. We are looking for `Authenticated` or similar.
    // The $secure3dResponse will normally be the full transaction details.
    //
    // NOTE: the result of the 3D Secure verification here is NOT safe to act on.
    // I have found that on live, it is possible for the card to totally fail
    // authentication, while the 3D Secure result returns `Authenticated` here.
    // This is a decision the bank mnakes. They may skip the 3D Secure and mark
    // it as "Authenticated" at their own risk. Just log this information.
    // Instead, you MUST fetch the remote transaction from the gateway to find
    // the real state of both the 3D Secure check and the card authentication
    // checks.

    echo $secure3dResponse->getStatus();
```

#### Final Transaction After 3D Secure

Whether 3D Secure passed or not, get the transaction. However - *do not get it too soon*.
The test instance of Sage Pay has a slight delay between getting the 3D Secure result and
being able to fetch the transaction.
It is safer just to sleep for one second at this time, which is an arbitrary period but
seems to work for now.
A better method would be to try immediately, then if you get a 404, back off for a short
time and try again, and maybe once more if necessary.
This is supposed to have been fixed in the gateway several times, but still gets occasionally
reported as still being an issue.

```php
    // Give the gateway some time to get its syncs in order.

    sleep(1);

    // Fetch the transaction with full details.

    $transactionResult = new FetchTransaction(
        $endpoint,
        $auth,
        // transaction ID would normally be in the session, as described above, but we put it
        // into the MD for this demo.
        $secure3dServerRequest->getMD()
    );

    // Send the request for the transaction to Sage Pay.

    $response = $client->sendRequest($transactionResult);

    // We should now have the payment, repeat payment, or an error collection.

    $transactionFetch = ResponseFactory::fromHttpResponse($response);

    // We should now have the final results.
    // The transaction data is all [described in the docs](https://test.sagepay.com/documentation/#transactions).

    echo json_encode($transactionFetch);
```

### 3D Secure Versions 2

This will be mandatory worldwide in 2022, so start using it now.

#### SCA: Strong Customer Authentication

3D Secure version 2 flow is triggered by supplying SCA information.
The following example is a minimal SCA object.
There is no guidance on the Opayo site on how all this information is
gathered and how it is used, and there are very few defaults, so some experimentation is needed.

Note that unlike 3DS v1, the notification URL - where the user is sent back with the results -
are supplied right at the start, whether a redirect is needed or not.

```php
// When using 3D Secure v2, put together additional SCA details.

$strongCustomerAuthentication = new StrongCustomerAuthentication(
    'https://example.com/your-3dsecure-notification-handler-post-url/',
    $_SERVER['REMOTE_ADDR'], // IPv4 of user's browser
    $_SERVER['HTTP_ACCEPT'], // Full Accept header provided by user's browser
    true, // if javascript enabled on the browser; your payment page would need to detect that
    'en-GB', // Language of the user's browser; docs are ambiguous on whether "en-GB" or just "en"
    $_SERVER['HTTP_USER_AGENT'], // Full user agent of the user's browser
    StrongCustomerAuthentication::CHALLENGE_WINDOW_SIZE_FULLSCREEN,
    StrongCustomerAuthentication::TRANS_TYPE_GOODS_AND_SERVICE_PURCHASE,
    [
        // These are mandatory if javascript is enabled.
        'browserJavaEnabled' => false,
        'browserColorDepth' => StrongCustomerAuthentication::BROWSER_COLOR_DEPTH_32,
        'browserScreenHeight' => 512,
        'browserScreenWidth' => 1024,
        'browserTz' => 60,
    ]
);
```

This is passed in as an additional option when creating the paymennt.

```php
$paymentRequest = new CreatePayment(
    ...
    [
        // 3D Secure v2 needs Strong Customer Authentication (SCA) which requires
        // additional browser details.
        'strongCustomerAuthentication' => $strongCustomerAuthentication,
    ]
);
```

#### 3D Secure Version 2 Redirect

The bank or 3DS rules may decide that no further authentication is needed, and so
no redirect is necessary, then you will get the transaction details back immediately.

If a redirect is needed, then it is done through a `POST` like 3DS v1.

```php
// Example 3DS POST redirect with a button.

// The $threeDSSessionData is an optional string that can be passed to the ACS,
// and will be returned with the result to help match up the user with their
// payment request.

if ($transactionResponse->isRedirect()) {
    echo '<form method="post" action="'.$payment->getAcsUrl().'">';
    foreach($transactionResponse->getPaRequestFields($threeDSSessionData) as $name => $value) {
        echo '<input type="hidden" name="'.$name.'" value="'.$value.'" />';
    }
    echo '<button type="submit">Click here if not redirected in five seconds</button>';
    echo '</form>';
}
```

#### 3D Secure Version 2 Notification Handling

After the redirect, the ACS will return the user to your notificartion URL with a result.
Check the response has come from the ACS and instantiate the server request object:

```php
use Academe\Opayo\Pi\ServerRequest\Secure3Dv2Notification;

if (Secure3Dv2Notification::isRequest($_POST)) {
    $secure3Dv2Notification = new Secure3Dv2Notification::fromData($_POST);
    ...
    // If you need the sent session data, it can be found here:

    $threeDSSessionData = $secure3Dv2Notification->getThreeDSSessionData();
}
```

Finally use that result to get the transaction authorisation result.

```php
    use Academe\Opayo\Pi\Request\CreateSecure3Dv2Challenge;

    $request = new CreateSecure3Dv2Challenge(
        $endpoint, $auth, $notification, $transactionId
    );

    $response = $client->sendRequest($request);
    $transaction = ResponseFactory::fromHttpResponse($response);
```

## Payment Methods

At this time, Sage Pay Pi supports just `card` payment types. However, there are three
different types of card object:

1. `SingleUseCard` - The fist time a card is used. It has been tokenised and will
    be held against the merchant session key for 400 seconds before being discarded.
2. `ReusableCard` - A card that has been saved and so is reusable. Use this for
    non-interaractive payments when no CVV is being used.
3. `ReusableCvvCard` - A card that has been saved and so is reusable, and has
    been linked to a CVV and merchant session. Use this for interactive reuse of a card, where
    the user is being asked to supply their CVV for additional security, but otherwise do not
    need to reenter all their card details.
    The CVV is (normally) linked to the card and the merchant session on the client side,
    and so will remain active for a limited time (400 seconds).

The `ReusableCard` does not need a merchant session key. `ReusableCvvCard` does require a
merchant session key and a call to link the session key + card identifier + CVV together
(preferably on the client side, but can be done server-side if appropriately PCI accredited
or while testing).

A CVV can be linked to a reusable card with the `LinkSecurityCode` message:

```php
use Academe\Opayo\Pi\Request\LinkSecurityCode;

$securityCode = new LinkSecurityCode(
    $endpoint,
    $auth,
    $sessionKey,
    $cardIdentifier,
    '123' // The CVV obtained from the user.
);

// Send the message to create the link.
// The result will be a `Response\NoContent` if all is well.

$securityCodeResponse = ResponseFactory::fromHttpResponse(
    $client->sendRequest($securityCode)
);

// Should check for errors here:

if ($securityCodeResponse->isError()) {...}
```

To save a reusable card, take the `PaymentMethod` from a successful payment.
Note: it is not possible at this time to set up a reusable card without making a payment.
That is a restriction of the gateway. Some gateways will allow you to create zero-amount
payments just to authenticate and set up a reusable card, but not here.

```php
...

// Get the transaction response.

$transactionResponse = ResponseFactory::fromHttpResponse($response);

// Get the card. Only cards are supported as Payment Method at this time,
// though that is likely to change when PayPal support is rolled out.

$card = $transactionResponse->getPaymentMethod();

// If it is reusable, then it can be serialised for storage:

if ($card->isReusable()) {
    // Also can use getData() if you want the data without being serialised.
    $serialisedCard = json_encode($card);
}

// In a later payment, the card can be reused:

$card = ReusableCard::fromData(json_decode($serialisedCard));

// Or more explicitly:

$card = new ReusableCard($cardIdentifier);

// Or if being linked to a freshly-entered CVV:

$card = new ReusableCard($merchantSessionKey, $cardIdentifier);
```
