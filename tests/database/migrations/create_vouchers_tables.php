<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('owner');
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->decimal('value', 10, 2);
            $table->string('currency', 3)->default('MYR');
            $table->decimal('min_cart_value', 10, 2)->nullable();
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_limit_per_user')->nullable();
            $table->integer('times_used')->default(0);
            $table->boolean('allows_manual_redemption')->default(false);
            $table->datetime('starts_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->json('applicable_products')->nullable();
            $table->json('excluded_products')->nullable();
            $table->json('applicable_categories')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('voucher_usage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->string('user_identifier');
            $table->string('cart_identifier')->nullable();
            $table->decimal('discount_amount', 10, 2);
            $table->string('currency', 3);
            $table->json('cart_snapshot')->nullable();
            $table->string('channel')->default('automatic');
            $table->nullableMorphs('redeemed_by');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_usage');
        Schema::dropIfExists('vouchers');
    }
};
