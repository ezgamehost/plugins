<?php

namespace Boy132\GenericOIDCProviders\Services;

use Exception;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenSSLAsymmetricKey;
use Throwable;

class JWKSDiscoveryService
{
    /**
     * Cache duration for OpenID Configuration in minutes.
     */
    protected int $configCacheDuration = 60;

    /**
     * Cache duration for JWKS in minutes.
     */
    protected int $jwksCacheDuration = 60;

    /**
     * Cache key version, used to avoid legacy cached nulls.
     */
    protected string $cacheKeyVersion = 'v2';

    /**
     * Short backoff when remote fetch fails, in seconds.
     *
     * Prevents request storms during brief outages while avoiding long auth outages.
     */
    protected int $failureBackoffSeconds = 30;

    /**
     * Get the OpenID Configuration from the provider.
     *
     * @return array<string, mixed>|null
     */
    public function getOpenIDConfiguration(string $baseUrl): ?array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $configUrl = $baseUrl . '/.well-known/openid-configuration';
        $cacheKey = $this->getOpenIdConfigCacheKey($configUrl);
        $failureKey = $this->getFailureBackoffCacheKey($cacheKey);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        if (Cache::get($failureKey) === true) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->throw()
                ->get($configUrl);

            $json = $response->json();
            if (!is_array($json)) {
                Log::error('OpenID Configuration response was not JSON object/array', [
                    'url' => $configUrl,
                ]);

                Cache::put($failureKey, true, now()->addSeconds($this->failureBackoffSeconds));
                return null;
            }

            Cache::put($cacheKey, $json, now()->addMinutes($this->configCacheDuration));
            Cache::forget($failureKey);

            return $json;
        } catch (Exception $e) {
            Log::error('Failed to fetch OpenID Configuration', [
                'url' => $configUrl,
                'error' => $e->getMessage(),
            ]);

            Cache::put($failureKey, true, now()->addSeconds($this->failureBackoffSeconds));
            return null;
        }
    }

    /**
     * Get the JWKS URI from the OpenID Configuration.
     */
    public function getJWKSUri(string $baseUrl): ?string
    {
        $config = $this->getOpenIDConfiguration($baseUrl);

        return $config['jwks_uri'] ?? null;
    }

    /**
     * Fetch the JWKS from the provider.
     *
     * @return array<string, mixed>|null
     */
    public function getJWKS(string $baseUrl): ?array
    {
        $jwksUri = $this->getJWKSUri($baseUrl);

        if (!$jwksUri) {
            Log::error('JWKS URI not found in OpenID Configuration', [
                'base_url' => $baseUrl,
            ]);

            return null;
        }

        $cacheKey = $this->getJwksCacheKey($jwksUri);
        $failureKey = $this->getFailureBackoffCacheKey($cacheKey);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        if (Cache::get($failureKey) === true) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->throw()
                ->get($jwksUri);

            $json = $response->json();
            if (!is_array($json)) {
                Log::error('JWKS response was not JSON object/array', [
                    'uri' => $jwksUri,
                ]);

                Cache::put($failureKey, true, now()->addSeconds($this->failureBackoffSeconds));
                return null;
            }

            Cache::put($cacheKey, $json, now()->addMinutes($this->jwksCacheDuration));
            Cache::forget($failureKey);

            return $json;
        } catch (Exception $e) {
            Log::error('Failed to fetch JWKS', [
                'uri' => $jwksUri,
                'error' => $e->getMessage(),
            ]);

            Cache::put($failureKey, true, now()->addSeconds($this->failureBackoffSeconds));
            return null;
        }
    }

    /**
     * Get all public keys from the JWKS.
     *
     * @return array<string, string>
     */
    public function getPublicKeys(string $baseUrl): array
    {
        $jwks = $this->getJWKS($baseUrl);

        if (!$jwks || !isset($jwks['keys']) || !is_array($jwks['keys'])) {
            return [];
        }

        try {
            $parsed = JWK::parseKeySet($jwks);
        } catch (Throwable $e) {
            Log::error('Failed to parse JWKS', [
                'base_url' => $baseUrl,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $keys = [];
        foreach ($parsed as $kid => $key) {
            $pem = null;

            if ($key instanceof Key) {
                $pem = $this->normalizeKeyMaterialToPem($key->getKeyMaterial());
            } else {
                // Older versions may return an OpenSSL key / PEM string directly.
                $pem = $this->normalizeKeyMaterialToPem($key);
            }

            if (is_string($pem) && $pem !== '') {
                $keys[(string) $kid] = $pem;
            }
        }

        return $keys;
    }

    /**
     * Get a specific public key by key ID (kid).
     */
    public function getPublicKey(string $baseUrl, string $kid): ?string
    {
        $keys = $this->getPublicKeys($baseUrl);

        return $keys[$kid] ?? null;
    }

    /**
     * Get the first available public key (for providers that don't use kid).
     */
    public function getFirstPublicKey(string $baseUrl): ?string
    {
        $keys = $this->getPublicKeys($baseUrl);

        return !empty($keys) ? reset($keys) : null;
    }

    /**
     * Normalize key material to a PEM-encoded public key.
     */
    protected function normalizeKeyMaterialToPem(mixed $material): ?string
    {
        if (is_string($material)) {
            return $material;
        }

        if ($material instanceof OpenSSLAsymmetricKey || is_resource($material)) {
            $details = openssl_pkey_get_details($material);
            if (is_array($details) && isset($details['key']) && is_string($details['key'])) {
                return $details['key'];
            }

            return null;
        }

        return null;
    }

    /**
     * Clear the cache for a specific provider.
     */
    public function clearCache(string $baseUrl): void
    {
        $baseUrl = rtrim($baseUrl, '/');
        $configUrl = $baseUrl . '/.well-known/openid-configuration';

        // Clear both legacy and current-version keys.
        Cache::forget('oidc_config:' . md5($configUrl));
        Cache::forget($this->getOpenIdConfigCacheKey($configUrl));
        Cache::forget($this->getFailureBackoffCacheKey($this->getOpenIdConfigCacheKey($configUrl)));

        $jwksUri = $this->getJWKSUri($baseUrl);
        if ($jwksUri) {
            Cache::forget('oidc_jwks:' . md5($jwksUri));
            Cache::forget($this->getJwksCacheKey($jwksUri));
            Cache::forget($this->getFailureBackoffCacheKey($this->getJwksCacheKey($jwksUri)));
        }
    }

    /**
     * @internal
     */
    protected function getOpenIdConfigCacheKey(string $configUrl): string
    {
        return 'oidc_config:' . $this->cacheKeyVersion . ':' . md5($configUrl);
    }

    /**
     * @internal
     */
    protected function getJwksCacheKey(string $jwksUri): string
    {
        return 'oidc_jwks:' . $this->cacheKeyVersion . ':' . md5($jwksUri);
    }

    /**
     * @internal
     */
    protected function getFailureBackoffCacheKey(string $successCacheKey): string
    {
        return $successCacheKey . ':fetch_failed';
    }
}
