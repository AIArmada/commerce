<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = (string) commerce_json_column_type('referrals', (string) config('referrals.database.json_column_type', 'jsonb'));

        Schema::create(config('referrals.database.tables.programs', 'referral_programs'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();

            $ownerTypeColumn = (string) config('referrals.owner.owner_type_column', 'owner_type');
            $ownerIdColumn = (string) config('referrals.owner.owner_id_column', 'owner_id');

            if ($ownerTypeColumn === 'owner_type' && $ownerIdColumn === 'owner_id') {
                $table->nullableUuidMorphs('owner');
            } else {
                $table->string($ownerTypeColumn)->nullable();
                $table->uuid($ownerIdColumn)->nullable();
                $table->index([$ownerTypeColumn, $ownerIdColumn]);
            }

            $table->string('name', 191);
            $table->string('slug', 191)->index();
            $table->string('status', 16)->default('draft')->index();
            $table->string('qualifier_outcome', 64);
            $table->unsignedInteger('invite_expiry_days')->nullable();
            $table->unsignedInteger('max_invites_per_referrer')->nullable();
            $table->unsignedInteger('max_qualified_per_referrer_per_month')->nullable();
            $table->{$jsonType}('referrer_reward')->nullable();
            $table->{$jsonType}('referee_reward')->nullable();
            $table->char('currency', 3);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
