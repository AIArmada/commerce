<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonColumnType = commerce_json_column_type('links', 'jsonb');

        Schema::table(config('links.database.tables.links', 'tracked_links'), function (Blueprint $table) use ($jsonColumnType): void {
            $table->nullableUuidMorphs('subject');
            $table->{$jsonColumnType}('parameters')->nullable();
            $table->boolean('require_signature')->default(false);
        });

        Schema::table(config('links.database.tables.clicks', 'tracked_link_clicks'), function (Blueprint $table): void {
            $table->nullableUuidMorphs('subject');
        });
    }
};
