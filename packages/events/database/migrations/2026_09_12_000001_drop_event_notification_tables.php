<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = (string) env('EVENTS_TABLE_PREFIX', '');
        $batchTable = (string) env('EVENTS_TABLE_NOTIFICATION_BATCHES', $tablePrefix . 'event_notification_batches');
        $deliveryTable = (string) env('EVENTS_TABLE_NOTIFICATION_DELIVERIES', $tablePrefix . 'event_notification_deliveries');
        $schema = Schema::getConnection()->getSchemaBuilder();

        if ($schema->hasTable($deliveryTable)) {
            $schema->drop($deliveryTable);
        }

        if ($schema->hasTable($batchTable)) {
            $schema->drop($batchTable);
        }
    }
};
