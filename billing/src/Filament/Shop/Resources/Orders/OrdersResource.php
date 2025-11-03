<?php

namespace Boy132\Billing\Filament\Shop\Resources\Orders;

use App\Filament\Server\Pages\Console;
use Boy132\Billing\Filament\Shop\Resources\Orders\Pages\ListOrders;
use Boy132\Billing\Models\Customer;
use Boy132\Billing\Models\Order;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use NumberFormatter;

class OrdersResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-truck-delivery';

    public static function getEloquentQuery(): Builder
    {
        /** @var Customer $customer */
        $customer = Customer::firstOrCreate([
            'user_id' => user()->id,
        ]);

        return parent::getEloquentQuery()->where('customer_id', $customer->id);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->sortable()
                    ->badge(),
                TextColumn::make('server.name')
                    ->label('Server')
                    ->placeholder('No server')
                    ->icon('tabler-brand-docker')
                    ->sortable()
                    ->url(fn (Order $order) => $order->server ? Console::getUrl(panel: 'server', tenant: $order->server) : null),
                TextColumn::make('productPrice.product.name')
                    ->label('Product')
                    ->icon('tabler-package')
                    ->sortable(),
                TextColumn::make('productPrice.name')
                    ->label('Price')
                    ->sortable(),
                TextColumn::make('productPrice.cost')
                    ->label('Cost')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        $formatter = new NumberFormatter(user()->language, NumberFormatter::CURRENCY);

                        return $formatter->formatCurrency($state, config('billing.currency'));
                    }),
            ])
            ->emptyStateHeading('No Orders')
            ->emptyStateDescription('');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
