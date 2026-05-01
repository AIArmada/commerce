<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = $this->getTableName();
        $jsonType = config('checkout.database.json_column_type', commerce_json_column_type('checkout', 'json'));

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();

            // References
            $table->string('cart_id')->index();
            $table->foreignUuid('customer_id')->nullable()->index();
            $table->foreignUuid('order_id')->nullable()->index();
            $table->string('payment_id')->nullable()->index();

            // Multi-tenancy
            $table->nullableUuidMorphs('owner');

            // Status tracking
            $table->string('status')->default('pending')->index();
            $table->string('current_step')->nullable();
            $table->string('error_message')->nullable();

            // Cart snapshot
            $table->{$jsonType}('cart_snapshot')->nullable();
            $table->{$jsonType}('step_states')->nullable();

            // Address data
            $table->{$jsonType}('shipping_data')->nullable();
            $table->{$jsonType}('billing_data')->nullable();

            // Calculation data
            $table->{$jsonType}('pricing_data')->nullable();
            $table->{$jsonType}('discount_data')->nullable();
            $table->{$jsonType}('tax_data')->nullable();
            $table->{$jsonType}('payment_data')->nullable();

            // Payment handling
            $table->string('payment_redirect_url', 2048)->nullable();
            $table->unsignedSmallInteger('payment_attempts')->default(0);

            // Selected options
            $table->string('selected_shipping_method')->nullable();
            $table->string('selected_payment_gateway')->nullable();

            // Totals (stored in smallest currency unit, e.g., cents)
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->string('currency', 3)->default('MYR');

            // Timestamps
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['status', 'created_at']);
            $table->index(['customer_id', 'status']);
        });
    }

    private function getTableName(): string
    {
        $tables = config('checkout.database.tables', []);
        $prefix = config('checkout.database.table_prefix', '');

        return $tables['checkout_sessions'] ?? $prefix . 'checkout_sessions';
    }
};
