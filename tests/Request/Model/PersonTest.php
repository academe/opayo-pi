<?php

namespace Academe\Opayo\Pi\Request\Model;

use PHPUnit\Framework\TestCase;

class PersonTest extends TestCase
{
    public function testConstructWithRequiredFields()
    {
        $person = new Person('John', 'Doe');

        $this->assertEquals('John', $person->getFirstName());
        $this->assertEquals('Doe', $person->getLastName());
        $this->assertNull($person->getEmail());
        $this->assertNull($person->getPhone());
    }

    public function testConstructWithAllFields()
    {
        $person = new Person(
            'John',
            'Doe',
            'john.doe@example.com',
            '+44 191 1234567'
        );

        $this->assertEquals('John', $person->getFirstName());
        $this->assertEquals('Doe', $person->getLastName());
        $this->assertEquals('john.doe@example.com', $person->getEmail());
        $this->assertEquals('+44 191 1234567', $person->getPhone());
    }

    public function testMissingFirstNameThrowsException()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Empty field "firstName" is mandatory');

        new Person('', 'Doe');
    }

    public function testMissingLastNameThrowsException()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Empty field "lastName" is mandatory');

        new Person('John', '');
    }

    public function testJsonSerializeWithoutPrefix()
    {
        $person = new Person(
            'John',
            'Doe',
            'john.doe@example.com',
            '+44 191 1234567'
        );

        $expected = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+44 191 1234567',
        ];

        $this->assertEquals($expected, $person->jsonSerialize());
    }

    public function testJsonSerializeWithPrefix()
    {
        $person = new Person('John', 'Doe', 'john.doe@example.com', '+44 191 1234567');
        $personWithPrefix = $person->withFieldPrefix('billing');

        $expected = [
            'billingFirstName' => 'John',
            'billingLastName' => 'Doe',
            'billingEmail' => 'john.doe@example.com',
            'billingPhone' => '+44 191 1234567',
        ];

        $this->assertEquals($expected, $personWithPrefix->jsonSerialize());
    }

    public function testJsonSerializeWithoutOptionalFields()
    {
        $person = new Person('John', 'Doe');

        $expected = [
            'firstName' => 'John',
            'lastName' => 'Doe',
        ];

        $this->assertEquals($expected, $person->jsonSerialize());
    }

    public function testWithFieldPrefixCreatesClone()
    {
        $person = new Person('John', 'Doe');
        $personWithPrefix = $person->withFieldPrefix('billing');

        // Should be different instances
        $this->assertNotSame($person, $personWithPrefix);

        // Original should not have prefix
        $data = $person->jsonSerialize();
        $this->assertArrayHasKey('firstName', $data);

        // Clone should have prefix
        $dataWithPrefix = $personWithPrefix->jsonSerialize();
        $this->assertArrayHasKey('billingFirstName', $dataWithPrefix);
    }
}
