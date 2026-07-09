<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('communications.database.tables.preferences', 'communication_preferences'), function (Blueprint $table): void {
            $table->string('scope_type')->nullable()->after('category');
            $table->string('scope_key')->nullable()->after('scope_type');
            $table->index(['scope_type', 'scope_key']);
        });
    }
};
