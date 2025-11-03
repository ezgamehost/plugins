<?php

namespace Boy132\Billing\Http\Controllers\Api;

use App\Filament\Server\Pages\Console;
use App\Http\Controllers\Controller;
use Boy132\Billing\Filament\Shop\Resources\Orders\Pages\ListOrders;
use Boy132\Billing\Models\Order;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class CheckoutController extends Controller
{
    public function __construct(
        private StripeClient $stripeClient
    ) {
        parent::__construct();
    }

    public function success(Request $request): RedirectResponse
    {
        $sessionId = $request->get('session_id');

        if ($sessionId === null) {
            return redirect(Filament::getPanel('shop')->getUrl());
        }

        $session = $this->stripeClient->checkout->sessions->retrieve($sessionId);

        if ($session->payment_status !== 'paid') {
            return redirect(ListOrders::getUrl(panel: 'shop'));
        }

        /** @var Order $order */
        $order = Order::where('stripe_id', $session->id)->firstOrFail();
        $order->activate();

        return redirect(Console::getUrl(panel: 'server', tenant: $order->server));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $sessionId = $request->get('session_id');

        if ($sessionId) {
            /** @var ?Order $order */
            $order = Order::where('stripe_id', $sessionId)->first();
            $order?->close();
        }

        return redirect(ListOrders::getUrl(panel: 'shop'));
    }
}
