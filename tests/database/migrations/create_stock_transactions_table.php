<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('stockable');
            $table->uuid('user_id')->nullable();
            $table->integer('quantity');
            $table->enum('type', ['in', 'out']);
            $table->string('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
