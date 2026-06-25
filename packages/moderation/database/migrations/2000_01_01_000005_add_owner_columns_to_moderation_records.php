<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            config('moderation.database.tables.blocks', 'moderation_blocks'),
            config('moderation.database.tables.moderation_actions', 'moderation_actions'),
        ] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $hasOwnerType = Schema::hasColumn($tableName, 'owner_type');
            $hasOwnerId = Schema::hasColumn($tableName, 'owner_id');

            if ($hasOwnerType && $hasOwnerId) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($hasOwnerType, $hasOwnerId): void {
                if (! $hasOwnerType && ! $hasOwnerId) {
                    $table->nullableMorphs('owner');

                    return;
                }

                if (! $hasOwnerType) {
                    $table->string('owner_type')->nullable();
                }

                if (! $hasOwnerId) {
                    match ((string) config('commerce-support.database.morph_key_type', 'uuid')) {
                        'uuid' => $table->uuid('owner_id')->nullable(),
                        'ulid' => $table->ulid('owner_id')->nullable(),
                        default => $table->unsignedBigInteger('owner_id')->nullable(),
                    };
                }
            });
        }
    }
};
