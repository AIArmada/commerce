<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $database = config('docs.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'docs_';
        $tables = $database['tables'] ?? [];

        $docsTable = $tables['docs'] ?? $tablePrefix . 'docs';
        $workflowStepsTable = $tables['workflow_steps'] ?? $tablePrefix . 'workflow_steps';
        $emailsTable = $tables['doc_emails'] ?? $tablePrefix . 'emails';
        $paymentsTable = $tables['doc_payments'] ?? $tablePrefix . 'payments';
        $approvalsTable = $tables['doc_approvals'] ?? $tablePrefix . 'approvals';
        $einvoiceTable = $tables['doc_einvoice_submissions'] ?? $tablePrefix . 'einvoice_submissions';
        $statusTable = $tables['doc_status_histories'] ?? $tablePrefix . 'doc_status_histories';
        $versionsTable = $tables['doc_versions'] ?? $tablePrefix . 'versions';

        Schema::table($docsTable, function (Blueprint $table): void {
            $table->timestampTz('sent_at')->nullable()->after('paid_at');
            $table->timestampTz('cancelled_at')->nullable()->after('sent_at');
            $table->timestampTz('refunded_at')->nullable()->after('cancelled_at');
            $table->timestampTz('overdue_at')->nullable()->after('refunded_at');
        });

        Schema::table($workflowStepsTable, function (Blueprint $table): void {
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('timed_out_at')->nullable();
            $table->timestampTz('escalated_at')->nullable();
        });

        Schema::table($emailsTable, function (Blueprint $table): void {
            $table->timestampTz('delivered_at')->nullable()->after('sent_at');
            $table->timestampTz('failed_at')->nullable()->after('delivered_at');
        });

        Schema::table($paymentsTable, function (Blueprint $table): void {
            $table->string('status', 50)->default('paid')->after('id');
            $table->timestampTz('refunded_at')->nullable()->after('paid_at');
        });

        Schema::table($approvalsTable, function (Blueprint $table): void {
            $table->timestampTz('expired_at')->nullable()->after('rejected_at');
        });

        Schema::table($einvoiceTable, function (Blueprint $table): void {
            $table->timestampTz('completed_at')->nullable()->after('validated_at');
            $table->timestampTz('failed_at')->nullable()->after('completed_at');
        });

        Schema::table($statusTable, function (Blueprint $table): void {
            $table->string('changed_by_type', 50)->default('user')->after('changed_by');
        });

        // Drop updated_at from doc_status_histories (immutable) and doc_versions (immutable)
        Schema::table($statusTable, function (Blueprint $table): void {
            $table->dropColumn('updated_at');
        });

        Schema::table($versionsTable, function (Blueprint $table): void {
            $table->dropColumn('updated_at');
        });
    }
};
