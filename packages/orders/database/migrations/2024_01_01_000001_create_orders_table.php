<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('orders.database.tables.orders', 'orders'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();
            $table->string('status', 50)->default('created')->index();

            // Customer relationship (polymorphic)
            $table->uuidMorphs('customer');

            // Owner relationship (polymorphic - for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            // Money fields (stored in cents)
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->string('currency', 3)->default('MYR');

            // Notes
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            // Timestamps
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['customer_type', 'customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('orders.database.tables.orders', 'orders'));
    }
};
