<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait EnsuresPricingSchema
{
    protected function ensurePricingSchema(): void
    {
        if (! Schema::hasColumn('price_lists', 'deactivated_at')) {
            Schema::table('price_lists', function (Blueprint $table): void {
                $table->timestampTz('deactivated_at')->nullable();
            });
        }

        if (! Schema::hasColumn('prices', 'deactivated_at')) {
            Schema::table('prices', function (Blueprint $table): void {
                $table->timestampTz('deactivated_at')->nullable();
            });
        }
    }
}
