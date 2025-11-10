<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Model;

/**
 * Value object used to hold details about a person.
 * Details include just a first name and last name.
 */

use UnexpectedValueException;

class Person implements PersonInterface
{
    protected string $fieldPrefix = '';

    /**
     * @param string $firstName The first name of the person
     * @param string $lastName The last name of the person
     * @param string|null $email The email address for the person
     * @param string|null $phone The phone number for the person
     */
    public function __construct(
        protected readonly string $firstName,
        protected readonly string $lastName,
        protected readonly ?string $email = null,
        protected readonly ?string $phone = null
    ) {
        // These fields are always mandatory.
        foreach (['firstName', 'lastName'] as $fieldName) {
            if (empty($$fieldName)) {
                throw new UnexpectedValueException(sprintf('Empty field "%s" is mandatory.', $fieldName));
            }
        }
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function jsonSerialize(): mixed
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

    public function getNamesBody(): array
    {
        // Name is mandatory.
        return [
            $this->addFieldPrefix('firstName') => $this->firstName,
            $this->addFieldPrefix('lastName') => $this->lastName,
        ];
    }

    protected function addFieldPrefix(string $field): string
    {
        if (! $this->fieldPrefix) {
            return $field;
        }

        return $this->fieldPrefix . ucfirst($field);
    }

    public function withFieldPrefix(string $fieldPrefix): self
    {
        $copy = clone $this;
        $copy->fieldPrefix = $fieldPrefix;
        return $copy;
    }
}
