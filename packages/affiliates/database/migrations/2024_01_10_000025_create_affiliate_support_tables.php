<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_support_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->index();
            $table->string('subject');
            $table->string('category')->default('general');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('affiliate_support_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->index();
            $table->foreignUuid('affiliate_id')->nullable()->index();
            $table->string('staff_id')->nullable();
            $table->text('message');
            $table->boolean('is_staff_reply')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_support_messages');
        Schema::dropIfExists('affiliate_support_tickets');
    }
};
