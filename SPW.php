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
use SoapClient;

/**
 * @author Jonathan Beliën <jbe@geo6.be>
 */
final class SPW extends AbstractHttpProvider implements Provider
{
    /**
     * @var string
     */
    const WSDL_ENDPOINT_URL = 'http://geoservices.wallonie.be/geolocalisation/soap/?wsdl';

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

        $result = $this->executeQuery('searchPositionScored', ['search' => $address]);

        // no result
        if (!isset($result->x) || !isset($result->y)) {
            return new AddressCollection([]);
        }

        $results = [];

        $proj4 = new Proj4php();

        $proj31370 = new Proj('EPSG:31370', $proj4);
        $proj4326 = new Proj('EPSG:4326', $proj4);

        $pointSrc = new Point($result->x, $result->y, $proj31370);
        $coordinates = $proj4->transform($proj4326, $pointSrc);

        $streetName = !empty($result->rue->nom) ? $result->rue->nom : null;
        $number = !empty($result->num) ? $result->num : null;
        $municipality = !empty($result->rue->commune) ? $result->rue->commune : null;
        $postCode = !empty($result->rue->cps) ? (string)$result->rue->cps : null;
        $subLocality = !empty($result->rue->localites) ? $result->rue->localites : null;
        $countryCode = 'BE';

        $lowerLeftSrc = new Point($result->rue->xMin, $result->rue->yMin, $proj31370);
        $lowerLeft = $proj4->transform($proj4326, $lowerLeftSrc);
        $upperRightSrc = new Point($result->rue->xMax, $result->rue->yMax, $proj31370);
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
        $coordinates = $query->getCoordinates();

        $proj4 = new Proj4php();

        $proj31370 = new Proj('EPSG:31370', $proj4);
        $proj4326 = new Proj('EPSG:4326', $proj4);

        $queryPointSrc = new Point($coordinates->getLongitude(), $coordinates->getLatitude(), $proj4326);
        $queryCoordinates = $proj4->transform($proj31370, $queryPointSrc);

        $result = $this->executeQuery('getNearestPosition', [
            'x' => $queryCoordinates->x,
            'y' => $queryCoordinates->y
        ]);

        $results = [];

        $pointSrc = new Point($result->x, $result->y, $proj31370);
        $coordinates = $proj4->transform($proj4326, $pointSrc);

        $streetName = !empty($result->rue->nom) ? $result->rue->nom : null;
        $number = !empty($result->num) ? $result->num : null;
        $municipality = !empty($result->rue->commune) ? $result->rue->commune : null;
        $postCode = !empty($result->rue->cps) ? (string)$result->rue->cps : null;
        $subLocality = !empty($result->rue->localites) ? $result->rue->localites : null;
        $countryCode = 'BE';

        $lowerLeftSrc = new Point($result->rue->xMin, $result->rue->yMin, $proj31370);
        $lowerLeft = $proj4->transform($proj4326, $lowerLeftSrc);
        $upperRightSrc = new Point($result->rue->xMax, $result->rue->yMax, $proj31370);
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
    public function getName(): string
    {
        return 'spw';
    }

    /**
     * @param string $url
     *
     * @return \stdClass
     */
    private function executeQuery(string $function, array $data): \stdClass
    {
        $client = new SoapClient(self::WSDL_ENDPOINT_URL);
        $result = $client->__soapCall($function, [$data]);

        // API error
        if (!isset($result->return)) {
            throw InvalidServerResponse::create(implode(', ', $data));
        };

        return $result->return;
    }
}
