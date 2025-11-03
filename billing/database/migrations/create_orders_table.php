<?php

use App\Models\Server;
use Boy132\Billing\Enums\OrderStatus;
use Boy132\Billing\Models\Customer;
use Boy132\Billing\Models\ProductPrice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_id')->nullable();
            $table->string('status')->default(OrderStatus::Pending);
            $table->foreignIdFor(Customer::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ProductPrice::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Server::class)->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
