<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('events.database.tables.event_registrations', 'event_registrations');
        $jsonType = config('events.database.json_column_type', 'jsonb');

        if (! Schema::hasColumn($tableName, 'parent_registration_id')) {
            Schema::table($tableName, function (Blueprint $table) use ($jsonType): void {
                $table->string('parent_registration_id', 36)->nullable()->index();

                $table->boolean('is_bundle_root')->default(false)->index();

                $table->{$jsonType}('pass_entitlements')->nullable();
            });
        }
    }
};
