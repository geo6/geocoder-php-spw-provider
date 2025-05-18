<?php

declare(strict_types=1);

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\SPW;

use Geocoder\Collection;
use Geocoder\Exception\InvalidArgument;
use Geocoder\Exception\InvalidServerResponse;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Http\Provider\AbstractHttpProvider;
use Geocoder\Model\Address;
use Geocoder\Model\AddressBuilder;
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Psr\Http\Client\ClientInterface;

/**
 * @author Jonathan Beliën <jbe@geo6.be>
 */
final class SPW extends AbstractHttpProvider implements Provider
{
    /**
     * @var string
     */
    const ENDPOINT_URL = 'https://geoservices.wallonie.be/geocodeWS';

    /**
     * @param ClientInterface $client an HTTP adapter
     */
    public function __construct(ClientInterface $client)
    {
        parent::__construct($client);
    }

    /**
     * {@inheritdoc}
     */
    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $address = $query->getText();
        $locale = $query->getLocale();

        if (filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The SPW provider does not support IP addresses, only street addresses.');
        }

        if (empty($address)) {
            throw new InvalidArgument('Address cannot be empty.');
        }

        if (!empty($locale) && !in_array($locale, ['fr', 'nl', 'de'])) {
            throw new InvalidArgument('Locale must be one of "fr", "nl", or "de".');
        }

        $url = self::ENDPOINT_URL.'/geocode?'.http_build_query([
            'address' => $address,
            'bbox' => true,
            'geom' => true,
            'crs' => 'EPSG:4326',
            'lang' => $query->getLocale(),
        ]);

        $response = $this->getUrlContents($url);
        $json = json_decode($response, true);
        if (is_null($json) || !is_array($json)) {
            throw InvalidServerResponse::create($url);
        }

        if (count($json['candidates']) === 0) {
            return new AddressCollection();
        }

        $results = [];
        foreach ($json['candidates'] as $candidate) {
            $results[] = $this->createAddress($candidate);
        }

        return new AddressCollection($results);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseQuery(ReverseQuery $query): Collection
    {
        $coordinates = $query->getCoordinates();

        if (!empty($locale) && !in_array($locale, ['fr', 'nl', 'de'])) {
            throw new InvalidArgument('Locale must be one of "fr", "nl", or "de".');
        }

        $url = self::ENDPOINT_URL.'/revgeocode?'.http_build_query([
            'x' => $coordinates->getLongitude(),
            'y' => $coordinates->getLatitude(),
            'bbox' => true,
            'geom' => true,
            'crs' => 'EPSG:4326',
            'lang' => $query->getLocale(),
        ]);

        $response = $this->getUrlContents($url);
        $json = json_decode($response, true);
        if (is_null($json) || !is_array($json)) {
            throw InvalidServerResponse::create($url);
        }

        if (count($json['candidates']) === 0) {
            return new AddressCollection();
        }

        $results = [];
        foreach ($json['candidates'] as $candidate) {
            $results[] = $this->createAddress($candidate);
        }

        return new AddressCollection($results);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'spw';
    }

    private function createAddress(array $candidate): Address
    {
        $builder = new AddressBuilder($this->getName());

        $city = $candidate['city'];
        $zone = $candidate['zone'];
        $street = $candidate['street'];
        $house = $candidate['house'];

        if (isset($city)) {
            $builder->setLocality($city['name']);
        }

        if (isset($zone)) {
            $builder->setPostalCode($zone['ident']);
            $builder->setSubLocality($zone['name']);
        }

        if (isset($street)) {
            $builder->setStreetName($street['name']);
        }

        if (isset($house)) {
            $builder->setStreetNumber($house['name']);

            if (isset($house['geometry'])) {
                $builder->setCoordinates($house['geometry']['coordinates'][1], $house['geometry']['coordinates'][0]);
            }

            if (isset($house['bbox'])) {
                $builder->setBounds($house['bbox'][1],$house['bbox'][0],$house['bbox'][3],$house['bbox'][2]);
            }
        }

        return $builder->build();
    }    
}