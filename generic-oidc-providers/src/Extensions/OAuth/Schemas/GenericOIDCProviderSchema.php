<?php

namespace Boy132\GenericOIDCProviders\Extensions\OAuth\Schemas;

use App\Extensions\OAuth\Schemas\OAuthSchema;
use Boy132\GenericOIDCProviders\Facades\JWKSDiscovery;
use Boy132\GenericOIDCProviders\Filament\Admin\Resources\GenericOIDCProviders\Pages\EditGenericOIDCProvider;
use Boy132\GenericOIDCProviders\Models\GenericOIDCProvider;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Log;
use SocialiteProviders\OIDC\Provider;

final class GenericOIDCProviderSchema extends OAuthSchema
{
    public function __construct(private GenericOIDCProvider $model) {}

    public function getId(): string
    {
        return $this->model->id;
    }

    public function getSocialiteProvider(): string
    {
        return Provider::class;
    }

    public function getServiceConfig(): array
    {
        $config = [
            'client_id' => $this->model->client_id,
            'client_secret' => $this->model->client_secret,
            'base_url' => $this->model->base_url,
            'verify_jwt' => $this->model->verify_jwt,
        ];

        if ($this->model->verify_jwt) {
            if ($this->model->use_jwks_discovery) {
                // Use JWKS discovery to get the public keys, but be defensive:
                // discovery failures must not leave verify_jwt enabled with missing/empty key material.
                $jwksUri = JWKSDiscovery::getJWKSUri($this->model->base_url);
                if (!is_string($jwksUri) || $jwksUri === '') {
                    Log::error('OIDC JWKS discovery failed: jwks_uri missing from OpenID configuration', [
                        'provider_id' => $this->model->id,
                        'base_url' => $this->model->base_url,
                    ]);
                    $jwksUri = null;
                }

                $discoveredKeys = JWKSDiscovery::getPublicKeys($this->model->base_url);
                if (!is_array($discoveredKeys) || empty($discoveredKeys)) {
                    Log::error('OIDC JWKS discovery failed: no public keys discovered', [
                        'provider_id' => $this->model->id,
                        'base_url' => $this->model->base_url,
                        'jwks_uri' => $jwksUri,
                    ]);
                    $discoveredKeys = [];
                }

                // Avoid redundant JWKS fetch: derive the first key from the already-discovered set.
                $firstDiscoveredKey = !empty($discoveredKeys) ? reset($discoveredKeys) : null;
                if (!is_string($firstDiscoveredKey) || $firstDiscoveredKey === '') {
                    $firstDiscoveredKey = null;
                }

                if ($jwksUri !== null) {
                    $config['jwks_uri'] = $jwksUri;
                }

                if (!empty($discoveredKeys)) {
                    $config['jwt_public_keys'] = $discoveredKeys;
                }

                // Prefer discovered key material, fall back to configured manual key if available.
                $effectivePublicKey = $firstDiscoveredKey ?: ($this->model->jwt_public_key ?: null);

                if (!is_string($effectivePublicKey) || $effectivePublicKey === '') {
                    // Fail closed: if we cannot obtain key material, disable JWT verification in config
                    // so downstream verifiers are never invoked with null/empty keys.
                    Log::error('OIDC JWT verification disabled: no usable public key material available', [
                        'provider_id' => $this->model->id,
                        'base_url' => $this->model->base_url,
                        'use_jwks_discovery' => true,
                        'jwks_uri' => $jwksUri,
                        'has_manual_key' => (bool) $this->model->jwt_public_key,
                    ]);

                    $config['verify_jwt'] = false;
                } else {
                    $config['jwt_public_key'] = $effectivePublicKey;
                }
            } else {
                if (!is_string($this->model->jwt_public_key) || $this->model->jwt_public_key === '') {
                    Log::error('OIDC JWT verification disabled: manual jwt_public_key is missing', [
                        'provider_id' => $this->model->id,
                        'base_url' => $this->model->base_url,
                        'use_jwks_discovery' => false,
                    ]);

                    $config['verify_jwt'] = false;
                } else {
                    $config['jwt_public_key'] = $this->model->jwt_public_key;
                }
            }
        }

        return $config;
    }

    public function getSetupSteps(): array
    {
        return [
            Step::make('Generic OIDC Provider')
                ->schema([
                    TextEntry::make('info')
                        ->hiddenLabel()
                        ->state('This a generic OIDC provider and doesn\'t require any setup!'),
                ]),
        ];
    }

    public function getSettingsForm(): array
    {
        return [
            TextEntry::make('info')
                ->label('Generic OIDC Provider')
                ->state('Click here to configure this generic OIDC provider.')
                ->url(EditGenericOIDCProvider::getUrl(['record' => $this->model], panel: 'admin'))
                ->columnSpanFull(),
        ];
    }

    public function getName(): string
    {
        return $this->model->display_name;
    }

    public function getIcon(): ?string
    {
        return $this->model->display_icon;
    }

    public function getHexColor(): ?string
    {
        return $this->model->display_color;
    }

    public function shouldCreateMissingUsers(): bool
    {
        return $this->model->create_missing_users;
    }

    public function shouldLinkMissingUsers(): bool
    {
        return $this->model->link_missing_users;
    }
}
