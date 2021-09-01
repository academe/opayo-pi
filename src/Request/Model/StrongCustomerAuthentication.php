<?php namespace Academe\Opayo\Pi\Request\Model;

/**
 * Use to provide strong customer authentication details for 3D Secure v2.
 */

use UnexpectedValueException;
use JsonSerializable;

class StrongCustomerAuthentication implements JsonSerializable
{
    /**
     * @var string values for challengeWindowSize
     */
    const CHALLENGE_WINDOW_SIZE_SMALL = 'Small';
    const CHALLENGE_WINDOW_SIZE_MEDIUM = 'Medium';
    const CHALLENGE_WINDOW_SIZE_LARGE = 'Large';
    const CHALLENGE_WINDOW_SIZE_EXTRALARGE = 'ExtraLarge';
    const CHALLENGE_WINDOW_SIZE_FULLSCREEN = 'FullScreen';

    /**
     * @var string values for transType
     */
    const TRANS_TYPE_GOODS_AND_SERVICE_PURCHASE = 'GoodsAndServicePurchase';
    const TRANS_TYPE_CHECK_ACCEPTANCE = 'CheckAcceptance';
    const TRANS_TYPE_ACCOUNT_FUNDING = 'AccountFunding';
    const TRANS_TYPE_QUASI_CASH_TRANSACTION = 'QuasiCashTransaction';
    const TRANS_TYPE_PREPAID_ACTIVATION_AND_LOAD = 'PrepaidActivationAndLoad';

    /**
     * @var string values for browserColorDepth
     */
    const BROWSER_COLOR_DEPTH_1 = 1;
    const BROWSER_COLOR_DEPTH_4 = 4;
    const BROWSER_COLOR_DEPTH_8 = 8;
    const BROWSER_COLOR_DEPTH_15 = 15;
    const BROWSER_COLOR_DEPTH_16 = 16;
    const BROWSER_COLOR_DEPTH_24 = 24;
    const BROWSER_COLOR_DEPTH_32 = 32;
    const BROWSER_COLOR_DEPTH_48 = 48;

    protected $browserColorDepths = [
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
    protected $notificationUrl; // notificationURL
    protected $browserIp; // browserIP
    protected $browserAcceptHeader;
    protected $browserJavascriptEnabled;
    protected $browserJavaEnabled;
    protected $browserLanguage; // API ref says 1 to 2 chars; example shows "en-GB" (5 chars)
    protected $browserColorDepth;
    protected $browserScreenHeight;
    protected $browserScreenWidth;
    protected $browserTz; // Time-zone offset in minutes between UTC and the Cardholder browser local time. (really!)
    protected $browserUserAgent;
    protected $challengeWindowSize;
    protected $acctId; // acctID
    protected $transType;
    protected $threeDsRequestorAuthenticationInfo; // object threeDSRequestorAuthenticationInfo
    protected $threeDsRequestorPriorAuthenticationInfo; // object threeDSRequestorPriorAuthenticationInfo
    protected $acctInfo; // object
    protected $merchantRiskIndicator; // object
    protected $threeDsExemptionIndicator; // threeDSExemptionIndicator
    protected $website;

    /**
     * @todo actually a bunch more fields are mandatory, depending on the values 
     * of others. For example window size and tz is required if javascript is enabled.
     */

    protected $mandatoryFields = [
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
     * @return void 
     * @throws UnexpectedValueException 
     * 
     * @todo add validation
     * @todo set through setters (where the validatino cam live)
     * @todo support "other options" array for non-mandatory options
     */
    public function __construct(
        string $notificationUrl,
        string $browserIp,
        string $browserAcceptHeader,
        bool $browserJavascriptEnabled,
        string $browserLanguage,
        string $browserUserAgent,
        string $challengeWindowSize,
        string $transType,
        array $additionalOptions = []
    )
    {
        $this->notificationUrl = $notificationUrl;
        $this->browserIp = $browserIp;
        $this->browserAcceptHeader = $browserAcceptHeader;
        $this->browserJavascriptEnabled = $browserJavascriptEnabled;
        $this->browserLanguage = $browserLanguage;
        $this->browserUserAgent = $browserUserAgent;
        $this->challengeWindowSize = $challengeWindowSize;
        $this->transType = $transType;

        foreach ($additionalOptions as $name => $value) {
            $method = 'set' . ucfirst($name);

            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    /**
     * @param boolean $browserJavaEnabled
     * @return void
     */
    protected function setBrowserJavaEnabled(bool $browserJavaEnabled)
    {
        $this->browserJavaEnabled = $browserJavaEnabled;
        return $this;
    }

    /**
     * @param boolean $browserJavaEnabled
     * @return void
     */
    public function withBrowserJavaEnabled(bool $browserJavaEnabled)
    {
        $clone = clone $this;
        return $clone->setBrowserJavaEnabled($browserJavaEnabled);
    } 
    
    /**
     * @param string $browserColorDepth
     * @return void
     * @throws UnexpectedValueException
     */
    protected function setBrowserColorDepth(string $browserColorDepth)
    {
        if (! in_array($browserColorDepth, $this->browserColorDepths)) {
            throw new UnexpectedValueException('Invalid browserColorDepth value');
        }

        $this->browserColorDepth = $browserColorDepth;
        return $this;
    }

    /**
     * @param string $browserColorDepth
     * @return void
     * @throws UnexpectedValueException
     */
    public function withBrowserColorDepth(string $browserColorDepth)
    {
        $clone = clone $this;
        return $clone->setBrowserColorDepth($browserColorDepth);
    } 

    /**
     * @param integer $browserScreenHeight
     * @return void
     */
    protected function setBrowserScreenHeight(int $browserScreenHeight)
    {
        $this->browserScreenHeight = $browserScreenHeight;
        return $this;
    }
    
    /**
     * @param integer $browserScreenHeight
     * @return void
     */
    public function withBrowserScreenHeight(int $browserScreenHeight)
    {
        $clone = clone $this;
        return $clone->setBrowserScreenHeight($browserScreenHeight);
    } 
    
    /**
     * @param integer $browserScreenWidth
     * @return void
     */
    protected function setBrowserScreenWidth(int $browserScreenWidth)
    {
        $this->browserScreenWidth = $browserScreenWidth;
        return $this;
    }

    /**
     * @param integer $browserScreenWidth
     * @return void
     */
    public function withBrowserScreenWidth(int $browserScreenWidth)
    {
        $clone = clone $this;
        return $clone->setBrowserScreenWidth($browserScreenWidth);
    } 
    
    /**
     * @param integer $browserTz
     * @return void
     */
    protected function setBrowserTz(int $browserTz)
    {
        $this->browserTz = $browserTz;
        return $this;
    }

    /**
     * @param integer $browserTz
     * @return void
     */
    public function withBrowserTz(int $browserTz)
    {
        $clone = clone $this;
        return $clone->setBrowserTz($browserTz);
    } 

    /**
     * @return array The Person returned as an array for the API, requiring conversion to JSON
     */
    public function jsonSerialize()
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

        return $attributes;
    }
}
