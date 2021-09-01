<?php namespace Academe\Opayo\Pi\Request\Model;

/**
 * Value object used to hold details about a person.
 * Details include just a first name and last name.
 */

use UnexpectedValueException;

class Person implements PersonInterface
{
    /**
     * @var
     */
    protected $firstName;
    protected $lastName;
    protected $email;
    protected $phone;

    /**
     * @var string The current field prefix
     */
    protected $fieldPrefix = '';

    /**
     * @param string $firstName The first name of the person
     * @param string $lastName The last name of the person
     * @param string|null $email The email address for the person
     * @param string|null $phone The phone number for the person
     */
    public function __construct($firstName, $lastName, $email = null, $phone = null)
    {
        // These fields are always mandatory.
        foreach (['firstName', 'lastName'] as $field_name) {
            if (empty($$field_name)) {
                throw new UnexpectedValueException(sprintf('Empty field "%s" is mandatory.', $field_name));
            }
        }

        $this->firstName = $firstName;
        $this->lastName = $lastName;

        $this->email = $email;
        $this->phone = $phone;
    }

    /**
     * @return string The first name for the person
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @return string The last name for the person
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @return string The email address for the person
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return string The phone number for the person
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @return array The Person returned as an array for the API, requiring conversion to JSON
     */
    public function jsonSerialize()
    {
        // First/last name is always required.
        $return = $this->getNamesBody();

        // Email and phone is optional.
        if (isset($this->email)) {
            $return[$this->addFieldPrefix('email')] = $this->email;
        }

        if (isset($this->phone)) {
            $return[$this->addFieldPrefix('phone')] = $this->phone;
        }

        return $return;
    }

    /**
     * @return array
     */
    public function getNamesBody()
    {
        // Name is mandatory.
        return [
            $this->addFieldPrefix('firstName') => $this->firstName,
            $this->addFieldPrefix('lastName') => $this->lastName,
        ];
    }

    /**
     * @param string $field The field name without a prefix
     *
     * @return string The field name with the current prefix added and camel capitalisation
     */
    protected function addFieldPrefix($field)
    {
        if (! $this->fieldPrefix) {
            return $field;
        }

        return $this->fieldPrefix . ucfirst($field);
    }

    /**
     * @param string $fieldPrefix The field prefix used when returning the object as an array
     *
     * @return Person Clone of $this with the prefix set.
     */
    public function withFieldPrefix($fieldPrefix)
    {
        $copy = clone $this;
        $copy->fieldPrefix = $fieldPrefix;
        return $copy;
    }
}
