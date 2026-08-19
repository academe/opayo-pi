<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use UnexpectedValueException;
use JsonSerializable;
use Academe\Opayo\Pi\Request\Enums\BrowserColorDepth;
use Academe\Opayo\Pi\Request\Enums\ChallengeWindowSize;
use Academe\Opayo\Pi\Request\Enums\ThreeDSExemptionIndicator;
use Academe\Opayo\Pi\Request\Enums\TransType;

/**
 * Use to provide strong customer authentication details for 3D Secure v2.
 */

class StrongCustomerAuthentication implements JsonSerializable
{
    /**
     * @var string values for challengeWindowSize
     */
    public const CHALLENGE_WINDOW_SIZE_SMALL = 'Small';
    public const CHALLENGE_WINDOW_SIZE_MEDIUM = 'Medium';
    public const CHALLENGE_WINDOW_SIZE_LARGE = 'Large';
    public const CHALLENGE_WINDOW_SIZE_EXTRALARGE = 'ExtraLarge';
    public const CHALLENGE_WINDOW_SIZE_FULLSCREEN = 'FullScreen';

    /**
     * @var string values for transType
     */
    public const TRANS_TYPE_GOODS_AND_SERVICE_PURCHASE = 'GoodsAndServicePurchase';
    public const TRANS_TYPE_CHECK_ACCEPTANCE = 'CheckAcceptance';
    public const TRANS_TYPE_ACCOUNT_FUNDING = 'AccountFunding';
    public const TRANS_TYPE_QUASI_CASH_TRANSACTION = 'QuasiCashTransaction';
    public const TRANS_TYPE_PREPAID_ACTIVATION_AND_LOAD = 'PrepaidActivationAndLoad';

    /**
     * @var string values for browserColorDepth
     */
    public const BROWSER_COLOR_DEPTH_1 = 1;
    public const BROWSER_COLOR_DEPTH_4 = 4;
    public const BROWSER_COLOR_DEPTH_8 = 8;
    public const BROWSER_COLOR_DEPTH_15 = 15;
    public const BROWSER_COLOR_DEPTH_16 = 16;
    public const BROWSER_COLOR_DEPTH_24 = 24;
    public const BROWSER_COLOR_DEPTH_32 = 32;
    public const BROWSER_COLOR_DEPTH_48 = 48;

    protected array $browserColorDepths = [
        self::BROWSER_COLOR_DEPTH_1,
        self::BROWSER_COLOR_DEPTH_4,
        self::BROWSER_COLOR_DEPTH_8,
        self::BROWSER_COLOR_DEPTH_15,
        self::BROWSER_COLOR_DEPTH_16,
        self::BROWSER_COLOR_DEPTH_24,
        self::BROWSER_COLOR_DEPTH_32,
        self::BROWSER_COLOR_DEPTH_48,
    ];

    /**
     * @var there are more undocumented attributes: requestSCAExemption threeDSRequestorDecReqInd threeDSRequestorChallengeInd etc.
     */
    protected ?bool $browserJavaEnabled = null;
    protected ?int $browserColorDepth = null;
    protected ?int $browserScreenHeight = null;
    protected ?int $browserScreenWidth = null;
    protected ?int $browserTz = null; // Time-zone offset in minutes between UTC and the Cardholder browser local time. (really!)
    protected ?object $threeDsRequestorAuthenticationInfo = null; // object threeDSRequestorAuthenticationInfo
    protected ?object $threeDsRequestorPriorAuthenticationInfo = null; // object threeDSRequestorPriorAuthenticationInfo
    protected ?object $acctInfo = null; // object
    protected ?object $merchantRiskIndicator = null; // object
    protected ?string $threeDsExemptionIndicator = null; // threeDSExemptionIndicator
    protected ?string $website = null;
    protected ?string $acctId = null; // acctID

    protected array $mandatoryFields = [
        'notificationUrl',
        'browserIp',
        'browserAcceptHeader',
        'browserJavascriptEnabled',
        'browserLanguage',
        'browserUserAgent',
        'challengeWindowSize',
        'transType',
    ];

    /**
     * @param string $notificationURL URL {,256}
     * @param string $browserIP IPv2 {,15}
     * @param string $browserAcceptHeader {1,2048}
     * @param bool $browserJavascriptEnabled
     * @param string $browserLanguage {1,2} IETF BCP47 navigator.language
     * @param string $browserUserAgent {1,2048}
     * @param string $challengeWindowSize enum "Small" "Medium" "Large" "ExtraLarge" "FullScreen"
     * @param string $transType enum "GoodsAndServicePurchase" "CheckAcceptance" "AccountFunding" "QuasiCashTransaction" "PrepaidActivationAndLoad"
     *
     * @todo add validation
     * @todo set through setters (where the validatino cam live)
     * @todo support "other options" array for non-mandatory options
     * @todo actually a bunch more fields are mandatory, depending on the values
     * of others. For example window size and tz is required if javascript is enabled.
     */
    protected readonly string $challengeWindowSize;
    protected readonly string $transType;

