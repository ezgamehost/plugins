<?php

namespace Boy132\Billing\Providers;

use App\Providers\RouteServiceProvider;
use Boy132\Billing\Http\Controllers\Api\CheckoutController;
use Illuminate\Support\Facades\Route;

class BillingRoutesProvider extends RouteServiceProvider
{
    public function boot(): void
    {
        $this->routes(function () {
            Route::get('checkout/success', [CheckoutController::class, 'success'])->name('billing.checkout.success')->withoutMiddleware(['auth']);
            Route::get('checkout/cancel', [CheckoutController::class, 'cancel'])->name('billing.checkout.cancel')->withoutMiddleware(['auth']);
        });
    }
}
