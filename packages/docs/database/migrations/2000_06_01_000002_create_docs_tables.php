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

        $templatesTable = $tables['doc_templates'] ?? $tablePrefix . 'doc_templates';
        $docsTable = $tables['docs'] ?? $tablePrefix . 'docs';
        $shareLinksTable = $tables['doc_share_links'] ?? $tablePrefix . 'doc_share_links';
        $statusTable = $tables['doc_status_histories'] ?? $tablePrefix . 'doc_status_histories';

        Schema::create($templatesTable, function (Blueprint $table) use ($templatesTable): void {
            $jsonType = (string) commerce_json_column_type('docs', 'jsonb');
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('doc_type')->default('invoice');
            $table->boolean('is_default')->default(false);
            $table->{$jsonType}('layout');
            $table->{$jsonType}('settings')->nullable();
            $table->timestampsTz();

            $table->index('is_default', $templatesTable . '_is_default_index');
            $table->index('doc_type', $templatesTable . '_doc_type_index');
            $table->unique(['owner_type', 'owner_id', 'slug'], $templatesTable . '_owner_slug_unique');
        });

        Schema::create($docsTable, function (Blueprint $table) use ($docsTable): void {
            $jsonType = (string) commerce_json_column_type('docs', 'jsonb');
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('doc_number')->unique($docsTable . '_doc_number_unique');
            $table->string('doc_type')->default('invoice');
            $table->foreignUuid('doc_template_id')->nullable();
            $table->nullableUuidMorphs('docable');
            $table->string('status')->default('draft');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->timestampTz('overdue_at')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->{$jsonType}('body')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->{$jsonType}('customer_data')->nullable();
            $table->{$jsonType}('company_data')->nullable();
            $table->{$jsonType}('items')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestampsTz();

            $table->index('doc_type', $docsTable . '_doc_type_index');
            $table->index('status', $docsTable . '_status_index');
            $table->index('issue_date', $docsTable . '_issue_date_index');
            $table->index('due_date', $docsTable . '_due_date_index');
        });

        Schema::create($shareLinksTable, function (Blueprint $table) use ($shareLinksTable): void {
            $jsonType = (string) commerce_json_column_type('docs', 'jsonb');
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->foreignUuid('doc_id');
            $table->string('token_hash', 64)->unique($shareLinksTable . '_token_hash_unique');
            $table->{$jsonType}('allowed_actions');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestampTz('last_accessed_at')->nullable();
            $table->timestampsTz();

            $table->index('doc_id', $shareLinksTable . '_doc_id_index');
            $table->index('expires_at', $shareLinksTable . '_expires_at_index');
            $table->index('revoked_at', $shareLinksTable . '_revoked_at_index');
        });

        Schema::create($statusTable, function (Blueprint $table) use ($statusTable): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->foreignUuid('doc_id');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->string('changed_by')->nullable();
            $table->string('changed_by_type', 50)->default('user');
            $table->timestampTz('created_at')->nullable();

            $table->index('doc_id', $statusTable . '_doc_id_index');
            $table->index('status', $statusTable . '_status_index');
        });
    }

    public function down(): void
    {
        $database = config('docs.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'docs_';
        $tables = $database['tables'] ?? [];

        $templatesTable = $tables['doc_templates'] ?? $tablePrefix . 'doc_templates';
        $docsTable = $tables['docs'] ?? $tablePrefix . 'docs';
        $shareLinksTable = $tables['doc_share_links'] ?? $tablePrefix . 'doc_share_links';
        $statusTable = $tables['doc_status_histories'] ?? $tablePrefix . 'doc_status_histories';

        Schema::dropIfExists($statusTable);
        Schema::dropIfExists($shareLinksTable);
        Schema::dropIfExists($docsTable);
        Schema::dropIfExists($templatesTable);
    }
};
