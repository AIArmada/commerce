<?php

declare(strict_types=1);

namespace Tests\src\Referrals;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase as BaseTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Referrals\Actions\AcceptReferralInvite;
use AIArmada\Referrals\Actions\CreateReferralInvite;
use AIArmada\Referrals\Actions\CreateReferralProgram;
use AIArmada\Referrals\Data\ReferralInviteCreationResult;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\ReferralsServiceProvider;
use Composer\Autoload\ClassLoader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

$projectRoot = dirname(__DIR__, 3);
/** @var ClassLoader $composerLoader */
$composerLoader = require $projectRoot . '/vendor/autoload.php';
$composerLoader->addPsr4('AIArmada\\Referrals\\', $projectRoot . '/packages/referrals/src');
$composerLoader->addPsr4('AIArmada\\Referrals\\Database\\Factories\\', $projectRoot . '/packages/referrals/database/factories');

abstract class ReferralsTestCase extends BaseTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders(mixed $app): array
    {
        return [...parent::getPackageProviders($app), ReferralsServiceProvider::class];
    }

    protected function newOwner(): User
    {
        return User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function newProgram(User $owner, array $overrides = []): ReferralProgram
    {
        $attributes = array_replace([
            'name' => 'Member referral',
            'slug' => 'member-referral-' . bin2hex(random_bytes(4)),
            'status' => 'active',
            'qualifier_outcome' => 'first_registration',
            'currency' => 'MYR',
            'referrer_reward' => ['driver' => 'manual'],
            'referee_reward' => ['driver' => 'manual'],
        ], $overrides);

        return OwnerContext::withOwner(
            $owner,
            fn (): ReferralProgram => CreateReferralProgram::run($attributes),
        );
    }

    protected function newInvite(
        User $owner,
        ReferralProgram $program,
        User $referrer,
        string $inviteeContact,
        ?string $referrerContact = null,
    ): ReferralInviteCreationResult {
        return OwnerContext::withOwner(
            $owner,
            fn (): ReferralInviteCreationResult => CreateReferralInvite::run(
                $program,
                $referrer,
                $inviteeContact,
                $referrerContact,
            ),
        );
    }

    protected function acceptInvite(User $owner, string $token, User $invitee, string $contact): ReferralInvite
    {
        return OwnerContext::withOwner(
            $owner,
            fn (): ReferralInvite => AcceptReferralInvite::run($token, $invitee, $contact),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        request()->attributes->remove(OwnerContext::REQUEST_KEY);
        Model::clearBootedModels();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('referrals.owner', [
            'enabled' => true,
            'include_global' => false,
            'auto_assign_on_create' => true,
            'owner_type_column' => 'owner_type',
            'owner_id_column' => 'owner_id',
        ]);
    }

    protected function setUpDatabase(): void
    {
        parent::setUpDatabase();

        if (! Schema::hasTable('referral_programs')) {
            Schema::create('referral_programs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->nullableMorphs('owner');
                $table->string('name');
                $table->string('slug');
                $table->string('status');
                $table->string('qualifier_outcome');
                $table->unsignedInteger('invite_expiry_days')->nullable();
                $table->unsignedInteger('max_invites_per_referrer')->nullable();
                $table->unsignedInteger('max_qualified_per_referrer_per_month')->nullable();
                $table->json('referrer_reward')->nullable();
                $table->json('referee_reward')->nullable();
                $table->string('currency', 3);
                $table->timestampTz('starts_at')->nullable();
                $table->timestampTz('ends_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('referral_invites')) {
            Schema::create('referral_invites', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('program_id');
                $table->nullableUuidMorphs('referrer');
                $table->string('invitee_hash', 64);
                $table->text('invitee_contact_encrypted')->nullable();
                $table->string('token_hash', 64)->unique();
                $table->string('status');
                $table->nullableUuidMorphs('accepted_by');
                $table->string('qualification_business_key', 191)->nullable()->unique();
                $table->timestampTz('accepted_at')->nullable();
                $table->timestampTz('qualified_at')->nullable();
                $table->timestampTz('rewarded_at')->nullable();
                $table->timestampTz('expired_at')->nullable();
                $table->timestampTz('revoked_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestampsTz();
                $table->index(['program_id', 'invitee_hash', 'status']);
            });
        }

        if (! Schema::hasTable('referral_rewards')) {
            Schema::create('referral_rewards', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('invite_id');
                $table->foreignUuid('program_id');
                $table->string('side');
                $table->nullableUuidMorphs('beneficiary');
                $table->string('driver');
                $table->unsignedBigInteger('amount_minor')->nullable();
                $table->unsignedInteger('points')->nullable();
                $table->string('currency', 3)->nullable();
                $table->json('spec');
                $table->string('status');
                $table->string('idempotency_key', 64)->unique();
                $table->string('external_reference')->nullable();
                $table->timestampTz('fulfilled_at')->nullable();
                $table->timestampTz('reversed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestampsTz();
                $table->unique(['invite_id', 'side']);
            });
        }
    }
}
