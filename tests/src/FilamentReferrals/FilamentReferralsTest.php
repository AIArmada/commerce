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
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Pages\EditReferralProgram as EditReferralProgramPage;
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
use Filament\Forms\Components\Select;
use Filament\Panel;
use Filament\Tables\Filters\SelectFilter;
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

it('renders configured qualifier outcomes as a labeled select field', function (): void {
    $owner = User::factory()->create();
    $this->setCurrentOwner($owner);
    config(['referrals.qualifier_outcomes' => ['first_accepted_registration_or_staff_verified_checkin']]);

    Livewire::actingAs($owner)
        ->test(CreateReferralProgramPage::class)
        ->assertFormFieldExists(
            'qualifier_outcome',
            static fn (Select $field): bool => $field->getOptions() === [
                'first_accepted_registration_or_staff_verified_checkin' => 'first_accepted_registration_or_staff_verified_checkin',
            ],
        );
});

it('creates a program with enabled reward specifications when another driver is disabled', function (): void {
    $owner = User::factory()->create();
    $this->setCurrentOwner($owner);
    $slug = 'program-' . Str::lower(Str::random(10));
    config(['referrals.fulfillment.drivers.affiliate_cash.enabled' => false]);

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
                'family_key' => 'referee-join',
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
        'family_key' => 'referee-join',
    ]);

    Livewire::actingAs($owner)
        ->test(ListReferralPrograms::class)
        ->assertCanSeeTableRecords([$program]);
});

it('offers enabled drivers dynamically while keeping historical disabled drivers readable and filterable', function (): void {
    $owner = User::factory()->create();
    $program = $this->newProgram($owner, [
        'referrer_reward' => [
            'driver' => 'affiliate_cash',
            'amount_minor' => 1250,
            'currency' => 'MYR',
        ],
        'referee_reward' => null,
    ]);
    $invite = filamentReferralsQualifyInvite(
        $owner,
        $program,
        $owner,
        User::factory()->create(),
        'historical-driver@example.com',
    );
    $reward = $invite->rewards()->firstOrFail();
    $this->setCurrentOwner($owner);
    config(['referrals.fulfillment.drivers.affiliate_cash.enabled' => true]);

    $programForm = Livewire::actingAs($owner)->test(CreateReferralProgramPage::class);

    $programForm->assertFormFieldExists(
        'referrer_reward.driver',
        static fn (Select $field): bool => array_key_exists('affiliate_cash', $field->getOptions())
            && array_key_exists('manual', $field->getOptions()),
    );

    config(['referrals.fulfillment.drivers.affiliate_cash.enabled' => false]);

    $programForm->assertFormFieldExists(
        'referrer_reward.driver',
        static fn (Select $field): bool => ! array_key_exists('affiliate_cash', $field->getOptions())
            && array_key_exists('manual', $field->getOptions()),
    );

    Livewire::actingAs($owner)
        ->test(ListReferralRewards::class)
        ->assertCanSeeTableRecords([$reward])
        ->assertTableFilterExists(
            'driver',
            static fn (SelectFilter $filter): bool => array_key_exists('affiliate_cash', $filter->getOptions()),
        )
        ->filterTable('driver', 'affiliate_cash')
        ->assertCanSeeTableRecords([$reward]);
});

