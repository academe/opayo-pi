<?php

namespace Academe\Opayo\Pi\Response;

/**
 * Response to a Payment (and probably Authorise) requent when a
 * 3D Secure v2 redirect (a "challenge") is needed.
 */

use Academe\Opayo\Pi\Helper;

class Secure3Dv2Redirect extends AbstractTransaction
{
    /**
     * Directory Server (DS) transaction ID. This is a unique ID provided
     * by the card scheme for 3DSv2 authentications.
     *
     * @var string
     */
    protected $dsTranId;

    /**
     * A fully qualified URL that points to the 3-D Secure authentication
     * system at the card holder's issuing bank
     *
     * @var string
     */
    protected $acsUrl;

    /**
     * @var string Remember to post to the ACS as "creq"
     */
    protected $cReq;

    /**
     * @param $data
     * @return $this
     */
    protected function setData($data)
    {
        $this->setStatuses($data);

        $this->transactionId = Helper::dataGet($data, 'transactionId', null);

        $this->dsTranId = Helper::dataGet($data, 'dsTranId', null);
        $this->acsUrl = Helper::dataGet($data, 'acsUrl', null);
        $this->cReq = Helper::dataGet($data, 'cReq', null);

        return $this;
    }

    public function getDsTranId()
    {
        return $this->dsTranId;
    }

    /**
     * The ACS URL to send the user to.
     *
     * @return string
     */
    public function getAcsUrl()
    {
        return $this->acsUrl;
    }

    /**
     * To be posted to the acsUrl as "creq".
     * Unlike 3DS v1, this is the only attribute posted, since the
     * notification URL has already been declared in the initial
     * transaction request SCA object.
     *
     * @return string
     */
    public function getCReq()
    {
        return $this->cReq;
    }

    /**
     * The fields to be posted.
     * This function is named for compatibility with the Secure3DRedirect
     * response for convenience.
     *
     * @param array $additionalSessionData to send to be ACS, which will be returned
     * @return array
     */
    public function getPaRequestFields($threeDSSessionData = null)
    {
        $data = [
            'creq' => $this->getCReq(),
        ];

        if (is_string($threeDSSessionData)) {
            $data['threeDSSessionData'] = $threeDSSessionData;
        }

        return $data;
    }

    /**
     * Convenient serialisation for logging and debugging.
     * 
     * @return array
     */
    public function jsonSerialize()
    {
        $return = parent::jsonSerialize();

        $return['transactionId'] = $this->transactionId;
        $return['acsUrl'] = $this->acsUrl;
        $return['cReq'] = $this->cReq;
        $return['dsTranId'] = $this->dsTranId;

        return $return;
    }

    /**
     * @inheritdoc This is a 3D Secure redirect.
     */
    public function isRedirect()
    {
        return true;
    }
}
