<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('engagement.database.tables.engagement_counters', 'engagement_counters');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropUnique('engagement_counters_unique_idx');
            $blueprint->unique([
                'subject_type',
                'subject_id',
                'counter_type',
                'counter_key',
                'owner_type',
                'owner_id',
            ], 'engagement_counters_unique_idx');
        });
    }
};