it('rejects a disabled reward driver during program creation without persisting the program', function (): void {
    $owner = User::factory()->create();
    $this->setCurrentOwner($owner);
    config(['referrals.fulfillment.drivers.affiliate_cash.enabled' => false]);
    $slug = 'disabled-driver-' . Str::lower(Str::random(10));

    Livewire::actingAs($owner)
        ->test(CreateReferralProgramPage::class)
        ->fillForm([
            'name' => 'Disabled driver referral',
            'slug' => $slug,
            'status' => ReferralProgramStatus::Draft->value,
            'qualifier_outcome' => 'first_registration',
            'currency' => 'MYR',
            'referrer_reward' => [
                'driver' => 'affiliate_cash',
                'amount_minor' => '1250',
                'currency' => 'MYR',
            ],
            'referee_reward' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['referrer_reward.driver' => 'in']);

    $programExists = OwnerContext::withOwner(
        $owner,
        fn (): bool => ReferralProgram::query()->where('slug', $slug)->exists(),
    );

    expect($programExists)->toBeFalse();
});

it('rejects a disabled reward driver during program edit without changing its specification', function (): void {
    $owner = User::factory()->create();
    $program = $this->newProgram($owner, [
        'referrer_reward' => [
            'driver' => 'manual',
            'amount_minor' => 1250,
            'currency' => 'MYR',
        ],
        'referee_reward' => null,
    ]);
    $originalReward = $program->referrer_reward;
    $this->setCurrentOwner($owner);
    config(['referrals.fulfillment.drivers.affiliate_cash.enabled' => false]);

    Livewire::actingAs($owner)
        ->test(EditReferralProgramPage::class, ['record' => $program->getKey()])
        ->fillForm([
            'referrer_reward' => [
                'driver' => 'affiliate_cash',
                'amount_minor' => '2500',
                'currency' => 'USD',
            ],
        ])
        ->call('save')
        ->assertHasFormErrors(['referrer_reward.driver' => 'in']);

    expect($program->fresh()->referrer_reward)->toBe($originalReward);
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

it('rejects an invalid program edit and leaves the stored program unchanged', function (): void {
    $owner = User::factory()->create();
    $program = $this->newProgram($owner, [
        'starts_at' => '2026-01-01T00:00:00Z',
        'ends_at' => '2026-02-01T00:00:00Z',
    ]);
    $originalEndsAt = $program->ends_at;
    $this->setCurrentOwner($owner);

    // The form's length rules let "!!!" through; the core update action
    // rejects it and the page reports the failure on the currency field.
    Livewire::actingAs($owner)
        ->test(EditReferralProgramPage::class, ['record' => $program->getKey()])
        ->fillForm(['currency' => '!!!'])
        ->call('save')
        ->assertHasFormErrors(['currency']);

    expect($program->fresh()->currency)->toBe('MYR');

    // A window that inverts against the stored starts_at is refused the same
    // way creation would refuse it.
    Livewire::actingAs($owner)
        ->test(EditReferralProgramPage::class, ['record' => $program->getKey()])
        ->fillForm(['ends_at' => '2025-12-01 00:00:00'])
        ->call('save')
        ->assertHasFormErrors(['ends_at']);

    expect($program->fresh()->ends_at?->equalTo($originalEndsAt))->toBeTrue();
});

it('does not reactivate an archived program through the edit page', function (): void {
    $owner = User::factory()->create();
    $program = $this->newProgram($owner, [
        'status' => 'archived',
        'name' => 'Archived referral',
    ]);
    $this->setCurrentOwner($owner);

    // The status select is non-dehydrated on edit, so a tampered payload
    // cannot move the program back to Active while other edits still apply.
    Livewire::actingAs($owner)
        ->test(EditReferralProgramPage::class, ['record' => $program->getKey()])
        ->fillForm([
            'status' => ReferralProgramStatus::Active->value,
            'name' => 'Archived referral renamed',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($program->fresh()->status)->toBe(ReferralProgramStatus::Archived)
        ->and($program->fresh()->name)->toBe('Archived referral renamed');
});

it('persists a valid program edit through the core update action', function (): void {
    $owner = User::factory()->create();
    $program = $this->newProgram($owner, ['name' => 'Member referral']);
    $this->setCurrentOwner($owner);

    Livewire::actingAs($owner)
        ->test(EditReferralProgramPage::class, ['record' => $program->getKey()])
        ->fillForm([
            'name' => 'Member referral v2',
            'invite_expiry_days' => '21',
            'currency' => 'SGD',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($program->fresh()->name)->toBe('Member referral v2')
        ->and($program->fresh()->invite_expiry_days)->toBe(21)
        ->and($program->fresh()->currency)->toBe('SGD');
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
