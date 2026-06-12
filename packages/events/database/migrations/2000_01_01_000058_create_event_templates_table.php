<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = config('events.database.json_column_type', 'jsonb');

        Schema::create(config('events.database.tables.event_templates', 'event_templates'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->string('templateable_type')->nullable()->index();
            $table->string('templateable_id')->nullable()->index();
            $table->string('code')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('template_type')->index();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->{$jsonType}('payload');
            $table->{$jsonType}('default_scope')->nullable();
            $table->string('created_by_type')->nullable()->index();
            $table->string('created_by_id')->nullable()->index();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
