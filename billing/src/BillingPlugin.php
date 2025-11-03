<?php

namespace Boy132\Billing;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;

class BillingPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'billing';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        $panel->discoverResources(plugin_path($this->getId(), "src/Filament/$id/Resources"), "Boy132\\Billing\\Filament\\$id\\Resources");
        $panel->discoverPages(plugin_path($this->getId(), "src/Filament/$id/Pages"), "Boy132\\Billing\\Filament\\$id\\Pages");
        $panel->discoverWidgets(plugin_path($this->getId(), "src/Filament/$id/Widgets"), "Boy132\\Billing\\Filament\\$id\\Widgets");

        if ($panel->getId() === 'app') {
            $panel->path('servers');
        }
    }

    public function boot(Panel $panel): void {}

    public function getSettingsForm(): array
    {
        return [
            TextInput::make('key')
                ->label('Stripe Key')
                ->required()
                ->default(fn () => config('billing.key')),
            TextInput::make('secret')
                ->label('Stripe Secret')
                ->required()
                ->default(fn () => config('billing.secret')),
            Select::make('currency')
                ->label('Currency')
                ->required()
                ->default(fn () => config('billing.currency'))
                ->options([
                    'USD' => 'US Dollar',
                    'EUR' => 'Euro',
                    'GBP' => 'British Pound',
                ]),
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'STRIPE_KEY' => $data['key'],
            'STRIPE_SECRET' => $data['secret'],
            'BILLING_CURRENCY' => $data['currency'],
        ]);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
