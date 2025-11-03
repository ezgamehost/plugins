<?php

namespace Boy132\Billing\Models;

use App\Enums\SuspendAction;
use App\Models\Objects\DeploymentObject;
use App\Models\Server;
use App\Services\Servers\ServerCreationService;
use App\Services\Servers\SuspensionService;
use Boy132\Billing\Enums\OrderStatus;
use Exception;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * @property int $id
 * @property ?string $stripe_id
 * @property OrderStatus $status
 * @property int $customer_id
 * @property Customer $customer
 * @property int $product_price_id
 * @property ProductPrice $productPrice
 * @property ?int $server_id
 * @property ?Server $server
 */
class Order extends Model
{
    protected $fillable = [
        'stripe_id',
        'status',
        'customer_id',
        'product_price_id',
        'server_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->BelongsTo(Customer::class, 'customer_id');
    }

    public function productPrice(): BelongsTo
    {
        return $this->BelongsTo(ProductPrice::class, 'product_price_id');
    }

    public function server(): BelongsTo
    {
        return $this->BelongsTo(Server::class, 'server_id');
    }

    public function getCheckoutSession(): Session
    {
        /** @var StripeClient $stripeClient */
        $stripeClient = app(StripeClient::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        if (is_null($this->stripe_id)) {
            $session = $stripeClient->checkout->sessions->create([
                'customer_email' => $this->customer->user->email,
                'success_url' => route('billing.checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.checkout.cancel') . '?session_id={CHECKOUT_SESSION_ID}',
                'return_url' => Filament::getPanel('shop')->getUrl(),
                'line_items' => [
                    [
                        'price' => $this->productPrice->stripe_id,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'ui_mode' => 'hosted',
            ]);

            $this->update([
                'stripe_id' => $session->id,
            ]);

            return $session;
        }

        return $stripeClient->checkout->sessions->retrieve($this->stripe_id);
    }

    public function activate(): void
    {
        $this->status = OrderStatus::Active;
        $this->save();

        try {
            if ($this->server) {
                app(SuspensionService::class)->handle($this->server, SuspendAction::Unsuspend); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
            } else {
                $this->createServer();
            }
        } catch (Exception $exception) {
            report($exception);
        }
    }

    public function close(): void
    {
        try {
            if ($this->server) {
                app(SuspensionService::class)->handle($this->server, SuspendAction::Suspend); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
            }
        } catch (Exception $exception) {
            report($exception);
        }

        $this->status = OrderStatus::Closed;
        $this->save();
    }

    private function createServer(): Server
    {
        if ($this->server) {
            return $this->server;
        }

        $product = $this->productPrice->product;

        $environment = [];
        foreach ($product->egg->variables as $variable) {
            $environment[$variable->env_variable] = $variable->default_value;
        }

        $data = [
            'name' => 'Order #' . $this->id,
            'owner_id' => $this->customer->user->id,
            'egg_id' => $product->egg->id,
            'cpu' => $product->cpu,
            'memory' => $product->memory,
            'disk' => $product->disk,
            'swap' => $product->swap,
            'io' => 500,
            'environment' => $environment,
            'skip_scripts' => false,
            'start_on_completion' => true,
            'oom_killer' => false,
            'database_limit' => $product->database_limit,
            'allocation_limit' => $product->allocation_limit,
            'backup_limit' => $product->backup_limit,
        ];

        $object = new DeploymentObject();
        $object->setDedicated(false);
        $object->setTags([]);
        $object->setPorts($product->ports);

        $server = app(ServerCreationService::class)->handle($data, $object); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $this->server_id = $server->id;
        $this->save();

        return $server;
    }
}
