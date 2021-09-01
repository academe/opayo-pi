<?php

namespace Academe\Opayo\Pi\Request\Model;

//use PHPUnit\Framework\TestCase;

class AddressTest extends \PHPUnit_Framework_TestCase
{
    protected $simpleGB = [
        'address1' => 'Address1',
        'address2' => 'Address2',
        'city' => 'City',
        'postalCode' => 'NE26 2SB',
        'country' => 'GB',
    ];

    protected $simpleUS = [
        'address1' => 'Address1',
        'address2' => 'Address2',
        'city' => 'City',
        'postalCode' => 'NE26 2SB',
        'country' => 'US',
        'state' => 'AL',
    ];

    public function testSimpleValidUS()
    {
        $address = new Address(
            $this->simpleUS['address1'],
            $this->simpleUS['address2'],
            $this->simpleUS['city'],
            $this->simpleUS['postalCode'],
            $this->simpleUS['country'],
            $this->simpleUS['state']
        );

        // A simple address with no prefix.
        $this->assertEquals(
            json_encode($address),
            '{"address1":"Address1","address2":"Address2","city":"City","postalCode":"NE26 2SB","country":"US","state":"AL"}'
        );

        $address = $address->withFieldPrefix('prefix');

        // A simple address with a field name prefix.
        $this->assertEquals(
            json_encode($address),
            '{"prefixAddress1":"Address1","prefixAddress2":"Address2","prefixCity":"City","prefixPostalCode":"NE26 2SB","prefixCountry":"US","prefixState":"AL"}'
        );
    }

    public function testSimpleValidGB()
    {
        $address = new Address(
            $this->simpleGB['address1'],
            $this->simpleGB['address2'],
            $this->simpleGB['city'],
            $this->simpleGB['postalCode'],
            $this->simpleGB['country']
        );

        // A simple address with no prefix.
        $this->assertEquals(
            json_encode($address),
            '{"address1":"Address1","address2":"Address2","city":"City","postalCode":"NE26 2SB","country":"GB"}'
        );

        $address = $address->withFieldPrefix('prefix');

        // A simple address with a field name prefix.
        $this->assertEquals(
            json_encode($address),
            '{"prefixAddress1":"Address1","prefixAddress2":"Address2","prefixCity":"City","prefixPostalCode":"NE26 2SB","prefixCountry":"GB"}'
        );
    }

    public function testSimpleValidFromDataGB()
    {
        $address = Address::fromData($this->simpleGB);

        // A simple address with no prefix.
        $this->assertEquals(
            json_encode($address),
            '{"address1":"Address1","address2":"Address2","city":"City","postalCode":"NE26 2SB","country":"GB"}'
        );
    }

    public function testSimpleValidFromDataUS()
    {
        $address = Address::fromData($this->simpleUS);

        // A simple address with no prefix.
        $this->assertEquals(
            json_encode($address),
            '{"address1":"Address1","address2":"Address2","city":"City","postalCode":"NE26 2SB","country":"US","state":"AL"}'
        );
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testMissingAddress1()
    {
        $data = $this->simpleUS;
        unset($data['address1']);
        $address = Address::fromData($data);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testMissingCity()
    {
        $data = $this->simpleUS;
        unset($data['city']);
        $address = Address::fromData($data);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testMissingCountry()
    {
        $data = $this->simpleUS;
        unset($data['country']);
        $address = Address::fromData($data);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testMissingState()
    {
        $data = $this->simpleUS;
        unset($data['state']);
        $address = Address::fromData($data);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testInvalidState()
    {
        $data = $this->simpleUS;
        $data['state'] = 'XX';
        $address = Address::fromData($data);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testInvalidCountry()
    {
        $data = $this->simpleUS;
        $data['country'] = 'XX';
        $address = Address::fromData($data);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testUnexpectedState()
    {
        $data = $this->simpleGB;
        $data['state'] = 'AL';
        $address = Address::fromData($data);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testMissingPostalCode()
    {
        $data = $this->simpleUS;
        unset($data['postalCode']);
        $address = Address::fromData($data);
    }

    /**
     * Postal code is optional for IE.
     */
    public function testMissingPostalCodeIE()
    {
        $data = $this->simpleGB;
        $data['country'] = 'IE';

        // Valid with a postalCode (no exceptions).
        $address1 = Address::fromData($data);

        // Valid without a postalCode (no exceptions).
        unset($data['postalCode']);
        $address2 = Address::fromData($data);
    }
}
