<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function getTableName(): string
    {
        $tables = config('checkout.database.tables', []);
        $prefix = config('checkout.database.table_prefix', '');

        return $tables['checkout_sessions'] ?? $prefix . 'checkout_sessions';
    }

    public function up(): void
    {
        Schema::table($this->getTableName(), function (Blueprint $table): void {
            $table->timestampTz('cancelled_at')->nullable()->index();
            $table->timestampTz('payment_failed_at')->nullable();
        });
    }
};
