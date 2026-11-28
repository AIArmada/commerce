<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('membership.database.tables.invitations', 'membership_invitations');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->string('channel')->index();
            $table->string('recipient');
            $table->string('role');
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending')->index();
            $table->foreignUuid('invited_by');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('expired_at')->nullable()->index();
            $table->timestampTz('accepted_at')->nullable();
            $table->foreignUuid('accepted_by')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignUuid('revoked_by')->nullable();
            $table->timestampTz('last_state_change_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['subject_type', 'subject_id']);
            $table->index('recipient');
            $table->index('invited_by');
        });
    }
};
