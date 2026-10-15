<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addressing_reference_snapshots', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('scope', 16); // baseline, local, remote
            $table->string('entity_type', 48);
            $table->char('key_hash', 64);
            $table->text('natural_key');
            $table->char('checksum', 64);
            $table->string('revision', 80)->nullable();
            $table->json('payload');
            $table->uuid('generation');
            $table->timestamps();
            $table->unique(['scope', 'entity_type', 'key_hash'], 'addr_ref_snapshot_identity');
            $table->index(['scope', 'generation'], 'addr_ref_snapshot_generation');
        });

        Schema::create('addressing_reference_settings', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('addressing_reference_changes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('direction', 16); // contribution, delivery
            $table->string('entity_type', 48);
            $table->char('key_hash', 64);
            $table->text('natural_key');
            $table->string('operation', 24); // create, update, delete, retire, conflict, reconciled
            $table->string('decision', 16)->default('pending');
            $table->char('fingerprint', 64);
            $table->uuid('scan_generation');
            $table->json('baseline_payload')->nullable();
            $table->string('baseline_revision', 80)->nullable();
            $table->json('local_payload')->nullable();
            $table->json('remote_payload')->nullable();
            $table->text('notes')->nullable();
            $table->string('reviewed_by', 191)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['direction', 'entity_type', 'key_hash'], 'addr_ref_change_identity');
            $table->index(['direction', 'operation', 'decision'], 'addr_ref_change_filters');
        });
        Schema::create('addressing_reference_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 24);
            $table->string('baseline_revision', 80);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('record_count');
            $table->string('status', 24)->default('exported');
            $table->timestamp('exported_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addressing_reference_exports');
        Schema::dropIfExists('addressing_reference_changes');
        Schema::dropIfExists('addressing_reference_settings');
        Schema::dropIfExists('addressing_reference_snapshots');
    }
};
