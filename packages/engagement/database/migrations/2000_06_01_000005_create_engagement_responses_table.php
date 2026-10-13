<?php

/**
 * Creates the engagement_responses table.
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
        $tableName = config('engagement.database.tables.responses', 'responses');

        $connection = Schema::getConnection();
        $driver = ConnectionDriver::name($connection);
        $isMysqlFamily = in_array($driver, ['mysql', 'mariadb'], true);
        $isMaria = ConnectionDriver::isMariaDb($connection);
        $identityCollation = $isMaria ? 'utf8mb4_nopad_bin' : ($isMysqlFamily ? 'utf8mb4_0900_bin' : null);
        $columns = ['responder_type', 'responder_id', 'respondable_type', 'respondable_id', 'owner_type', 'owner_id'];
        $mysqlIdentity = null;
        $packedColumn = 'actor_subject_unique_packed';
        $packed = null;
        $grammar = $connection->getQueryGrammar();

        if ($isMysqlFamily) {
            $packed = 'CONCAT(' . implode(', ', array_merge(...array_map(
                static fn (string $column): array => [
                    sprintf('CHAR_LENGTH(%s)', $grammar->wrap($column)),
                    "':'",
                    $grammar->wrap($column),
                    "'|'",
                ],
                $columns,
            ))) . ')';
            $mysqlIdentity = sprintf('SHA2(%s, 256)', $grammar->wrap($packedColumn));
        }

        Schema::create($tableName, function (Blueprint $table) use ($jsonType, $isMysqlFamily, $mysqlIdentity, $grammar, $identityCollation, $packedColumn, $packed): void {
            $table->uuid('id')->primary();
            $responderType = $table->string('responder_type')->index();
            $responderId = $table->uuid('responder_id')->index();
            $table->index(['responder_type', 'responder_id']);
            $respondableType = $table->string('respondable_type')->index();
            $respondableId = $table->uuid('respondable_id')->index();
            $table->index(['respondable_type', 'respondable_id']);
            $table->string('response_type')->index();
            $table->string('status')->index();
            $table->string('visibility')->index();
            $table->timestampTz('responded_at')->nullable()->index();
            $table->timestampTz('changed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable()->index();
            $table->timestampTz('expires_at')->nullable();
            $table->string('source')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $ownerType = $table->string('owner_type', 191)->nullable();
            $ownerId = $table->uuid('owner_id')->nullable();
            $table->index(['owner_type', 'owner_id']);
            if ($identityCollation !== null) {
                foreach ([$responderType, $responderId, $respondableType, $respondableId, $ownerType, $ownerId] as $column) {
                    $column->charset('utf8mb4')->collation($identityCollation);
                }
            }
            $table->timestampsTz();
            if (! $isMysqlFamily) {
                $table->unique(['responder_type', 'responder_id', 'respondable_type', 'respondable_id', 'owner_type', 'owner_id'], 'engagement_responses_actor_subject_unique');
                $table->index(['respondable_type', 'respondable_id', 'response_type', 'status'], 'engagement_responses_subject_type_status_idx');
            }

            if ($isMysqlFamily) {
                $table->text($packedColumn)->nullable()->virtualAs($packed)->invisible();
            }
            if ($mysqlIdentity !== null) {
                $table->string('actor_subject_unique_identity', 64)->nullable()->virtualAs($mysqlIdentity)->invisible();
                $table->unique('actor_subject_unique_identity', 'engagement_responses_actor_subject_unique');
                $table->rawIndex(sprintf(
                    '%s(191), %s, %s(191), %s(191)',
                    $grammar->wrap('respondable_type'),
                    $grammar->wrap('respondable_id'),
                    $grammar->wrap('response_type'),
                    $grammar->wrap('status'),
                ), 'engagement_responses_subject_type_status_idx');
            }
        });

    }
};
