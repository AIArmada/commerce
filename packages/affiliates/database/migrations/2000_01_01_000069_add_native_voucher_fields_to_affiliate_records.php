<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('affiliates');
        $attributions = config('affiliates.database.tables.attributions', 'affiliate_attributions');
        $conversions = config('affiliates.database.tables.conversions', 'affiliate_conversions');

        Schema::table($attributions, function (Blueprint $table) use ($attributions, $jsonType): void {
            if (! Schema::hasColumn($attributions, 'affiliate_program_id')) {
                $table->uuid('affiliate_program_id')->nullable()->index();
            }

            if (! Schema::hasColumn($attributions, 'commission_override')) {
                $table->{$jsonType}('commission_override')->nullable();
            }

            if (! Schema::hasColumn($attributions, 'upline_levels')) {
                $table->{$jsonType}('upline_levels')->nullable();
            }
        });

        Schema::table($conversions, function (Blueprint $table) use ($conversions, $jsonType): void {
            if (! Schema::hasColumn($conversions, 'affiliate_program_id')) {
                $table->uuid('affiliate_program_id')->nullable()->index();
            }

            if (! Schema::hasColumn($conversions, 'commission_override')) {
                $table->{$jsonType}('commission_override')->nullable();
            }

            if (! Schema::hasColumn($conversions, 'upline_levels')) {
                $table->{$jsonType}('upline_levels')->nullable();
            }
        });
    }
};
