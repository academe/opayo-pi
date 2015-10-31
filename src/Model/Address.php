<?php namespace Academe\SagePayMsg\Model;

/**
 * Value object used to define the customer's billing address.
 * Reasonable validation is done at creation.
 */

use Exception;
use UnexpectedValueException;

use Academe\SagePayMsg\Iso3166\Countries;
use Academe\SagePayMsg\Iso3166\States;
use Academe\SagePayMsg\Helper;

class Address implements AddressInterface
{
    /**
     * @var
     */
    protected $address1;
    protected $address2;
    protected $city;
    protected $postalCode;
    protected $country;
    protected $state;

    protected $fieldPrefix = '';

    /**
     * @param $address1
     * @param $address2
     * @param $city
     * @param $postalCode
     * @param $country
     * @param $state
     */
    public function __construct($address1, $address2, $city, $postalCode, $country, $state)
    {
        // These fields are always mandatory.
        foreach(array('address1', 'city', 'country') as $field_name) {
            if (empty($$field_name)) {
                throw new UnexpectedValueException(sprintf('Field "%s" is mandatory but not set.', $field_name));
            }
        }

        // Validate Country is ISO 3166-1 code.
        if ( ! Countries::isValid($country)) {
            throw new UnexpectedValueException(sprintf('Country code "%s" is not recognised.', (string)$country));
        }

        // State must be set if country is US.
        if ($country == 'US') {
            if (empty($state)) {
                throw new UnexpectedValueException('State must be provided for US country.');
            }

            // Validate State is ISO 3166-2 code.
            if ( ! States::isValid($country, $state)) {
                throw new UnexpectedValueException(sprintf(
                    'State code "%s" for country "%s" is not recognised.', (string)$state, (string)$country
                ));
            }
        }

        // State must not be set if country is not US.
        if ($country != 'US' && ! empty($state)) {
            throw new UnexpectedValueException('State must be left blank for non-US countries.');
        }

        // postCode is optional only if country is IE.
        if ($country != 'IE' && empty($postalCode)) {
            throw new UnexpectedValueException('Postalcode is mandatory for non-IE countries.');
        }

        $this->address1 = $address1;
        $this->address2 = $address2;
        $this->city = $city;
        $this->postalCode = $postalCode;
        $this->country = $country;
        $this->state = $state;
    }

    /**
     * Create a new instance from an array or object of values.
     */
    public static function fromData($data)
    {
        return new static(
            Helper::structureGet($data, 'address1', null),
            Helper::structureGet($data, 'address2', null),
            Helper::structureGet($data, 'city', null),
            Helper::structureGet($data, 'postalCode', null),
            Helper::structureGet($data, 'country', null),
            Helper::structureGet($data, 'state', null)
        );
    }

    /**
     * @param $field
     * @return string
     */
    protected function addFieldPrefix($field)
    {
        if ( ! $this->fieldPrefix) {
            return $field;
        }

        return $this->fieldPrefix . ucfirst($field);
    }

    /**
     * Return the body partial for message construction.
     * Includes all mandatory fields, and optional fields only if not empty.
     * Takes into account the field name prefix, if set.
     */
    public function getBody()
    {
        $return = array(
            $this->addFieldPrefix('address1') => $this->address1,
        );

        if ( ! empty($this->address2)) {
            $return[$this->addFieldPrefix('address2')] = $this->address2;
        }

        $return[$this->addFieldPrefix('city')] = $this->city;

        if ( ! empty($this->postalCode)) {
            $return[$this->addFieldPrefix('postalCode')] = $this->postalCode;
        }

        $return[$this->addFieldPrefix('country')] = $this->country;

        if ( ! empty($this->state)) {
            $return[$this->addFieldPrefix('state')] = $this->state;
        }

        return $return;
    }

    /**
     * Set the field prefix used when returning the object as an array.
     */
    public function withFieldPrefix($fieldPrefix)
    {
        $copy = clone $this;
        $copy->fieldPrefix = $fieldPrefix;
        return $copy;
    }
}
