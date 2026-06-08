<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $eventsTable = (string) config('events.database.tables.events', 'events');

        if (! Schema::hasTable($eventsTable)) {
            return;
        }

        Schema::table($eventsTable, function (Blueprint $table) use ($eventsTable): void {
            if (! Schema::hasColumn($eventsTable, 'registration_required')) {
                $table->boolean('registration_required')->default(false)->after('search_keywords');
            }

            if (! $this->indexExists($eventsTable, 'events_registration_required_index')) {
                $table->index(['status', 'registration_required'], 'events_registration_required_index');
            }
        });
    }

    public function down(): void
    {
        $eventsTable = (string) config('events.database.tables.events', 'events');

        if (! Schema::hasTable($eventsTable)) {
            return;
        }

        Schema::table($eventsTable, function (Blueprint $table) use ($eventsTable): void {
            if ($this->indexExists($eventsTable, 'events_registration_required_index')) {
                $table->dropIndex('events_registration_required_index');
            }

            if (Schema::hasColumn($eventsTable, 'registration_required')) {
                $table->dropColumn('registration_required');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $result = $connection->selectOne(
                'SELECT COUNT(*) AS count FROM sqlite_master WHERE type = ? AND name = ?',
                ['index', $indexName],
            );

            return ((int) ($result->count ?? 0)) > 0;
        }

        $result = $connection->selectOne(
            'SELECT COUNT(*) AS count FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ?',
            ['public', $table, $indexName],
        );

        return ((int) ($result->count ?? 0)) > 0;
    }
};
