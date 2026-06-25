<?php

declare(strict_types=1);

namespace AIArmada\Membership\Tests;

use AIArmada\Commerce\Tests\TestCase as BaseTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class MembershipTestCase extends BaseTestCase
{
    protected function setUpDatabase(): void
    {
        parent::setUpDatabase();

        // Membership applications table
        Schema::dropIfExists('membership_applications');
        Schema::create('membership_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->foreignUuid('applicant_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('granted_role')->nullable();
            $table->text('justification');
            $table->foreignUuid('reviewer_id')->nullable();
            $table->text('reviewer_note')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestampsTz();

            $table->index(['subject_type', 'subject_id']);
            $table->index('applicant_id');
            $table->index('status');
            $table->index('reviewer_id');
        });

        // Membership invitations table
        Schema::dropIfExists('membership_invitations');
        Schema::create('membership_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->string('email');
            $table->string('role');
            $table->string('token', 64);
            $table->foreignUuid('invited_by');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->foreignUuid('accepted_by')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignUuid('revoked_by')->nullable();
            $table->timestampsTz();

            $table->index(['subject_type', 'subject_id']);
            $table->index('email');
            $table->index('token');
            $table->index('invited_by');
        });

        // Test subjects table for HasMembers trait
        Schema::dropIfExists('test_subjects');
        Schema::create('test_subjects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        // Pivot table for HasMembers trait
        Schema::dropIfExists('test_subject_members');
        Schema::create('test_subject_members', function (Blueprint $table): void {
            $table->foreignUuid('test_subject_id');
            $table->foreignUuid('user_id');
            $table->string('role')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->primary(['test_subject_id', 'user_id']);
        });
    }
}
