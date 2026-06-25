<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tokenLength = max(64, (int) config('membership.invitations.token_length', 64));
        $tableName = (string) config('membership.database.tables.invitations', 'membership_invitations');

        Schema::create($tableName, function (Blueprint $table) use ($tokenLength): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->string('email');
            $table->string('role');
            $table->string('token', $tokenLength)->unique();
            $table->foreignUuid('invited_by');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->foreignUuid('accepted_by')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignUuid('revoked_by')->nullable();
            $table->timestampsTz();

            $table->index(['subject_type', 'subject_id']);
            $table->index('email');
            $table->index('invited_by');
        });
    }
};
