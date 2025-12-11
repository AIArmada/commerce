<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('tax.tables.tax_exemptions', 'tax_exemptions'), function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Polymorphic: Customer, User, etc.
            $table->uuidMorphs('exemptable');

            // Exemption details
            $table->string('reason');
            $table->string('certificate_number')->nullable();
            $table->string('document_path')->nullable();

            // Status: pending, approved, rejected, expired
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();

            // Verification
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();

            // Expiration
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['exemptable_type', 'exemptable_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('tax.tables.tax_exemptions', 'tax_exemptions'));
    }
};
