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

        Schema::create(config('ticketing.database.tables.passes'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('ticketable_id');
            $table->string('ticketable_type');
            $table->uuid('ticket_type_id')->nullable()->index();
            $table->string('pass_no')->unique();
            $table->string('qr_code')->nullable()->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('status')->index();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('transfer_expires_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableMorphs('owner');
            $table->nullableMorphs('registration');
            $table->uuid('occurrence_id')->nullable()->index();
            $table->uuid('session_id')->nullable()->index();
            $table->timestampsTz();

            $table->index(['ticketable_id', 'ticketable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ticketing.database.tables.passes'));
    }
};
