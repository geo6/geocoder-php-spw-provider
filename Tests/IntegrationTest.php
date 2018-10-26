<?php

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\SPW\Tests;

use Geocoder\IntegrationTest\ProviderIntegrationTest;
use Geocoder\Provider\SPW\SPW;
use Http\Client\HttpClient;

class IntegrationTest extends ProviderIntegrationTest
{
    protected $testAddress = true;

    protected $testReverse = true;

    protected $testIpv4 = false;

    protected $testIpv6 = false;

    protected $skippedTests = [
        'testGeocodeQuery'              => 'SPW provider supports Belgium only.',
        'testReverseQuery'              => 'SPW provider supports Belgium only.',
        'testReverseQueryWithNoResults' => 'SPW provider supports Belgium only.',
        'testExceptions'                => 'SPW provider uses SOAP.',
    ];

    protected function createProvider(HttpClient $httpClient)
    {
        return new SPW($httpClient, 'Geocoder PHP/SPW Provider/Integration Test');
    }

    protected function getCacheDir()
    {
        return __DIR__.'/.cached_responses';
    }

    protected function getApiKey()
    {
    }
}
