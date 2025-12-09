<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('jnt.database.table_prefix', 'jnt_') . 'orders';

        Schema::table($tableName, function (Blueprint $table): void {
            // nullableMorphs already creates an index on owner_type and owner_id
            $table->nullableMorphs('owner');
        });
    }

    public function down(): void
    {
        $tableName = config('jnt.database.table_prefix', 'jnt_') . 'orders';

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['owner_type', 'owner_id']);
            $table->dropMorphs('owner');
        });
    }
};
