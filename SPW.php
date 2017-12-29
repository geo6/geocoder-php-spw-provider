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
use Geocoder\Model\AddressCollection;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Http\Client\HttpClient;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;

/**
 * @author Jonathan Beliën <jbe@geo6.be>
 */
final class SPW extends AbstractHttpProvider implements Provider
{
    /**
     * @var string
     */
    const GEOCODE_ENDPOINT_URL = 'http://geoservices.wallonie.be/geolocalisation/rest/searchPositionScored/%s/';

    /**
     * @var string
     */
    const REVERSE_ENDPOINT_URL = 'http://geoservices.wallonie.be/geolocalisation/rest/getNearestPosition/%F/%F/';

    /**
     * @param HttpClient $client an HTTP adapter
     */
    public function __construct(HttpClient $client)
    {
        parent::__construct($client);
    }

    /**
     * {@inheritdoc}
     */
    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $address = $query->getText();
        // This API does not support IP
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The SPW provider does not support IP addresses, only street addresses.');
        }

        // Save a request if no valid address entered
        if (empty($address)) {
            throw new InvalidArgument('Address cannot be empty.');
        }

        $url = sprintf(self::GEOCODE_ENDPOINT_URL, urlencode($address));
        $json = $this->executeQuery($url);

        // no result
        if (is_null($json->x) && is_null($json->y)) {
            return new AddressCollection([]);
        }

        $results = [];

        $proj4 = new Proj4php();

        $proj31370 = new Proj('EPSG:31370', $proj4);
        $proj4326 = new Proj('EPSG:4326', $proj4);

        $pointSrc = new Point($json->x, $json->y, $proj31370);
        $coordinates = $proj4->transform($proj4326, $pointSrc);

        $streetName = !empty($json->rue->nom) ? $json->rue->nom : null;
        $number = !empty($json->num) ? $json->num : null;
        $municipality = !empty($json->rue->commune) ? $json->rue->commune : null;
        $postCode = !empty($json->rue->cps) ? implode(', ', $json->rue->cps) : null;
        $subLocality = !empty($json->rue->localites) ? implode(', ', $json->rue->localites) : null;
        $countryCode = 'BE';

        $lowerLeftSrc = new Point($json->rue->xMin, $json->rue->yMin, $proj31370);
        $lowerLeft = $proj4->transform($proj4326, $lowerLeftSrc);
        $upperRightSrc = new Point($json->rue->xMax, $json->rue->yMax, $proj31370);
        $upperRight = $proj4->transform($proj4326, $upperRightSrc);

        $bounds = [
          'west'  => $lowerLeft->x,
          'south' => $lowerLeft->y,
          'east'  => $upperRight->x,
          'north' => $upperRight->y,
        ];

        $results[] = Address::createFromArray([
            'providedBy'   => $this->getName(),
            'latitude'     => $coordinates->y,
            'longitude'    => $coordinates->x,
            'streetNumber' => $number,
            'streetName'   => $streetName,
            'locality'     => $municipality,
            'subLocality'  => $subLocality,
            'postalCode'   => $postCode,
            'countryCode'  => $countryCode,
            'bounds'       => $bounds,
        ]);

        return new AddressCollection($results);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseQuery(ReverseQuery $query): Collection
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'spw';
    }

    /**
     * @param string $url
     *
     * @return \stdClass
     */
    private function executeQuery(string $url): \stdClass
    {
        $content = $this->getUrlContents($url);
        $json = json_decode($content);
        // API error
        if (!isset($json)) {
            throw InvalidServerResponse::create($url);
        }

        return $json;
    }
}
