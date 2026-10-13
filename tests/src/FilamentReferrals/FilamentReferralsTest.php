<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentReferrals\FilamentReferralsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentReferrals\FilamentReferralsPlugin;
use AIArmada\FilamentReferrals\Resources\ReferralInviteResource\Pages\ListReferralInvites;
use AIArmada\FilamentReferrals\Resources\ReferralInviteResource\Pages\ViewReferralInvite;
use AIArmada\FilamentReferrals\Resources\ReferralInviteResource\ReferralInviteResource;
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Pages\CreateReferralProgram as CreateReferralProgramPage;
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Pages\ListReferralPrograms;
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\ReferralProgramResource;
use AIArmada\FilamentReferrals\Resources\ReferralRewardResource\Pages\ListReferralRewards;
use AIArmada\FilamentReferrals\Resources\ReferralRewardResource\ReferralRewardResource;
use AIArmada\FilamentReferrals\Widgets\ReferralStatsWidget;
use AIArmada\Referrals\Actions\AcceptReferralInvite;
use AIArmada\Referrals\Actions\CreateReferralInvite;
use AIArmada\Referrals\Actions\FulfillReferralReward;
use AIArmada\Referrals\Actions\QualifyReferral;
use AIArmada\Referrals\Actions\RevokeReferralInvite;
use AIArmada\Referrals\Data\ReferralInviteCreationResult;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Enums\ReferralProgramStatus;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Models\ReferralReward;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(FilamentReferralsTestCase::class);

beforeEach(function (): void {
    Gate::before(static fn (): bool => true);
});

it('registers the resources and stats widget on a panel with merged config', function (): void {
    $plugin = FilamentReferralsPlugin::make();
    $panel = Panel::make();

    $plugin->register($panel);

    expect($plugin->getId())->toBe('filament-referrals')
        ->and($panel->getResources())->toBe([
            ReferralProgramResource::class,
            ReferralInviteResource::class,
            ReferralRewardResource::class,
        ])
        ->and($panel->getWidgets())->toContain(ReferralStatsWidget::class)
        ->and(app(FilamentReferralsPlugin::class))->toBe($plugin)
        ->and(config('filament-referrals.navigation.group'))->toBe('Referrals');
});

