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
            if (! Schema::hasColumn($eventsTable, 'cancelled_at')) {
                $table->timestampTz('cancelled_at')->nullable()->after('published_at');
            }

            if (! Schema::hasColumn($eventsTable, 'postponed_at')) {
                $table->timestampTz('postponed_at')->nullable()->after('cancelled_at');
            }

            if (! Schema::hasColumn($eventsTable, 'delayed_at')) {
                $table->timestampTz('delayed_at')->nullable()->after('postponed_at');
            }

            if (! Schema::hasColumn($eventsTable, 'last_state_change_actor_type')) {
                $table->nullableUuidMorphs('last_state_change_actor');
            } elseif (! Schema::hasColumn($eventsTable, 'last_state_change_actor_id')) {
                $table->uuid('last_state_change_actor_id')->nullable()->after('last_state_change_actor_type');
            }

            if (! Schema::hasColumn($eventsTable, 'last_state_change_note')) {
                $table->text('last_state_change_note')->nullable()->after('last_state_change_actor_id');
            }

            if (! Schema::hasColumn($eventsTable, 'last_state_change_at')) {
                $table->timestampTz('last_state_change_at')->nullable()->after('last_state_change_note');
            }

            if (! $this->indexExists($eventsTable, 'events_status_cancelled_at_index')) {
                $table->index(['status', 'cancelled_at'], 'events_status_cancelled_at_index');
            }

            if (! $this->indexExists($eventsTable, 'events_status_postponed_at_index')) {
                $table->index(['status', 'postponed_at'], 'events_status_postponed_at_index');
            }

            if (! $this->indexExists($eventsTable, 'events_status_delayed_at_index')) {
                $table->index(['status', 'delayed_at'], 'events_status_delayed_at_index');
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
            if ($this->indexExists($eventsTable, 'events_status_delayed_at_index')) {
                $table->dropIndex('events_status_delayed_at_index');
            }

            if ($this->indexExists($eventsTable, 'events_status_postponed_at_index')) {
                $table->dropIndex('events_status_postponed_at_index');
            }

            if ($this->indexExists($eventsTable, 'events_status_cancelled_at_index')) {
                $table->dropIndex('events_status_cancelled_at_index');
            }

            $columns = array_filter([
                Schema::hasColumn($eventsTable, 'last_state_change_at') ? 'last_state_change_at' : null,
                Schema::hasColumn($eventsTable, 'last_state_change_note') ? 'last_state_change_note' : null,
                Schema::hasColumn($eventsTable, 'last_state_change_actor_id') ? 'last_state_change_actor_id' : null,
                Schema::hasColumn($eventsTable, 'last_state_change_actor_type') ? 'last_state_change_actor_type' : null,
                Schema::hasColumn($eventsTable, 'delayed_at') ? 'delayed_at' : null,
                Schema::hasColumn($eventsTable, 'postponed_at') ? 'postponed_at' : null,
                Schema::hasColumn($eventsTable, 'cancelled_at') ? 'cancelled_at' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
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