    public function __construct(
        protected readonly string $notificationUrl,
        protected readonly string $browserIp,
        protected readonly string $browserAcceptHeader,
        protected readonly bool $browserJavascriptEnabled,
        protected readonly string $browserLanguage,
        protected readonly string $browserUserAgent,
        string|ChallengeWindowSize $challengeWindowSize,
        string|TransType $transType,
        array $additionalOptions = []
    ) {
        $this->challengeWindowSize = $challengeWindowSize instanceof ChallengeWindowSize
            ? $challengeWindowSize->value : $challengeWindowSize;
        $this->transType = $transType instanceof TransType
            ? $transType->value : $transType;

        foreach ($additionalOptions as $name => $value) {
            $method = 'set' . ucfirst($name);

            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    /**
     * @param bool $browserJavaEnabled
     * @return $this
     */
    protected function setBrowserJavaEnabled(bool $browserJavaEnabled): static
    {
        $this->browserJavaEnabled = $browserJavaEnabled;
        return $this;
    }

    /**
     * @param bool $browserJavaEnabled
     * @return static
     */
    public function withBrowserJavaEnabled(bool $browserJavaEnabled): static
    {
        $clone = clone $this;
        return $clone->setBrowserJavaEnabled($browserJavaEnabled);
    }

    /**
     * The value is stored and serialized as an integer, as the gateway
     * expects. Numeric strings are accepted for backwards compatibility.
     *
     * @param int|string|BrowserColorDepth $browserColorDepth
     * @return $this
     * @throws UnexpectedValueException
     */
    protected function setBrowserColorDepth(int|string|BrowserColorDepth $browserColorDepth): static
    {
        if (! $browserColorDepth instanceof BrowserColorDepth) {
            $browserColorDepth = BrowserColorDepth::tryFrom((int)$browserColorDepth);

            if ($browserColorDepth === null) {
                throw new UnexpectedValueException('Invalid browserColorDepth value');
            }
        }

        $this->browserColorDepth = $browserColorDepth->value;
        return $this;
    }

    /**
     * @param int|string|BrowserColorDepth $browserColorDepth
     * @return static
     * @throws UnexpectedValueException
     */
    public function withBrowserColorDepth(int|string|BrowserColorDepth $browserColorDepth): static
    {
        $clone = clone $this;
        return $clone->setBrowserColorDepth($browserColorDepth);
    }

    /**
     * @param int $browserScreenHeight
     * @return $this
     */
    protected function setBrowserScreenHeight(int $browserScreenHeight): static
    {
        $this->browserScreenHeight = $browserScreenHeight;
        return $this;
    }

    /**
     * @param int $browserScreenHeight
     * @return static
     */
    public function withBrowserScreenHeight(int $browserScreenHeight): static
    {
        $clone = clone $this;
        return $clone->setBrowserScreenHeight($browserScreenHeight);
    }

    /**
     * @param int $browserScreenWidth
     * @return $this
     */
    protected function setBrowserScreenWidth(int $browserScreenWidth): static
    {
        $this->browserScreenWidth = $browserScreenWidth;
        return $this;
    }

    /**
     * @param int $browserScreenWidth
     * @return static
     */
    public function withBrowserScreenWidth(int $browserScreenWidth): static
    {
        $clone = clone $this;
        return $clone->setBrowserScreenWidth($browserScreenWidth);
    }

    /**
     * The reason for bypassing 3D Secure; required by the gateway when
     * apply3DSecure is Disable and the credentialType initiatedType is CIT.
     *
     * @param string|ThreeDSExemptionIndicator $threeDsExemptionIndicator
     * @return $this
     */
    protected function setThreeDsExemptionIndicator(
        string|ThreeDSExemptionIndicator $threeDsExemptionIndicator
    ): static {
        $this->threeDsExemptionIndicator = $threeDsExemptionIndicator instanceof ThreeDSExemptionIndicator
            ? $threeDsExemptionIndicator->value : $threeDsExemptionIndicator;
        return $this;
    }

    /**
     * @param string|ThreeDSExemptionIndicator $threeDsExemptionIndicator
     * @return static
     */
    public function withThreeDsExemptionIndicator(
        string|ThreeDSExemptionIndicator $threeDsExemptionIndicator
    ): static {
        $clone = clone $this;
        return $clone->setThreeDsExemptionIndicator($threeDsExemptionIndicator);
    }

    /**
     * @param int $browserTz
     * @return $this
     */
    protected function setBrowserTz(int $browserTz): static
    {
        $this->browserTz = $browserTz;
        return $this;
    }

    /**
     * @param int $browserTz
     * @return static
     */
    public function withBrowserTz(int $browserTz): static
    {
        $clone = clone $this;
        return $clone->setBrowserTz($browserTz);
    }

    /**
     * @return array The Person returned as an array for the API, requiring conversion to JSON
     */
    public function jsonSerialize(): mixed
    {
        $attributes = [
            'notificationURL' => $this->notificationUrl,
            'browserIP' => $this->browserIp,
            'browserAcceptHeader' => $this->browserAcceptHeader,
            'browserJavascriptEnabled' => $this->browserJavascriptEnabled,
            'browserLanguage' => $this->browserLanguage,
            'browserUserAgent' => $this->browserUserAgent,
            'challengeWindowSize' => $this->challengeWindowSize,
            'transType' => $this->transType,
        ];

        // @todo remaining non-mandatory options

        if ($this->browserJavaEnabled !== null) {
            $attributes['browserJavaEnabled'] = $this->browserJavaEnabled;
        }

        if ($this->browserColorDepth !== null) {
            $attributes['browserColorDepth'] = $this->browserColorDepth;
        }

        if ($this->browserScreenHeight !== null) {
            $attributes['browserScreenHeight'] = $this->browserScreenHeight;
        }

        if ($this->browserScreenWidth !== null) {
            $attributes['browserScreenWidth'] = $this->browserScreenWidth;
        }

        if ($this->browserTz !== null) {
            $attributes['browserTZ'] = $this->browserTz;
        }

        if ($this->threeDsExemptionIndicator !== null) {
            $attributes['threeDSExemptionIndicator'] = $this->threeDsExemptionIndicator;
        }

        return $attributes;
    }
}
