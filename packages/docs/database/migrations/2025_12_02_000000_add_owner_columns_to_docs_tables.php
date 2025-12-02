<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration only runs when docs owner is enabled.
     */
    public function up(): void
    {
        if (! config('docs.owner.enabled', false)) {
            return;
        }

        $docsTable = config('docs.database.tables.docs', 'docs');
        $templatesTable = config('docs.database.tables.doc_templates', 'doc_templates');

        // Add owner columns to docs table
        if (! Schema::hasColumn($docsTable, 'owner_type')) {
            Schema::table($docsTable, function (Blueprint $table): void {
                $table->string('owner_type')->nullable()->after('id')->index();
                $table->string('owner_id')->nullable()->after('owner_type')->index();
            });
        }

        // Add owner columns to doc_templates table
        if (! Schema::hasColumn($templatesTable, 'owner_type')) {
            Schema::table($templatesTable, function (Blueprint $table): void {
                $table->string('owner_type')->nullable()->after('id')->index();
                $table->string('owner_id')->nullable()->after('owner_type')->index();
            });

            // Update unique constraint on slug to be scoped by owner
            Schema::table($templatesTable, function (Blueprint $table): void {
                $table->dropUnique(['slug']);
                $table->unique(['owner_type', 'owner_id', 'slug']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! config('docs.owner.enabled', false)) {
            return;
        }

        $docsTable = config('docs.database.tables.docs', 'docs');
        $templatesTable = config('docs.database.tables.doc_templates', 'doc_templates');

        if (Schema::hasColumn($docsTable, 'owner_type')) {
            Schema::table($docsTable, function (Blueprint $table): void {
                $table->dropColumn(['owner_type', 'owner_id']);
            });
        }

        if (Schema::hasColumn($templatesTable, 'owner_type')) {
            Schema::table($templatesTable, function (Blueprint $table): void {
                $table->dropUnique(['owner_type', 'owner_id', 'slug']);
                $table->unique(['slug']);
                $table->dropColumn(['owner_type', 'owner_id']);
            });
        }
    }
};
