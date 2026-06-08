<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('chip.database.table_prefix', 'chip_') . 'customers';
        $jsonType = (string) commerce_json_column_type('chip', 'jsonb');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('subject');
            $table->nullableUuidMorphs('owner');
            $table->string('chip_customer_id')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['subject_type', 'subject_id']);
        });

        if (ConnectionDriver::name(Schema::getConnection()) === 'pgsql' && $jsonType === 'jsonb') {
            DB::statement("CREATE INDEX IF NOT EXISTS chip_customers_metadata_gin_index ON \"{$table}\" USING GIN (\"metadata\")");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('chip.database.table_prefix', 'chip_') . 'customers');
    }
};
