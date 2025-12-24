<?php

namespace Boy132\GenericOIDCProviders\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Get the OpenID Configuration from the provider.
     *
     * @return array<string, mixed>|null
     */
    public function getOpenIDConfiguration(string $baseUrl): ?array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $configUrl = $baseUrl . '/.well-known/openid-configuration';
        $cacheKey = 'oidc_config:' . md5($configUrl);

        return Cache::remember($cacheKey, now()->addMinutes($this->configCacheDuration), function () use ($configUrl) {
            try {
                $response = Http::timeout(10)
                    ->connectTimeout(5)
                    ->throw()
                    ->get($configUrl);

                return $response->json();
            } catch (Exception $e) {
                Log::error('Failed to fetch OpenID Configuration', [
                    'url' => $configUrl,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
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

        $cacheKey = 'oidc_jwks:' . md5($jwksUri);

        return Cache::remember($cacheKey, now()->addMinutes($this->jwksCacheDuration), function () use ($jwksUri) {
            try {
                $response = Http::timeout(10)
                    ->connectTimeout(5)
                    ->throw()
                    ->get($jwksUri);

                return $response->json();
            } catch (Exception $e) {
                Log::error('Failed to fetch JWKS', [
                    'uri' => $jwksUri,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Get all public keys from the JWKS.
     *
     * @return array<string, string>
     */
    public function getPublicKeys(string $baseUrl): array
    {
        $jwks = $this->getJWKS($baseUrl);

        if (!$jwks || !isset($jwks['keys'])) {
            return [];
        }

        $keys = [];
        foreach ($jwks['keys'] as $key) {
            if (!isset($key['kid'])) {
                continue;
            }

            $publicKey = $this->jwkToPem($key);
            if ($publicKey) {
                $keys[$key['kid']] = $publicKey;
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
     * Convert a JWK to PEM format.
     */
    protected function jwkToPem(array $jwk): ?string
    {
        if (!isset($jwk['kty'])) {
            return null;
        }

        return match ($jwk['kty']) {
            'RSA' => $this->rsaJwkToPem($jwk),
            'EC' => $this->ecJwkToPem($jwk),
            default => null,
        };
    }

    /**
     * Convert an RSA JWK to PEM format.
     */
    protected function rsaJwkToPem(array $jwk): ?string
    {
        if (!isset($jwk['n']) || !isset($jwk['e'])) {
            return null;
        }

        $modulus = $this->base64UrlDecode($jwk['n']);
        $exponent = $this->base64UrlDecode($jwk['e']);

        $modulus = ltrim($modulus, "\x00");
        if (ord($modulus[0]) > 0x7f) {
            $modulus = "\x00" . $modulus;
        }

        $exponent = ltrim($exponent, "\x00");

        // Build RSA public key ASN.1 structure
        $modulusEncoded = $this->asn1EncodeInteger($modulus);
        $exponentEncoded = $this->asn1EncodeInteger($exponent);

        $rsaPublicKey = $this->asn1EncodeSequence($modulusEncoded . $exponentEncoded);

        // RSA OID
        $rsaOid = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

        $bitString = "\x00" . $rsaPublicKey;
        $bitStringEncoded = $this->asn1EncodeBitString($bitString);

        $publicKeyInfo = $this->asn1EncodeSequence($rsaOid . $bitStringEncoded);

        return "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($publicKeyInfo), 64, "\n") .
            '-----END PUBLIC KEY-----';
    }

    /**
     * Convert an EC JWK to PEM format.
     */
    protected function ecJwkToPem(array $jwk): ?string
    {
        if (!isset($jwk['crv']) || !isset($jwk['x']) || !isset($jwk['y'])) {
            return null;
        }

        $x = $this->base64UrlDecode($jwk['x']);
        $y = $this->base64UrlDecode($jwk['y']);

        // Get the OID for the curve
        $curveOid = match ($jwk['crv']) {
            'P-256' => "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07",
            'P-384' => "\x06\x05\x2b\x81\x04\x00\x22",
            'P-521' => "\x06\x05\x2b\x81\x04\x00\x23",
            default => null,
        };

        if (!$curveOid) {
            return null;
        }

        // Pad x and y to the correct length based on curve
        $keyLength = match ($jwk['crv']) {
            'P-256' => 32,
            'P-384' => 48,
            'P-521' => 66,
            default => 0,
        };

        $x = str_pad($x, $keyLength, "\x00", STR_PAD_LEFT);
        $y = str_pad($y, $keyLength, "\x00", STR_PAD_LEFT);

        // EC point format: 0x04 (uncompressed) + x + y
        $ecPoint = "\x04" . $x . $y;

        // EC public key OID
        $ecOid = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";

        $algorithmIdentifier = $this->asn1EncodeSequence($ecOid . $curveOid);
        $bitStringEncoded = $this->asn1EncodeBitString("\x00" . $ecPoint);

        $publicKeyInfo = $this->asn1EncodeSequence($algorithmIdentifier . $bitStringEncoded);

        return "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($publicKeyInfo), 64, "\n") .
            '-----END PUBLIC KEY-----';
    }

    /**
     * Decode base64url encoded string.
     */
    protected function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * ASN.1 encode an integer.
     */
    protected function asn1EncodeInteger(string $data): string
    {
        return "\x02" . $this->asn1EncodeLength(strlen($data)) . $data;
    }

    /**
     * ASN.1 encode a sequence.
     */
    protected function asn1EncodeSequence(string $data): string
    {
        return "\x30" . $this->asn1EncodeLength(strlen($data)) . $data;
    }

    /**
     * ASN.1 encode a bit string.
     */
    protected function asn1EncodeBitString(string $data): string
    {
        return "\x03" . $this->asn1EncodeLength(strlen($data)) . $data;
    }

    /**
     * ASN.1 encode length.
     */
    protected function asn1EncodeLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($temp)) . $temp;
    }

    /**
     * Clear the cache for a specific provider.
     */
    public function clearCache(string $baseUrl): void
    {
        $baseUrl = rtrim($baseUrl, '/');
        $configUrl = $baseUrl . '/.well-known/openid-configuration';

        Cache::forget('oidc_config:' . md5($configUrl));

        $jwksUri = $this->getJWKSUri($baseUrl);
        if ($jwksUri) {
            Cache::forget('oidc_jwks:' . md5($jwksUri));
        }
    }
}
