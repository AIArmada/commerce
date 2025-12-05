<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.table_names.network', 'affiliate_network');
        $affiliatesTable = config('affiliates.table_names.affiliates', 'affiliates');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->foreignUuid('ancestor_id');
            $table->foreignUuid('descendant_id');
            $table->integer('depth');

            $table->primary(['ancestor_id', 'descendant_id']);
            $table->index(['descendant_id', 'depth']);
            $table->index(['ancestor_id', 'depth']);
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.table_names.network', 'affiliate_network');
        Schema::dropIfExists($tableName);
    }
};
