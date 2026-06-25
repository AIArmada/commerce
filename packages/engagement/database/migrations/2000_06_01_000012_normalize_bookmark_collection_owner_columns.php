<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('engagement.database.tables.bookmark_collections', 'bookmark_collections');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $hasOwnerType = Schema::hasColumn($tableName, 'owner_type');
        $hasOwnerId = Schema::hasColumn($tableName, 'owner_id');

        Schema::table($tableName, function (Blueprint $table) use ($hasOwnerType, $hasOwnerId): void {
            if (! $hasOwnerType && ! $hasOwnerId) {
                $table->nullableMorphs('owner');

                return;
            }

            if ($hasOwnerType) {
                $table->string('owner_type')->nullable()->change();
            } else {
                $table->string('owner_type')->nullable();
            }

            match ((string) config('commerce-support.database.morph_key_type', 'uuid')) {
                'uuid' => $hasOwnerId
                    ? $table->uuid('owner_id')->nullable()->change()
                    : $table->uuid('owner_id')->nullable(),
                'ulid' => $hasOwnerId
                    ? $table->ulid('owner_id')->nullable()->change()
                    : $table->ulid('owner_id')->nullable(),
                default => $hasOwnerId
                    ? $table->unsignedBigInteger('owner_id')->nullable()->change()
                    : $table->unsignedBigInteger('owner_id')->nullable(),
            };
        });
    }
};
