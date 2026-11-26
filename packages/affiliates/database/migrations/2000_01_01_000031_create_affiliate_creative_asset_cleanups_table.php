<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('affiliates.database.tables.creative_asset_cleanups', 'affiliate_creative_asset_cleanups'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('media_class');
            $table->text('media_attributes');
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->unsignedBigInteger('attempt_count')->default(0);
            $table->timestampsTz();
            $table->index(['attempt_count', 'created_at', 'id'], 'affiliate_creative_cleanup_retry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.creative_asset_cleanups', 'affiliate_creative_asset_cleanups'));
    }
};
