<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('communications', 'jsonb');
        $tableName = config('communications.database.tables.preferences', 'communication_preferences');

        $isMysqlFamily = in_array(ConnectionDriver::name(Schema::getConnection()), ['mysql', 'mariadb'], true);
        $grammar = Schema::getConnection()->getQueryGrammar();

        Schema::create($tableName, function (Blueprint $table) use ($jsonType, $isMysqlFamily, $grammar): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('recipient_type');
            $table->string('recipient_id');
            $table->string('channel')->nullable();
            $table->string('category')->nullable();
            $table->string('scope_type')->nullable();
            $table->string('scope_key')->nullable();
            $table->string('locale')->nullable();
            $table->string('timezone')->nullable();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('quiet_hours_timezone')->nullable();
            $table->timestampTz('enabled_at')->nullable()->index();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampTz('opted_in_at')->nullable();
            $table->timestampTz('opted_out_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->string('source')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['recipient_type', 'recipient_id']);

            if (! $isMysqlFamily) {
                $table->index(['owner_type', 'owner_id', 'channel', 'category']);
            } else {
                $table->rawIndex(sprintf(
                    '%s(191), %s, %s(191), %s(191)',
                    $grammar->wrap('owner_type'),
                    $grammar->wrap('owner_id'),
                    $grammar->wrap('channel'),
                    $grammar->wrap('category'),
                ), 'communication_preferences_owner_channel_category_idx');
            }

            $table->index(['scope_type', 'scope_key']);
        });

    }
};
