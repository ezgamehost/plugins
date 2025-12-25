<?php

namespace Boy132\GenericOIDCProviders\Facades;

use Boy132\GenericOIDCProviders\Services\JWKSDiscoveryService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array|null getOpenIDConfiguration(string $baseUrl)
 * @method static string|null getJWKSUri(string $baseUrl)
 * @method static array|null getJWKS(string $baseUrl)
 * @method static array getPublicKeys(string $baseUrl)
 * @method static string|null getPublicKey(string $baseUrl, string $kid)
 * @method static string|null getFirstPublicKey(string $baseUrl)
 * @method static void clearCache(string $baseUrl)
 *
 * @see \Boy132\GenericOIDCProviders\Services\JWKSDiscoveryService
 */
class JWKSDiscovery extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return JWKSDiscoveryService::class;
    }
}
