<?php namespace Academe\SagePayMsg\Models;

/**
 * The 3DSecure response from a Sage Pay transaction.
 * It only includes the status for now, and the acsUrl and paReq
 * are bizarrely not in this object. Maybe we should put those here
 * too, as optional properties, i.e. put ALL 3DSecure data into
 * this one object?
 */

use Exception;
use UnexpectedValueException;

use Academe\SagePayMsg\Helper;

class Secure3D
{
    // FIXME: also need the MD.
    protected $status;
    protected $acsUrl;
    protected $paReq;

    protected $statuses = [
        'authenticated' => 'Authenticated',
        'force' => 'Force',
        'notchecked' => 'NotChecked',
        'notauthenticated' => 'NotAuthenticated',
        'error' => 'Error',
        'cardnotenrolled' => 'CardNotEnrolled',
        'issuernotenrolled' => 'IssuerNotEnrolled',
    ];

    public function __construct($status, $acsUrl = null, $paReq = null)
    {
        $this->status = $status;
        $this->acsUrl = $acsUrl;
        $this->paReq = $paReq;
    }

    /**
     * Create a new instance from an array or object of values.
     * The data will normally be the whole transaction response with various items
     * of data at different levels, or a flat array.
     * This is possibly misleading, because if there is no 3DSecure data returned
     * at all in the response, then the overall transaction status will be picked
     * up here.
     */
    public static function fromData($data)
    {
        return new static(
            Helper::structureGet($data, '3DSecure.status', Helper::structureGet($data, 'status', null)),
            Helper::structureGet($data, 'acsUrl', null),
            Helper::structureGet($data, 'paReq', null)
        );
    }

    /**
     * The status of the 3DSecure result.
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * The 3DSecure ACS URL, to send users to.
     */
    public function getAcsUrl()
    {
        return $this->acsUrl;
    }

    /**
     * The 3DSecure PA REQ, the token to send along to the ACS URL.
     */
    public function getPaReq()
    {
        return $this->paReq;
    }

    /**
     * Get the fields (names and values) to go into the paReq POST.
     * TODO: 'MD' needs to be supported once the API supports it.
     * $termUrl is the return URL after the PA Request is complete.
     */
    public function getPaRequestFields($termUrl = null)
    {
        $fields = [
            'paReq' => $this->getPaReq(),
            'md' => '',
        ];

        if (isset($termUrl)) {
            $fields['TermUrl'] = $termUrl;
        }

        return $fields;
    }
}