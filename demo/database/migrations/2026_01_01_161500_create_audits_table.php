<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = (string) config('audit.drivers.database.connection', config('database.default'));
        $tableName = (string) config('audit.drivers.database.table', 'audits');
        $morphPrefix = (string) config('audit.user.morph_prefix', 'user');

        if (Schema::connection($connection)->hasTable($tableName)) {
            return;
        }

        Schema::connection($connection)->create($tableName, function (Blueprint $table) use ($morphPrefix): void {
            $table->bigIncrements('id');

            // The demo uses UUID primary keys; store the auditing "user" as UUID morphs.
            $table->nullableUuidMorphs($morphPrefix);

            $table->string('event');

            // Many of our models use UUID primary keys, so auditable_id must be UUID.
            $table->uuidMorphs('auditable');

            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();

            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();

            $table->timestamps();

            $table->index([$morphPrefix.'_id', $morphPrefix.'_type']);
        });
    }

    public function down(): void
    {
        $connection = (string) config('audit.drivers.database.connection', config('database.default'));
        $tableName = (string) config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->dropIfExists($tableName);
    }
};
