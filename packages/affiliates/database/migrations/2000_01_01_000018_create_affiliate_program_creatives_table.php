<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.program_creatives', 'affiliate_program_creatives');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->nullable();
            $table->string('type'); // banner, text_link, email
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('asset_url', 2048)->nullable();
            $table->string('destination_url')->nullable();
            $table->string('tracking_code');

            $jsonType = config('affiliates.database.json_column_type', commerce_json_column_type('affiliates', 'jsonb'));
            $table->addColumn($jsonType, 'metadata')->nullable();

            $table->timestampsTz();

            $table->index('program_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.program_creatives', 'affiliate_program_creatives');
        Schema::dropIfExists($tableName);
    }
};
