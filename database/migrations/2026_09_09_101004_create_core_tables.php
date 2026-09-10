<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('type');
            $table->unsignedBigInteger('price');
            $table->string('currency', 3)->default('RUB');
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('key_pool', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('status')->default('available');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('sku');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('status')->default('created')->index();
            $table->string('payment_event_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->string('delivery_request_id')->nullable();
            $table->string('current_supplier')->nullable();
            $table->integer('supplier_attempts')->default(0);

            $table->uuid('delivery_lease_token')->nullable();
            $table->timestamp('delivery_lease_expires_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'delivery_lease_expires_at']);
        });

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->string('event_id')->primary();
            $table->foreignId('order_id');
            $table->string('status')->default('received');
            $table->jsonb('payload');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->string('code');
            $table->string('supplier');
            $table->string('request_id')->unique();
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('supplier_stub_ledger', function (Blueprint $table) {
            $table->string('request_id')->primary();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('sku');
            $table->string('supplier');
            $table->string('code')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_stub_ledger');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('key_pool');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('products');
    }
};
