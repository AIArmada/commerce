<?php

/**
 * Creates the engagement_follows table.
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
        $tableName = config('engagement.database.tables.follows', 'follows');

        $connection = Schema::getConnection();
        $driver = ConnectionDriver::name($connection);
        $isMysqlFamily = in_array($driver, ['mysql', 'mariadb'], true);
        $isMaria = ConnectionDriver::isMariaDb($connection);
        $identityCollation = $isMaria ? 'utf8mb4_nopad_bin' : ($isMysqlFamily ? 'utf8mb4_0900_bin' : null);
        $columns = ['follower_type', 'follower_id', 'followable_type', 'followable_id', 'owner_type', 'owner_id'];
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
            $followerType = $table->string('follower_type')->index();
            $followerId = $table->uuid('follower_id')->index();
            $table->index(['follower_type', 'follower_id']);
            $followableType = $table->string('followable_type')->index();
            $followableId = $table->uuid('followable_id')->index();
            $table->index(['followable_type', 'followable_id']);
            $table->string('status')->index();
            $table->string('notification_level')->nullable()->index();
            $table->{$jsonType}('notification_preferences')->nullable();
            $table->timestampTz('followed_at')->nullable()->index();
            $table->timestampTz('muted_at')->nullable();
            $table->timestampTz('unfollowed_at')->nullable()->index();
            $table->timestampTz('blocked_at')->nullable();
            $table->string('source')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $ownerType = $table->string('owner_type', 191)->nullable();
            $ownerId = $table->uuid('owner_id')->nullable();
            $table->index(['owner_type', 'owner_id']);
            if ($identityCollation !== null) {
                foreach ([$followerType, $followerId, $followableType, $followableId, $ownerType, $ownerId] as $column) {
                    $column->charset('utf8mb4')->collation($identityCollation);
                }
            }
            $table->timestampsTz();
            if (! $isMysqlFamily) {
                $table->index(['follower_type', 'follower_id', 'followable_type', 'followable_id', 'status']);
                $table->unique(['follower_type', 'follower_id', 'followable_type', 'followable_id', 'owner_type', 'owner_id'], 'engagement_follows_actor_subject_unique');
            }

            if ($isMysqlFamily) {
                $table->text($packedColumn)->nullable()->virtualAs($packed)->invisible();
            }
            if ($mysqlIdentity !== null) {
                $table->string('actor_subject_unique_identity', 64)->nullable()->virtualAs($mysqlIdentity)->invisible();
                $table->unique('actor_subject_unique_identity', 'engagement_follows_actor_subject_unique');
                $table->rawIndex(sprintf(
                    '%s(191), %s, %s(191), %s, %s(191)',
                    $grammar->wrap('follower_type'),
                    $grammar->wrap('follower_id'),
                    $grammar->wrap('followable_type'),
                    $grammar->wrap('followable_id'),
                    $grammar->wrap('status'),
                ), 'engagement_follows_actor_subject_status_idx');
            }
        });

    }
};
