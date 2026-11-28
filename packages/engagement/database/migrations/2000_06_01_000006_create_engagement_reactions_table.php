<?php

/**
 * Creates the engagement_reactions table.
 * Part of the aiarmada/engagement package.
 *
 * @see https://github.com/aiarmada/engagement/docs
 */
declare(strict_types=1);
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('engagement', 'jsonb');
        $tableName = (string) config('engagement.database.tables.reactions', 'reactions');
        $driver = ConnectionDriver::name(Schema::getConnection());

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException(sprintf('Engagement reactions identity migration cannot run on unsupported database driver [%s].', $driver));
        }

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('reactor_type')->index();
            $table->uuid('reactor_id')->index();
            $table->index(['reactor_type', 'reactor_id']);
            $table->string('reactable_type')->index();
            $table->uuid('reactable_id')->index();
            $table->index(['reactable_type', 'reactable_id']);
            $table->string('reaction_type')->index();
            $table->string('status')->index();
            $table->timestampTz('reacted_at')->nullable()->index();
            $table->timestampTz('removed_at')->nullable()->index();
            $table->string('source')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestampsTz();
            $table->unique(['reactor_type', 'reactor_id', 'reactable_type', 'reactable_id', 'reaction_type', 'owner_type', 'owner_id'], 'engagement_reactions_actor_subject_type_unique');
            $table->index(['reactable_type', 'reactable_id', 'reaction_type', 'status'], 'engagement_reactions_subject_type_status_idx');
        });

        $connection = Schema::getConnection();
        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (reactor_type, reactor_id, reactable_type, reactable_id, reaction_type) WHERE owner_type IS NULL AND owner_id IS NULL',
            $grammar->wrap('engagement_reactions_global_unique'),
            $grammar->wrapTable($tableName),
        ));
    }
};
