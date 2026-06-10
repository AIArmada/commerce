<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = (string) config('docs.database.table_prefix', 'docs_');

        Schema::table($prefix, function (Blueprint $table): void {
            $table->timestampTz('sent_at')->nullable()->after('paid_at');
            $table->timestampTz('cancelled_at')->nullable()->after('sent_at');
            $table->timestampTz('refunded_at')->nullable()->after('cancelled_at');
            $table->timestampTz('overdue_at')->nullable()->after('refunded_at');
        });

        Schema::table($prefix . '_workflow_steps', function (Blueprint $table): void {
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('timed_out_at')->nullable();
            $table->timestampTz('escalated_at')->nullable();
        });

        Schema::table($prefix . '_emails', function (Blueprint $table): void {
            $table->timestampTz('delivered_at')->nullable()->after('sent_at');
            $table->timestampTz('failed_at')->nullable()->after('delivered_at');
        });

        Schema::table($prefix . '_payments', function (Blueprint $table): void {
            $table->string('status', 50)->default('paid')->after('id');
            $table->timestampTz('refunded_at')->nullable()->after('paid_at');
        });

        Schema::table($prefix . '_approvals', function (Blueprint $table): void {
            $table->timestampTz('expired_at')->nullable()->after('rejected_at');
        });

        Schema::table($prefix . '_einvoice_submissions', function (Blueprint $table): void {
            $table->timestampTz('completed_at')->nullable()->after('validated_at');
            $table->timestampTz('failed_at')->nullable()->after('completed_at');
        });

        Schema::table($prefix . '_doc_status_histories', function (Blueprint $table): void {
            $table->string('changed_by_type', 50)->default('user')->after('changed_by');
        });

        // Drop updated_at from doc_status_histories (immutable) and doc_versions (immutable)
        Schema::table($prefix . '_doc_status_histories', function (Blueprint $table): void {
            $table->dropColumn('updated_at');
        });

        Schema::table($prefix . '_doc_versions', function (Blueprint $table): void {
            $table->dropColumn('updated_at');
        });
    }
};
