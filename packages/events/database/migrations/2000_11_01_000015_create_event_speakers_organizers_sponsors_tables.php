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

        Schema::create(config('events.database.tables.speakers', 'event_speakers'), function (Blueprint $t) use ($j): void {
            $t->uuid('id')->primary();
            $t->nullableUuidMorphs('owner');
            $t->nullableMorphs('assignable');
            $t->nullableMorphs('person');
            $t->string('display_name')->nullable();
            $t->text('biography')->nullable();
            $t->string('role_key')->nullable();
            $t->unsignedInteger('order_column')->nullable();
            $t->string('visibility', 32)->default('public');
            $t->{$j}('metadata')->nullable();
            $t->timestampsTz();
            $t->index(['assignable_type', 'assignable_id', 'order_column']);
        });

        Schema::create(config('events.database.tables.organizers', 'event_organizers'), function (Blueprint $t) use ($j): void {
            $t->uuid('id')->primary();
            $t->nullableUuidMorphs('owner');
            $t->nullableMorphs('assignable');
            $t->nullableMorphs('reference');
            $t->string('display_name')->nullable();
            $t->string('logo_url')->nullable();
            $t->string('contact_email')->nullable();
            $t->string('website_url')->nullable();
            $t->unsignedInteger('order_column')->nullable();
            $t->{$j}('metadata')->nullable();
            $t->timestampsTz();
            $t->index(['assignable_type', 'assignable_id', 'order_column']);
        });

        Schema::create(config('events.database.tables.sponsors', 'event_sponsors'), function (Blueprint $t) use ($j): void {
            $t->uuid('id')->primary();
            $t->nullableUuidMorphs('owner');
            $t->nullableMorphs('assignable');
            $t->nullableMorphs('reference');
            $t->string('tier')->nullable();
            $t->string('display_name')->nullable();
            $t->string('logo_url')->nullable();
            $t->string('website_url')->nullable();
            $t->unsignedInteger('order_column')->nullable();
            $t->{$j}('metadata')->nullable();
            $t->timestampsTz();
            $t->index(['assignable_type', 'assignable_id', 'order_column']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('events.database.tables.speakers', 'event_speakers'));
        Schema::dropIfExists(config('events.database.tables.organizers', 'event_organizers'));
        Schema::dropIfExists(config('events.database.tables.sponsors', 'event_sponsors'));
    }
};
