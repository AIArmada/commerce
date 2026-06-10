<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $j = (string) config('events.database.json_column_type', 'jsonb');

        Schema::create(config('events.database.tables.seat_categories', 'event_seat_categories'), function (Blueprint $t) use ($j): void {
            $t->uuid('id')->primary();
            $t->nullableUuidMorphs('owner');
            $t->nullableMorphs('assignable');
            $t->string('name');
            $t->unsignedInteger('capacity')->nullable();
            $t->uuid('product_id')->nullable();
            $t->uuid('variant_id')->nullable();
            $t->unsignedInteger('order_column')->nullable();
            $t->{$j}('metadata')->nullable();
            $t->timestampsTz();
            $t->index(['assignable_type', 'assignable_id', 'order_column']);
        });

        Schema::create(config('events.database.tables.registration_groups', 'event_registration_groups'), function (Blueprint $t) use ($j): void {
            $t->uuid('id')->primary();
            $t->nullableUuidMorphs('owner');
            $t->uuid('occurrence_id')->index();
            $t->uuid('seat_category_id')->nullable()->index();
            $t->uuid('purchaser_customer_id')->nullable();
            $t->uuid('order_id')->nullable();
            $t->string('name')->nullable();
            $t->string('code')->unique();
            $t->string('status', 32)->default('draft');
            $t->unsignedInteger('size')->nullable();
            $t->string('check_in_mode', 32)->default('per_member');
            $t->timestampTz('filled_at')->nullable();
            $t->timestampTz('cancelled_at')->nullable();
            $t->{$j}('metadata')->nullable();
            $t->timestampsTz();
            $t->index(['occurrence_id', 'status']);
        });

        $registrationsTable = (string) config('events.database.tables.registrations', 'event_registrations');
        Schema::table($registrationsTable, function (Blueprint $t) use ($registrationsTable): void {
            if (! Schema::hasColumn($registrationsTable, 'registration_group_id')) {
                $t->uuid('registration_group_id')->nullable()->index()->after('occurrence_id');
            }
            if (! Schema::hasColumn($registrationsTable, 'seat_category_id')) {
                $t->uuid('seat_category_id')->nullable()->index()->after('registration_group_id');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('events.database.tables.seat_categories', 'event_seat_categories'));
        Schema::dropIfExists(config('events.database.tables.registration_groups', 'event_registration_groups'));

        $registrationsTable = (string) config('events.database.tables.registrations', 'event_registrations');
        Schema::table($registrationsTable, fn (Blueprint $t) => $t->dropColumn(['registration_group_id', 'seat_category_id']));
    }
};
