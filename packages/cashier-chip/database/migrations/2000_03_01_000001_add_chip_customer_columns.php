<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function stripeCashierIsLoaded(): bool
    {
        return class_exists(\Laravel\Cashier\CashierServiceProvider::class)
            && app()->getProvider(\Laravel\Cashier\CashierServiceProvider::class) !== null;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        $stripeCashierIsLoaded = $this->stripeCashierIsLoaded();

        Schema::table('users', function (Blueprint $table) use ($stripeCashierIsLoaded): void {
            $columns = [
                'chip_id' => fn () => $table->string('chip_id')->nullable()->index(),
                'default_pm_id' => fn () => $table->string('default_pm_id')->nullable(),
            ];

            if (! $stripeCashierIsLoaded) {
                $columns['pm_type'] = fn () => $table->string('pm_type')->nullable();
                $columns['pm_last_four'] = fn () => $table->string('pm_last_four', 4)->nullable();
                $columns['trial_ends_at'] = fn () => $table->timestamp('trial_ends_at')->nullable();
            }

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

        $stripeCashierIsLoaded = $this->stripeCashierIsLoaded();

        Schema::table('users', function (Blueprint $table) use ($stripeCashierIsLoaded): void {
            if (Schema::hasColumn('users', 'chip_id')) {
                $table->dropIndex(['chip_id']);
            }

            $columns = ['chip_id', 'default_pm_id'];

            if (! $stripeCashierIsLoaded) {
                $columns = [...$columns, 'pm_type', 'pm_last_four', 'trial_ends_at'];
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
