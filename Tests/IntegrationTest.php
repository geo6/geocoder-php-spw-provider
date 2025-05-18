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
use Psr\Http\Client\ClientInterface;

class IntegrationTest extends ProviderIntegrationTest
{
    protected bool $testAddress = true;

    protected bool $testReverse = true;

    protected bool $testIpv4 = false;

    protected bool $testIpv6 = false;

    protected array $skippedTests = [
        'testGeocodeQuery'              => 'SPW provider supports Belgium only (and does not support "en" locale).',
        'testGeocodeQueryWithNoResults' => 'SPW provider supports Belgium only (and does not support "en" locale).',
        'testReverseQuery'              => 'SPW provider supports Belgium only (and does not support "en" locale).',
        'testReverseQueryWithNoResults' => 'SPW provider supports Belgium only (and does not support "en" locale).',
        'testExceptions'                => '',
    ];

    protected function createProvider(ClientInterface $httpClient)
    {
        return new SPW($httpClient);
    }

    protected function getCacheDir(): string
    {
        return __DIR__.'/.cached_responses';
    }

    protected function getApiKey(): string
    {
        return '';
    }
}
