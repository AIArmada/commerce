<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.program_creatives', 'affiliate_program_creatives');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->foreignUuid('program_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // irreversible — program_id cannot be restored to non-nullable
    }
};
