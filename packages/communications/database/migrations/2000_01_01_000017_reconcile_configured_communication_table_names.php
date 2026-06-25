<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'batches' => 'communication_batches',
            'threads' => 'communication_threads',
            'recipients' => 'communication_recipients',
            'contents' => 'communication_contents',
            'deliveries' => 'communication_deliveries',
            'attempts' => 'communication_attempts',
            'events' => 'communication_events',
            'templates' => 'communication_templates',
            'template_versions' => 'communication_template_versions',
            'preferences' => 'communication_preferences',
            'suppressions' => 'communication_suppressions',
            'attachments' => 'communication_attachments',
            'references' => 'communication_references',
            'tracking_tokens' => 'communication_tracking_tokens',
        ];

        foreach ($tables as $configKey => $legacyTable) {
            $configuredTable = (string) config(
                "communications.database.tables.{$configKey}",
                $legacyTable,
            );

            if (
                $configuredTable !== $legacyTable
                && Schema::hasTable($legacyTable)
                && ! Schema::hasTable($configuredTable)
            ) {
                Schema::rename($legacyTable, $configuredTable);
            }
        }
    }
};
