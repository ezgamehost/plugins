# Generic OIDC Providers (by Boy132)

Allows administrators to create and configure generic OpenID Connect (OIDC) providers for authentication.

## Features

- Create custom OIDC providers through the admin panel
- Configure client ID, client secret, and authorization endpoints
- Support for multiple OIDC providers simultaneously
- **JWT Verification** with two modes:
  - **JWKS Discovery** (recommended): Automatically discovers and caches public keys from the provider's `.well-known/openid-configuration` endpoint
  - **Manual Public Key**: Configure a static public key for JWT verification

## JWKS Discovery

When JWKS Discovery is enabled, the plugin will:

1. Fetch the OpenID Configuration from `{base_url}/.well-known/openid-configuration`
2. Extract the `jwks_uri` from the configuration
3. Fetch and cache the JSON Web Key Set (JWKS) from the JWKS URI
4. Convert JWK keys to PEM format for JWT verification

Supported key types:
- RSA keys (kty: RSA)
- Elliptic Curve keys (kty: EC) with curves P-256, P-384, and P-521

The JWKS and OpenID Configuration are cached for 60 minutes to reduce external requests.
