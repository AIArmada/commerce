<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('communications.database.json_column_type', 'jsonb');

        Schema::create(config('communications.database.tables.tracking_tokens', 'communication_tracking_tokens'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->foreignUuid('delivery_id');
            $table->string('kind');
            $table->string('token_hash');
            $table->text('target_url_ciphertext')->nullable();
            $table->string('target_host')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('first_used_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['delivery_id', 'kind']);
            $table->index(['token_hash']);
        });
    }
};
