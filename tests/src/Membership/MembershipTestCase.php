<?php

declare(strict_types=1);

namespace AIArmada\Membership\Tests;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase as BaseTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Membership\Models\MembershipInvitation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\DefaultTeamResolver;
use Spatie\Permission\PermissionRegistrar;

abstract class MembershipTestCase extends BaseTestCase
{
    /**
     * Create a pending invitation through its lifecycle API.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createInvitation(array $attributes = [], ?string $rawToken = null): MembershipInvitation
    {
        $expiresAt = $attributes['expires_at'] ?? null;

        if ($expiresAt !== null && ! $expiresAt instanceof CarbonInterface) {
            $expiresAt = CarbonImmutable::parse((string) $expiresAt);
        }

        unset($attributes['expires_at']);

        $invitation = new MembershipInvitation;
        $invitation->fill($attributes);
        $invitation->issue($rawToken ?? bin2hex(random_bytes(32)), $expiresAt);
        $invitation->save();

        return $invitation;
    }

    protected function withMembershipOwner(callable $callback): mixed
    {
        $owner = User::query()
            ->where('email', 'default-owner@example.com')
            ->first()
            ?? app(OwnerResolverInterface::class)->resolve();

        return OwnerContext::withOwner(
            $owner,
            $callback,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('permission.team_resolver', DefaultTeamResolver::class);
        app()->forgetInstance(PermissionRegistrar::class);
        app(PermissionRegistrar::class);
        request()->attributes->remove(OwnerContext::REQUEST_KEY);
        Model::clearBootedModels();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('membership.owner', [
            'enabled' => true,
            'include_global' => false,
            'auto_assign_on_create' => true,
            'owner_type_column' => 'owner_type',
            'owner_id_column' => 'owner_id',
        ]);
        $app['config']->set('permission.team_resolver', DefaultTeamResolver::class);
    }

    protected function setUpDatabase(): void
    {
        parent::setUpDatabase();

        // Membership applications table
        Schema::dropIfExists('membership_applications');
        Schema::create('membership_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
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
            $table->nullableMorphs('owner');
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->string('email');
            $table->string('role');
            $table->string('token', 64);
            $table->string('status')->default('pending')->index();
            $table->foreignUuid('invited_by');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->foreignUuid('accepted_by')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignUuid('revoked_by')->nullable();
            $table->timestampTz('last_state_change_at')->nullable()->index();
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
