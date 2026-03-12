<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.links', 'affiliate_links');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::table($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->string('subject_type', 64)->nullable()->after('sub_id_3');
            $table->string('subject_identifier')->nullable()->after('subject_type');
            $table->string('subject_instance', 64)->nullable()->after('subject_identifier');
            $table->string('subject_title_snapshot', 200)->nullable()->after('subject_instance');
            $table->{$jsonType}('subject_metadata')->nullable()->after('subject_title_snapshot');

            $table->index(['subject_type', 'subject_identifier'], 'affiliate_links_subject_idx');
        });
    }
};
