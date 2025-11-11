<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

use UnexpectedValueException;
use Academe\Opayo\Pi\Iso3166\Countries;
use Academe\Opayo\Pi\Iso3166\States;
use Academe\Opayo\Pi\Helper;

/**
 * Value object used to define the customer's billing address.
 * Reasonable validation is done at creation.
 */

class Address implements AddressInterface
{
    protected string $address1;
    protected ?string $address2;
    protected string $city;
    protected ?string $postalCode;
    protected string $country;
    protected ?string $state;

    protected string $fieldPrefix = '';

    /**
     * @param string|null $address1 Address line 1
     * @param string|null $address2 Address line 2
     * @param string|null $city The name of the city or town
     * @param string|null $postalCode The postal code
     * @param string|null $country The country ISO 3166-2 two-letter code
     * @param string|null $state The last two letters of the ISO 3166-2:US state code
     */
    public function __construct(
        ?string $address1,
        ?string $address2,
        ?string $city,
        ?string $postalCode,
        ?string $country,
        ?string $state = null
    ) {
        // These fields are always mandatory.
        foreach (array('address1', 'city', 'country') as $field_name) {
            if (empty($$field_name)) {
                throw new UnexpectedValueException(sprintf('Field "%s" is mandatory but not set.', $field_name));
            }
        }

        // Validate Country is ISO 3166-1 code.
        if (! Countries::isValid($country)) {
            throw new UnexpectedValueException(sprintf('Country code "%s" is not recognised.', (string)$country));
        }

        // State must be set if country is US.
        if ($country == 'US') {
            if (empty($state)) {
                throw new UnexpectedValueException('State must be provided for US country.');
            }

            // Validate State is ISO 3166-2 code.
            if (! States::isValid($country, $state)) {
                throw new UnexpectedValueException(sprintf(
                    'State code "%s" for country "%s" is not recognised.',
                    (string)$state,
                    (string)$country
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
     *
     * @param array|object $data Address data using fields or elements for intialisation
     *
     * @return static New address object set up from the data
     */
    public static function fromData(array|object $data): static
    {
        return new static(
            Helper::dataGet($data, 'address1', null),
            Helper::dataGet($data, 'address2', null),
            Helper::dataGet($data, 'city', null),
            Helper::dataGet($data, 'postalCode', null),
            Helper::dataGet($data, 'country', null),
            Helper::dataGet($data, 'state', null)
        );
    }

    /**
     * Add the current prefix to a field name. Some addresses in the Sage Pay API have
     * prefixes depending on context. For example, one object may use field "city" and
     * another object may prefix it to "recipientCity", with camel capitalisation.
     *
     * @param string $field The name of a data field without the current prefix
     *
     * @return string The name of the data field with the current prefix added, if a prefix is set
     */
    protected function addFieldPrefix(string $field): string
    {
        if (! $this->fieldPrefix) {
            return $field;
        }

        return $this->fieldPrefix . ucfirst($field);
    }

    /**
     * Return the body partial for message construction.
     * Includes all mandatory fields, and optional fields only if not empty.
     * Takes into account the field name prefix, if set.
     *
     * @return array Data for passing to the API, requiring JSON conversion first.
     */
    public function jsonSerialize(): mixed
    {
        $return = [
            $this->addFieldPrefix('address1') => $this->address1,
        ];

        if (! empty($this->address2)) {
            $return[$this->addFieldPrefix('address2')] = $this->address2;
        }

        $return[$this->addFieldPrefix('city')] = $this->city;

        if (! empty($this->postalCode)) {
            $return[$this->addFieldPrefix('postalCode')] = $this->postalCode;
        }

        $return[$this->addFieldPrefix('country')] = $this->country;

        if (! empty($this->state)) {
            $return[$this->addFieldPrefix('state')] = $this->state;
        }

        return $return;
    }

    /**
     * Set the field prefix used when returning the object as an array.
     *
     * @param string $fieldPrefix The prefix to be added to all fields, normally lower-case
     *
     * @return self Clone of $this with the prefix set.
     */
    public function withFieldPrefix(string $fieldPrefix): self
    {
        $copy = clone $this;
        $copy->fieldPrefix = $fieldPrefix;
        return $copy;
    }
}
