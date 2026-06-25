<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('moderation.database.tables.blocks', 'moderation_blocks');

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'blockable_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->uuid('blockable_id')->nullable()->change();
        });
    }
};
