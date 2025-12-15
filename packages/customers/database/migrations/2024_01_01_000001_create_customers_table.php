<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('customers.tables.customers', 'customers'), function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            // Link to User model
            $table->foreignId('user_id')->nullable();

            // Basic info
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();

            // Status
            $table->string('status')->default('active');

            // Wallet / Store Credit (in cents)
            $table->unsignedBigInteger('wallet_balance')->default(0);

            // Lifetime value tracking
            $table->unsignedBigInteger('lifetime_value')->default(0);
            $table->unsignedInteger('total_orders')->default(0);

            // Preferences
            $table->boolean('accepts_marketing')->default(true);
            $table->boolean('is_tax_exempt')->default(false);
            $table->string('tax_exempt_reason')->nullable();

            // Timestamps
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('last_login_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['status', 'accepts_marketing']);
            $table->index('lifetime_value');
            $table->index('last_order_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('customers.tables.customers', 'customers'));
    }
};
