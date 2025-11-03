<?php

namespace Boy132\Billing\Models;

use Boy132\Billing\Enums\PriceInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NumberFormatter;
use Stripe\StripeClient;

/**
 * @property int $id
 * @property ?string $stripe_id
 * @property string $name
 * @property int $cost
 * @property PriceInterval $interval_type
 * @property int $interval_value
 * @property int $product_id
 * @property Product $product
 */
class ProductPrice extends Model
{
    protected $fillable = [
        'stripe_id',
        'product_id',
        'name',
        'cost',
        'interval_type',
        'interval_value',
    ];

    protected function casts(): array
    {
        return [
            'interval_type' => PriceInterval::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (self $model) {
            $model->sync();
        });

        static::updated(function (self $model) {
            $model->sync();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function sync(): void
    {
        $this->product->sync();

        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        if (is_null($this->stripe_id)) {
            $stripePrice = $stripeClient->prices->create([
                'currency' => config('billing.currency'),
                'nickname' => $this->name,
                'product' => $this->product->stripe_id,
                'recurring' => [
                    'interval' => $this->interval_type->value,
                    'interval_count' => $this->interval_value,
                ],
                'unit_amount' => $this->cost,
            ]);

            $this->update([
                'stripe_id' => $stripePrice->id,
            ]);
        } else {
            $stripePrice = $stripeClient->prices->retrieve($this->stripe_id);

            // You can't update price objects on stripe, so check for changes and recreate the price if needed
            if ($stripePrice->product !== $this->product->stripe_id || $stripePrice->unit_amount !== $this->cost || $stripePrice->recurring->interval !== $this->interval_type->value || $stripePrice->recurring->interval_count !== $this->interval_value) {
                $this->stripe_id = null;
                $this->sync();
            }
        }
    }

    public function formatCost(): string
    {
        $formatter = new NumberFormatter(user()?->language ?? 'en', NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($this->cost, config('billing.currency'));
    }
}
