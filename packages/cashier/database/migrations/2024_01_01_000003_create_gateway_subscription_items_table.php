<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gateway_subscription_items', function (Blueprint $table) {
            $table->id();

            // Relationship to subscription
            $table->foreignId('subscription_id')->constrained('gateway_subscriptions')->cascadeOnDelete();

            // Gateway identification
            $table->string('gateway_id')->index();
            $table->string('gateway_product')->nullable();
            $table->string('gateway_price')->index();

            // Quantity for this item
            $table->integer('quantity')->nullable();

            // Unit amount in cents
            $table->integer('unit_amount')->nullable();

            $table->timestamps();

            // Unique constraint: one item per price per subscription
            $table->unique(['subscription_id', 'gateway_price']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gateway_subscription_items');
    }
};
