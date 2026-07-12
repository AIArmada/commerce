<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('ticketing', 'jsonb');

        Schema::create(config('ticketing.database.tables.pass_transfers', 'ticket_pass_transfers'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('pass_id')->index();
            $table->uuid('from_holder_id')->nullable()->index();
            $table->uuid('to_holder_id')->nullable()->index();
            $table->text('reason')->nullable();
            $table->string('transferred_by_type')->nullable();
            $table->string('transferred_by_id')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['pass_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ticketing.database.tables.pass_transfers', 'ticket_pass_transfers'));
    }
};
