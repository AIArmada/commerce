<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /** @var list<array{table: string, columns: list<string>, name: string}> $indexes */
        $indexes = [
            [
                'table' => (string) config('engagement.database.tables.follows', 'follows'),
                'columns' => [
                    'follower_type',
                    'follower_id',
                    'followable_type',
                    'followable_id',
                    'owner_type',
                    'owner_id',
                ],
                'name' => 'engagement_follows_actor_subject_unique',
            ],
            [
                'table' => (string) config('engagement.database.tables.bookmarks', 'bookmarks'),
                'columns' => [
                    'bookmarker_type',
                    'bookmarker_id',
                    'bookmarkable_type',
                    'bookmarkable_id',
                    'owner_type',
                    'owner_id',
                ],
                'name' => 'engagement_bookmarks_actor_subject_unique',
            ],
            [
                'table' => (string) config('engagement.database.tables.responses', 'responses'),
                'columns' => [
                    'responder_type',
                    'responder_id',
                    'respondable_type',
                    'respondable_id',
                    'owner_type',
                    'owner_id',
                ],
                'name' => 'engagement_responses_actor_subject_unique',
            ],
            [
                'table' => (string) config('engagement.database.tables.reactions', 'reactions'),
                'columns' => [
                    'reactor_type',
                    'reactor_id',
                    'reactable_type',
                    'reactable_id',
                    'reaction_type',
                    'owner_type',
                    'owner_id',
                ],
                'name' => 'engagement_reactions_actor_subject_type_unique',
            ],
            [
                'table' => (string) config('engagement.database.tables.shares', 'engagement_shares'),
                'columns' => ['share_token'],
                'name' => 'engagement_shares_share_token_unique',
            ],
        ];

        foreach ($indexes as $index) {
            $this->addUniqueIndex($index['table'], $index['columns'], $index['name']);
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function addUniqueIndex(string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach ($columns as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                continue;
            }

            throw new RuntimeException(sprintf(
                'Engagement uniqueness migration cannot run because [%s] is missing column [%s].',
                $tableName,
                $column,
            ));
        }

        if (Schema::hasIndex($tableName, $indexName)
            || Schema::hasIndex($tableName, $columns, 'unique')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->unique($columns, $indexName);
        });
    }
};
