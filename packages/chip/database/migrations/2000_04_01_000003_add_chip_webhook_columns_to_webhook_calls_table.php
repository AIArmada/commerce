<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\SupportServiceProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('chip.webhooks.enabled', true)) {
            return;
        }

        $this->createSharedWebhookCallsTableIfMissing();

        if (! Schema::hasTable('webhook_calls')) {
            return;
        }

        $jsonType = (string) commerce_json_column_type('chip', 'jsonb');

        Schema::table('webhook_calls', function (Blueprint $table) use ($jsonType): void {
            if (! Schema::hasColumn('webhook_calls', 'title')) {
                $table->string('title', 100)->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'events')) {
                $table->{$jsonType}('events')->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'callback')) {
                $table->string('callback', 500)->nullable()->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'all_events')) {
                $table->boolean('all_events')->default(false);
            }

            if (! Schema::hasColumn('webhook_calls', 'public_key')) {
                $table->text('public_key')->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'event_type')) {
                $table->string('event_type')->nullable()->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'event')) {
                $table->string('event')->nullable()->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'signature')) {
                $table->text('signature')->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'verified')) {
                $table->boolean('verified')->default(false)->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'processed')) {
                $table->boolean('processed')->default(false)->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'processing_error')) {
                $table->text('processing_error')->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'processing_attempts')) {
                $table->unsignedInteger('processing_attempts')->default(0);
            }

            if (! Schema::hasColumn('webhook_calls', 'status')) {
                $table->string('status')->default('pending')->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique();
            }

            if (! Schema::hasColumn('webhook_calls', 'retry_count')) {
                $table->unsignedInteger('retry_count')->default(0)->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'last_retry_at')) {
                $table->timestampTz('last_retry_at')->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'last_error')) {
                $table->text('last_error')->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'processing_time_ms')) {
                $table->decimal('processing_time_ms', 10, 3)->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'ip_address')) {
                $table->string('ip_address')->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'created_on')) {
                $table->integer('created_on')->nullable()->index();
            }

            if (! Schema::hasColumn('webhook_calls', 'updated_on')) {
                $table->integer('updated_on')->nullable();
            }

            if (! Schema::hasColumn('webhook_calls', 'owner_type') && ! Schema::hasColumn('webhook_calls', 'owner_id')) {
                $table->nullableMorphs('owner');
            }

            $this->addIndexIfMissing($table, ['event_type', 'processed'], 'webhook_calls_event_type_processed_idx');
            $this->addIndexIfMissing($table, ['verified', 'processed'], 'webhook_calls_verified_processed_idx');
            $this->addIndexIfMissing($table, ['status', 'retry_count'], 'webhook_calls_status_retry_count_idx');
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(Blueprint $table, array $columns, string $name): void
    {
        if (! Schema::hasIndex($table->getTable(), $name)) {
            $table->index($columns, $name);
        }
    }

    private function createSharedWebhookCallsTableIfMissing(): void
    {
        if (Schema::hasTable('webhook_calls')) {
            return;
        }

        if (! class_exists(SupportServiceProvider::class)) {
            return;
        }

        $providerFile = (new ReflectionClass(SupportServiceProvider::class))->getFileName();

        if (! is_string($providerFile) || $providerFile === '') {
            return;
        }

        $migrationPath = dirname($providerFile, 2) . '/database/migrations/1970_01_01_000004_create_webhook_calls_table.php.stub';

        if (! is_file($migrationPath)) {
            return;
        }

        $migration = require $migrationPath;
        $migration->up();
    }
};
