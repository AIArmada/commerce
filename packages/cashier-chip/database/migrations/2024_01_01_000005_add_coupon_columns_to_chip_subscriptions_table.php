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
        if (! Schema::hasTable('chip_subscriptions')) {
            return;
        }

        Schema::table('chip_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('chip_subscriptions', 'coupon_id')) {
                $table->string('coupon_id')->nullable()->after('ends_at');
                $table->index('coupon_id');
            }

            if (! Schema::hasColumn('chip_subscriptions', 'coupon_discount')) {
                $table->integer('coupon_discount')->nullable()->after('coupon_id');
            }

            if (! Schema::hasColumn('chip_subscriptions', 'coupon_duration')) {
                $table->string('coupon_duration')->nullable()->after('coupon_discount');
            }

            if (! Schema::hasColumn('chip_subscriptions', 'coupon_applied_at')) {
                $table->timestamp('coupon_applied_at')->nullable()->after('coupon_duration');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('chip_subscriptions')) {
            return;
        }

        Schema::table('chip_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('chip_subscriptions', 'coupon_id')) {
                $table->dropIndex(['coupon_id']);
                $table->dropColumn('coupon_id');
            }

            $columns = ['coupon_discount', 'coupon_duration', 'coupon_applied_at'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('chip_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
