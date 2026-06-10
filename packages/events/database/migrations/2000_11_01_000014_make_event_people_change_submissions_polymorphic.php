<?php

declare(strict_types=1);

use AIArmada\Events\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['people', 'change_notices', 'submissions'] as $configKey) {
            $tableName = (string) config("events.database.tables.{$configKey}", $configKey);
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'assignable_type')) {
                    $table->string('assignable_type')->nullable()->after('owner_id');
                    $table->uuid('assignable_id')->nullable()->after('assignable_type');
                }
            });

            DB::table($tableName)->whereNotNull('event_id')->update([
                'assignable_type' => Event::class,
                'assignable_id' => DB::raw('event_id'),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['people', 'change_notices', 'submissions'] as $configKey) {
            $tableName = (string) config("events.database.tables.{$configKey}", $configKey);
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'assignable_type')) {
                    $table->dropColumn(['assignable_type', 'assignable_id']);
                }
            });
        }
    }
};
