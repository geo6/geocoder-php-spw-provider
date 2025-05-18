<?php

declare(strict_types=1);

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\SPW\Tests;

use Geocoder\IntegrationTest\BaseTestCase;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\SPW\SPW;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

class SPWTest extends BaseTestCase
{
    protected function getCacheDir()
    {
        return __DIR__.'/.cached_responses';
    }

    public function testGeocodeWithLocalhostIPv4()
    {
        $this->expectException(\Geocoder\Exception\UnsupportedOperation::class);
        $this->expectExceptionMessage('The SPW provider does not support IP addresses, only street addresses.');

        $provider = new SPW($this->getMockedHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('127.0.0.1'));
    }

    public function testGeocodeWithLocalhostIPv6()
    {
        $this->expectException(\Geocoder\Exception\UnsupportedOperation::class);
        $this->expectExceptionMessage('The SPW provider does not support IP addresses, only street addresses.');

        $provider = new SPW($this->getMockedHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('::1'));
    }

    public function testGeocodeWithRealIPv6()
    {
        $this->expectException(\Geocoder\Exception\UnsupportedOperation::class);
        $this->expectExceptionMessage('The SPW provider does not support IP addresses, only street addresses.');

        $provider = new SPW($this->getMockedHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('::ffff:88.188.221.14'));
    }

    public function testHouseReverseQuery()
    {
        $provider = new SPW($this->getHttpClient());
        $results = $provider->reverseQuery(ReverseQuery::fromCoordinates(50.461370, 4.840830));

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertNotEmpty($results);

        /** @var \Geocoder\Model\Address $result */
        $result = $results->first();
        $this->assertInstanceOf(Address::class, $result);
        $this->assertEquals('83', $result->getStreetNumber());
        $this->assertEquals('Chaussée de Charleroi', $result->getStreetName());
        $this->assertEquals('5000', $result->getPostalCode());
        $this->assertEquals('Namur', $result->getLocality());
        $this->assertEquals('Namur', $result->getSubLocality());
    }

    public function testHouseGeocodeQuery()
    {
        $provider = new SPW($this->getHttpClient());
        $results = $provider->geocodeQuery(GeocodeQuery::create('Chaussée de Charleroi 83 5000 Namur'));

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertNotEmpty($results);

        /** @var \Geocoder\Model\Address $result */
        $result = $results->first();
        $this->assertInstanceOf(Address::class, $result);
        $this->assertEqualsWithDelta(50.461370, $result->getCoordinates()->getLatitude(), 0.00001);
        $this->assertEqualsWithDelta(4.840830, $result->getCoordinates()->getLongitude(), 0.00001);
        $this->assertEquals('83', $result->getStreetNumber());
        $this->assertEquals('Chaussée de Charleroi', $result->getStreetName());
        $this->assertEquals('5000', $result->getPostalCode());
        $this->assertEquals('Namur', $result->getSubLocality());
        $this->assertEquals('Namur', $result->getLocality());
    }

    public function testStreetGeocodeQuery()
    {
        $provider = new SPW($this->getHttpClient());
        $results = $provider->geocodeQuery(GeocodeQuery::create('Chaussée de Charleroi, Namur'));

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertNotEmpty($results);

        /** @var \Geocoder\Model\Address $result */
        $result = $results->first();
        $this->assertInstanceOf(Address::class, $result);
        $this->assertEqualsWithDelta(50.449540, $result->getCoordinates()->getLatitude(), 0.00001);
        $this->assertEqualsWithDelta(4.818282, $result->getCoordinates()->getLongitude(), 0.00001);
        $this->assertEquals('Chaussée de Charleroi', $result->getStreetName());
        $this->assertEquals('Namur', $result->getLocality());
    }

    public function testCityGeocodeQuery()
    {
        $provider = new SPW($this->getHttpClient());
        $results = $provider->geocodeQuery(GeocodeQuery::create('Namur'));

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertNotEmpty($results);

        /** @var \Geocoder\Model\Address $result */
        $result = $results->first();
        $this->assertInstanceOf(Address::class, $result);
        $this->assertEqualsWithDelta(50.466390, $result->getCoordinates()->getLatitude(), 0.00001);
        $this->assertEqualsWithDelta(4.866114, $result->getCoordinates()->getLongitude(), 0.00001);
        $this->assertEquals('Namur', $result->getLocality());
    }

    public function testGeocodeLocaleException()
    {
        $this->expectException(\Geocoder\Exception\InvalidArgument::class);
        $this->expectExceptionMessage('Locale must be one of "fr", "nl", or "de".');

        $provider = new SPW($this->getMockedHttpClient());
        $provider->geocodeQuery(GeocodeQuery::create('Chaussée de Charleroi 83 5000 Namur')->withLocale('en'));
    }

    public function testReverseLocaleException()
    {
        $this->expectException(\Geocoder\Exception\InvalidArgument::class);
        $this->expectExceptionMessage('Locale must be one of "fr", "nl", or "de".');

        $provider = new SPW($this->getMockedHttpClient());
        $provider->reverseQuery(ReverseQuery::fromCoordinates(50.461370, 4.840830)->withLocale('en'));
    }

    public function testGeocodeQueryWithNoResults()
    {
        $provider = new SPW($this->getHttpClient());
        $results = $provider->geocodeQuery(GeocodeQuery::create('jsajhgsdkfjhsfkjhaldkadjaslgldasd'));

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertEmpty($results);
    }

    public function testReverseQueryWithNoResults()
    {
        $provider = new SPW($this->getHttpClient());
        $results = $provider->reverseQuery(ReverseQuery::fromCoordinates(0, 0));

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertEmpty($results);
    }
}
