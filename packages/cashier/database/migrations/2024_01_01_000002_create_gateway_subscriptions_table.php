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
        Schema::create('gateway_subscriptions', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship to billable model
            $table->morphs('billable');

            // Gateway identification
            $table->string('gateway')->default('stripe')->index();
            $table->string('gateway_id')->index();
            $table->string('gateway_status')->nullable();
            $table->string('gateway_price')->nullable();

            // Subscription type/name
            $table->string('type')->index();

            // Quantity for metered subscriptions
            $table->integer('quantity')->nullable();

            // Trial management
            $table->timestamp('trial_ends_at')->nullable();

            // Billing cycle
            $table->timestamp('next_billing_at')->nullable();
            $table->string('billing_interval')->nullable();

            // Subscription lifecycle
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            // Unique constraint: one subscription per type per billable per gateway
            $table->unique(['billable_type', 'billable_id', 'type', 'gateway']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gateway_subscriptions');
    }
};
