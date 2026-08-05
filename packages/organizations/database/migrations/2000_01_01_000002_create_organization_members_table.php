<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('organizations.database.tables.members', 'organization_members');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('organization_id')->index();
            $table->uuid('user_id')->index();
            $table->string('role')->index();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'user_id']);
        });
    }
};
