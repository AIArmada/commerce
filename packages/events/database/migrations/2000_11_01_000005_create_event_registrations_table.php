<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $databaseConfig = (array) config('events.database', []);
        $jsonType = (string) ($databaseConfig['json_column_type'] ?? commerce_json_column_type('events', 'json'));

        Schema::create(config('events.database.tables.registrations', 'event_registrations'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->uuid('occurrence_id')->index();
            $table->uuid('order_id')->nullable()->index();
            $table->uuid('order_item_id')->nullable()->index();
            $table->uuid('purchaser_customer_id')->nullable()->index();
            $table->uuid('participant_customer_id')->nullable()->index();
            $table->string('code')->unique();
            $table->string('status', 32)->default('pending')->index();
            $table->string('first_name');
            $table->string('last_name')->default('');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->timestamp('checked_in_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();

            $table->index(['occurrence_id', 'status']);
            $table->index(['owner_type', 'owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('events.database.tables.registrations', 'event_registrations'));
    }
};
