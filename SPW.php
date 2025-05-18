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

        if (!empty($locale) && !in_array($locale, ['fr', 'nl', 'de'])) {
            throw new InvalidArgument('Locale must be one of "fr", "nl", or "de".');
        }

        $url = self::ENDPOINT_URL.'/geocode?'.http_build_query([
            'address' => $address,
            'bbox'    => true,
            'geom'    => true,
            'crs'     => 'EPSG:4326',
            'lang'    => $query->getLocale(),
        ]);

        $response = $this->getUrlContents($url);
        $json = json_decode($response, true);
        if (is_null($json) || !is_array($json)) {
            throw InvalidServerResponse::create($url);
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
        $locale = $query->getLocale();

        if (!empty($locale) && !in_array($locale, ['fr', 'nl', 'de'])) {
            throw new InvalidArgument('Locale must be one of "fr", "nl", or "de".');
        }

        $url = self::ENDPOINT_URL.'/revgeocode?'.http_build_query([
            'x'    => $coordinates->getLongitude(),
            'y'    => $coordinates->getLatitude(),
            'bbox' => true,
            'geom' => true,
            'crs'  => 'EPSG:4326',
            'lang' => $query->getLocale(),
        ]);

        $response = $this->getUrlContents($url);
        $json = json_decode($response, true);
        if (is_null($json) || !is_array($json)) {
            throw InvalidServerResponse::create($url);
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

        if (isset($candidate['city'])) {
            $builder->setLocality($candidate['city']['name']);

            if (isset($candidate['city']['geometry'])) {
                $builder->setCoordinates($candidate['city']['geometry']['coordinates'][1], $candidate['city']['geometry']['coordinates'][0]);
            }

            if (isset($candidate['city']['bbox'])) {
                $builder->setBounds($candidate['city']['bbox'][1], $candidate['city']['bbox'][0], $candidate['city']['bbox'][3], $candidate['city']['bbox'][2]);
            }
        }

        if (isset($candidate['zone'])) {
            $builder->setPostalCode($candidate['zone']['ident']);
            $builder->setSubLocality($candidate['zone']['name']);
        }

        if (isset($candidate['street'])) {
            $builder->setStreetName($candidate['street']['name']);

            if (isset($candidate['street']['geometry'])) {
                $builder->setCoordinates($candidate['street']['geometry']['coordinates'][1], $candidate['street']['geometry']['coordinates'][0]);
            }

            if (isset($candidate['street']['bbox'])) {
                $builder->setBounds($candidate['street']['bbox'][1], $candidate['street']['bbox'][0], $candidate['street']['bbox'][3], $candidate['street']['bbox'][2]);
            }
        }

        if (isset($candidate['house'])) {
            $builder->setStreetNumber($candidate['house']['name']);

            if (isset($candidate['house']['geometry'])) {
                $builder->setCoordinates($candidate['house']['geometry']['coordinates'][1], $candidate['house']['geometry']['coordinates'][0]);
            }

            if (isset($candidate['house']['bbox'])) {
                $builder->setBounds($candidate['house']['bbox'][1], $candidate['house']['bbox'][0], $candidate['house']['bbox'][3], $candidate['house']['bbox'][2]);
            }
        }

        return $builder->build();
    }
}