it('renders the program list and creates a program with both reward specifications', function (): void {
    $owner = User::factory()->create();
    $this->setCurrentOwner($owner);
    $slug = 'program-' . Str::lower(Str::random(10));

    Livewire::actingAs($owner)
        ->test(ListReferralPrograms::class)
        ->assertOk();

    Livewire::actingAs($owner)
        ->test(CreateReferralProgramPage::class)
        ->fillForm([
            'name' => 'Member referrals',
            'slug' => $slug,
            'status' => ReferralProgramStatus::Draft->value,
            'qualifier_outcome' => 'first_registration',
            'invite_expiry_days' => '30',
            'max_invites_per_referrer' => '8',
            'max_qualified_per_referrer_per_month' => '3',
            'currency' => 'MYR',
            'referrer_reward' => [
                'driver' => 'manual',
                'amount_minor' => '1250',
                'currency' => 'MYR',
            ],
            'referee_reward' => [
                'driver' => 'reward_points',
                'points' => '40',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $program = OwnerContext::withOwner(
        $owner,
        fn (): ReferralProgram => ReferralProgram::query()->where('slug', $slug)->firstOrFail(),
    );

    expect($program->referrer_reward)->toBe([
        'driver' => 'manual',
        'amount_minor' => 1250,
        'currency' => 'MYR',
    ])->and($program->referee_reward)->toBe([
        'driver' => 'reward_points',
        'points' => 40,
    ]);

    Livewire::actingAs($owner)
        ->test(ListReferralPrograms::class)
        ->assertCanSeeTableRecords([$program]);
});

it('hides invites that belong to another owner from the invite list', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $ownProgram = $this->newProgram($owner);
    $otherProgram = $this->newProgram($otherOwner);

    $ownInvite = $this->newInvite($owner, $ownProgram, $owner, 'own-invite@example.com')->invite;
    $otherInvite = $this->newInvite($otherOwner, $otherProgram, $otherOwner, 'other-invite@example.com')->invite;

    $this->setCurrentOwner($owner);

    Livewire::actingAs($owner)
        ->test(ListReferralInvites::class)
        ->assertCanSeeTableRecords([$ownInvite])
        ->assertCanNotSeeTableRecords([$otherInvite]);
});

it('hides rewards that belong to another owner from the reward list', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $ownProgram = $this->newProgram($owner);
    $otherProgram = $this->newProgram($otherOwner);

    $ownInvite = filamentReferralsQualifyInvite($owner, $ownProgram, $owner, User::factory()->create(), 'own-qualified@example.com');
    $otherInvite = filamentReferralsQualifyInvite($otherOwner, $otherProgram, $otherOwner, User::factory()->create(), 'other-qualified@example.com');
    $ownReward = $ownInvite->rewards()->firstOrFail();
    $otherReward = $otherInvite->rewards()->firstOrFail();

    $this->setCurrentOwner($owner);

    Livewire::actingAs($owner)
        ->test(ListReferralRewards::class)
        ->assertCanSeeTableRecords([$ownReward])
        ->assertCanNotSeeTableRecords([$otherReward]);
});

it('fulfils and reverses a manual reward through core actions', function (): void {
    $owner = User::factory()->create();
    $program = $this->newProgram($owner, [
        'referrer_reward' => ['driver' => 'manual'],
        'referee_reward' => null,
    ]);
    $invite = filamentReferralsQualifyInvite($owner, $program, $owner, User::factory()->create(), 'rewarded-member@example.com');
    $reward = $invite->rewards()->firstOrFail();
    $reward->metadata = ['external_reference' => 'manual-referral-123'];
    $reward->save();
    $this->setCurrentOwner($owner);

    Livewire::actingAs($owner)
        ->test(ListReferralRewards::class)
        ->callTableAction('fulfill', $reward->getKey())
        ->assertHasNoTableActionErrors();

    expect($reward->fresh()->status)->toBe(ReferralRewardStatus::Fulfilled)
        ->and($invite->fresh()->status)->toBe(ReferralInviteStatus::Rewarded);

    Livewire::actingAs($owner)
        ->test(ListReferralRewards::class)
        ->callTableAction('reverse', $reward->getKey(), data: [
            'reason' => 'The manual credit was issued in error.',
        ])
        ->assertHasNoTableActionErrors();

    expect($reward->fresh()->status)->toBe(ReferralRewardStatus::Reversed)
        ->and($invite->fresh()->status)->toBe(ReferralInviteStatus::Qualified)
        ->and($reward->fresh()->metadata['reversal_reason'])->toBe('The manual credit was issued in error.');
});

it('revokes pending invites and hides the revoke action for terminal invites', function (): void {
    $owner = User::factory()->create();
    $program = $this->newProgram($owner);
    $pendingInvite = $this->newInvite($owner, $program, $owner, 'pending-revoke@example.com')->invite;
    $terminalInvite = $this->newInvite($owner, $program, $owner, 'terminal-revoke@example.com')->invite;

    OwnerContext::withOwner($owner, fn (): ReferralInvite => RevokeReferralInvite::run($terminalInvite));
    $this->setCurrentOwner($owner);

    Livewire::actingAs($owner)
        ->test(ListReferralInvites::class)
        ->assertCanSeeTableRecords([$pendingInvite, $terminalInvite])
        ->assertTableActionVisible('revoke', $pendingInvite)
        ->assertTableActionHidden('revoke', $terminalInvite)
        ->callTableAction('revoke', $pendingInvite->getKey(), data: [
            'reason' => 'The invite was sent to the wrong address.',
        ])
        ->assertHasNoTableActionErrors();

    expect($pendingInvite->fresh()->status)->toBe(ReferralInviteStatus::Revoked);

    expect(fn (): ReferralInvite => OwnerContext::withOwner(
        $owner,
        fn (): ReferralInvite => RevokeReferralInvite::run($terminalInvite),
    ))->toThrow(LogicException::class);
});

it('shows only current owner counts in referral statistics', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $ownProgram = $this->newProgram($owner);
    $otherProgram = $this->newProgram($otherOwner);

    $this->newInvite($owner, $ownProgram, $owner, 'stats-pending@example.com');
    filamentReferralsQualifyInvite($owner, $ownProgram, $owner, User::factory()->create(), 'stats-qualified@example.com');
    $ownRewardedInvite = filamentReferralsQualifyInvite(
        $owner,
        $this->newProgram($owner, [
            'referrer_reward' => ['driver' => 'manual'],
            'referee_reward' => null,
        ]),
        $owner,
        User::factory()->create(),
        'stats-rewarded@example.com',
    );
    $ownReward = $ownRewardedInvite->rewards()->firstOrFail();
    $ownReward->metadata = ['external_reference' => 'stats-manual-ref'];
    $ownReward->save();
    OwnerContext::withOwner($owner, fn (): ReferralReward => FulfillReferralReward::run($ownReward));

    $this->newInvite($otherOwner, $otherProgram, $otherOwner, 'foreign-stats-pending@example.com');
    filamentReferralsQualifyInvite($otherOwner, $otherProgram, $otherOwner, User::factory()->create(), 'foreign-stats-qualified@example.com');

    $this->setCurrentOwner($owner);
    $html = Livewire::actingAs($owner)
        ->test(ReferralStatsWidget::class)
        ->html();

    expect($html)
        ->toContain('Pending invites')
        ->toContain('Qualified invites')
        ->toContain('Rewarded invites')
        ->toContain('Rewards pending fulfilment')
        ->toMatch('/Pending invites[\s\S]*?\b1\b/')
        ->toMatch('/Qualified invites[\s\S]*?\b1\b/')
        ->toMatch('/Rewarded invites[\s\S]*?\b1\b/')
        ->toMatch('/Rewards pending fulfilment[\s\S]*?\b2\b/');
});

it('does not render the invite token hash or decrypted invitee contact', function (): void {
    $owner = User::factory()->create();
    $program = $this->newProgram($owner);
    $contact = 'private-invitee@example.com';
    $created = $this->newInvite($owner, $program, $owner, $contact);
    $invite = $created->invite;
    $this->setCurrentOwner($owner);

    Livewire::actingAs($owner)
        ->test(ViewReferralInvite::class, ['record' => $invite->getKey()])
        ->assertOk()
        ->assertDontSee((string) $invite->token_hash)
        ->assertDontSee($contact);
});

function filamentReferralsQualifyInvite(
    User $owner,
    ReferralProgram $program,
    User $referrer,
    User $invitee,
    string $contact,
): ReferralInvite {
    $created = OwnerContext::withOwner(
        $owner,
        fn (): ReferralInviteCreationResult => CreateReferralInvite::run($program, $referrer, $contact),
    );

    if ($created->token === null) {
        throw new LogicException('The new referral invite did not return its one-time token.');
    }

    return OwnerContext::withOwner($owner, function () use ($created, $invitee, $contact): ReferralInvite {
        $acceptedInvite = AcceptReferralInvite::run($created->token, $invitee, $contact);

        return QualifyReferral::run($acceptedInvite, 'business-' . Str::lower(Str::random(12)))->invite;
    });
}
