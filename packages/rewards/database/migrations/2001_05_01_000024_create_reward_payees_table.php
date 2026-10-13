<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('rewards', 'jsonb');

        Schema::create(config('rewards.database.tables.payees', 'reward_payees'), function (Blueprint $table) use ($jsonType): void {

            $table->uuid('id')->primary();
            $table->string('recipient_type', 191);
            $table->string('recipient_id', 191);
            $table->string('method', 32)->index();
            $table->string('account_ref', 191);
            $table->string('account_display', 191);
            $table->string('status', 16)->index();
            $table->{$jsonType}('evidence')->nullable();
            $table->string('verified_by', 191)->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();
            $table->index(['recipient_type', 'recipient_id', 'status']);
        });
    }
};
