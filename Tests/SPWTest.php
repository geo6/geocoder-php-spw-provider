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
use Geocoder\Provider\SPW\SPW;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;

class SPWTest extends BaseTestCase
{
    protected function getCacheDir()
    {
        return __DIR__.'/.cached_responses';
    }

    /**
     * @expectedException \Geocoder\Exception\UnsupportedOperation
     * @expectedExceptionMessage The SPW provider does not support IP addresses, only street addresses.
     */
    public function testGeocodeWithLocalhostIPv4()
    {
        $provider = new SPW($this->getMockedHttpClient(), 'Geocoder PHP/SPW Provider/SPW Test');
        $provider->geocodeQuery(GeocodeQuery::create('127.0.0.1'));
    }

    /**
     * @expectedException \Geocoder\Exception\UnsupportedOperation
     * @expectedExceptionMessage The SPW provider does not support IP addresses, only street addresses.
     */
    public function testGeocodeWithLocalhostIPv6()
    {
        $provider = new SPW($this->getMockedHttpClient(), 'Geocoder PHP/SPW Provider/SPW Test');
        $provider->geocodeQuery(GeocodeQuery::create('::1'));
    }

    /**
     * @expectedException \Geocoder\Exception\UnsupportedOperation
     * @expectedExceptionMessage The SPW provider does not support IP addresses, only street addresses.
     */
    public function testGeocodeWithRealIPv6()
    {
        $provider = new SPW($this->getMockedHttpClient(), 'Geocoder PHP/SPW Provider/SPW Test');
        $provider->geocodeQuery(GeocodeQuery::create('::ffff:88.188.221.14'));
    }

    public function testReverseQuery()
    {
        $provider = new SPW($this->getHttpClient(), 'Geocoder PHP/SPW Provider/SPW Test');
        $results = $provider->reverseQuery(ReverseQuery::fromCoordinates(50.46144106856357, 4.839749533657067));

        $this->assertInstanceOf('Geocoder\Model\AddressCollection', $results);
        $this->assertCount(1, $results);

        /** @var \Geocoder\Model\Address $result */
        $result = $results->first();
        $this->assertInstanceOf('\Geocoder\Model\Address', $result);
        $this->assertEquals('83', $result->getStreetNumber());
        $this->assertEquals('CHAUSSÉE DE CHARLEROI', $result->getStreetName());
        $this->assertEquals('5000', $result->getPostalCode());
        $this->assertEquals('NAMUR', $result->getLocality());
        $this->assertEquals('NAMUR', $result->getSubLocality());
    }

    public function testGeocodeQuery()
    {
        $provider = new SPW($this->getHttpClient(), 'Geocoder PHP/SPW Provider/SPW Test');
        $results = $provider->geocodeQuery(GeocodeQuery::create('CHAUSSÉE DE CHARLEROI 83 5000 NAMUR'));

        $this->assertInstanceOf('Geocoder\Model\AddressCollection', $results);
        $this->assertCount(1, $results);

        /** @var \Geocoder\Model\Address $result */
        $result = $results->first();
        $this->assertInstanceOf('\Geocoder\Model\Address', $result);
        $this->assertEquals(50.46144106856357, $result->getCoordinates()->getLatitude(), '', 0.00001);
        $this->assertEquals(4.839749533657067, $result->getCoordinates()->getLongitude(), '', 0.00001);
        $this->assertEquals('83', $result->getStreetNumber());
        $this->assertEquals('CHAUSSÉE DE CHARLEROI', $result->getStreetName());
        $this->assertEquals('5000', $result->getPostalCode());
        $this->assertEquals('NAMUR', $result->getLocality());
        $this->assertEquals('NAMUR', $result->getSubLocality());
    }
}
