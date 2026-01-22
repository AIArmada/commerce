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
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $columns = [
                'chip_id' => fn () => $table->string('chip_id')->nullable()->index(),
                'chip_default_payment_method' => fn () => $table->string('chip_default_payment_method')->nullable(),
                'pm_type' => fn () => $table->string('pm_type')->nullable(),
                'pm_last_four' => fn () => $table->string('pm_last_four', 4)->nullable(),
                'trial_ends_at' => fn () => $table->timestamp('trial_ends_at')->nullable(),
            ];

            foreach ($columns as $column => $add) {
                if (! Schema::hasColumn('users', $column)) {
                    $add();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'chip_id')) {
                $table->dropIndex(['chip_id']);
            }

            foreach (['chip_id', 'chip_default_payment_method', 'pm_type', 'pm_last_four', 'trial_ends_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
