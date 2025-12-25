<?php

namespace Boy132\GenericOIDCProviders\Providers;

use App\Extensions\OAuth\OAuthService;
use Boy132\GenericOIDCProviders\Extensions\OAuth\Schemas\GenericOIDCProviderSchema;
use Boy132\GenericOIDCProviders\Models\GenericOIDCProvider;
use Boy132\GenericOIDCProviders\Services\JWKSDiscoveryService;
use Illuminate\Support\ServiceProvider;

class GenericOIDCProvidersPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JWKSDiscoveryService::class);
    }

    public function boot(): void
    {
        $service = $this->app->make(OAuthService::class);

        $providers = GenericOIDCProvider::all();
        foreach ($providers as $provider) {
            $service->register(new GenericOIDCProviderSchema($provider));
        }
    }
}
