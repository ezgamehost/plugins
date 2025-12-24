<?php

namespace Boy132\GenericOIDCProviders\Extensions\OAuth\Schemas;

use App\Extensions\OAuth\Schemas\OAuthSchema;
use Boy132\GenericOIDCProviders\Facades\JWKSDiscovery;
use Boy132\GenericOIDCProviders\Filament\Admin\Resources\GenericOIDCProviders\Pages\EditGenericOIDCProvider;
use Boy132\GenericOIDCProviders\Models\GenericOIDCProvider;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;
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
                // Use JWKS discovery to get the public keys
                $config['jwks_uri'] = JWKSDiscovery::getJWKSUri($this->model->base_url);
                $config['jwt_public_keys'] = JWKSDiscovery::getPublicKeys($this->model->base_url);

                // For backwards compatibility, also set jwt_public_key to the first key
                $config['jwt_public_key'] = JWKSDiscovery::getFirstPublicKey($this->model->base_url);
            } else {
                $config['jwt_public_key'] = $this->model->jwt_public_key;
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
