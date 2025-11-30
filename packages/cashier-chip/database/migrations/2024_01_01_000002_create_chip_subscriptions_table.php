<?php

declare(strict_types=1);

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
        if (Schema::hasTable('chip_subscriptions')) {
            return;
        }

        Schema::create('chip_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id');
            $table->string('type');
            $table->string('chip_id')->unique();
            $table->string('chip_status');
            $table->string('chip_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('recurring_token')->nullable();
            $table->string('billing_interval')->default('month');
            $table->integer('billing_interval_count')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'chip_status']);
            $table->index('user_id');
            $table->index('type');
            $table->index('recurring_token');
            $table->index('trial_ends_at');
            $table->index('next_billing_at');
            $table->index('ends_at');
            $table->index(['user_id', 'type'], 'chip_subscriptions_user_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chip_subscriptions');
    }
};
