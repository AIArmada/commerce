<?php

declare(strict_types=1);

use AIArmada\Authz\Models\Role;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentRewards\Resources\RewardAppealResource\Pages\ListRewardAppeals;
use AIArmada\FilamentRewards\Resources\RewardAwardResource\Pages\ListRewardAwards;
use AIArmada\FilamentRewards\Resources\RewardBenefitGrantResource\Pages\ListRewardBenefitGrants;
use AIArmada\FilamentRewards\Resources\RewardBenefitItemResource\Pages\ListRewardBenefitItems;
use AIArmada\FilamentRewards\Resources\RewardFraudResource\Pages\ListRewardFraudCases;
use AIArmada\FilamentRewards\Resources\RewardFundingResource\Pages\ListRewardFunding;
use AIArmada\FilamentRewards\Resources\RewardPayeeResource\Pages\ListRewardPayees;
use AIArmada\FilamentRewards\Resources\RewardPayoutResource\Pages\ListRewardPayouts;
use AIArmada\FilamentRewards\Resources\RewardProgramResource\Pages\ListRewardPrograms;
use AIArmada\FilamentRewards\Resources\RewardProgramResource\Pages\ViewRewardProgram;
use AIArmada\FilamentRewards\Resources\RewardRecoveryResource\Pages\ListRewardRecoveries;
use AIArmada\Rewards\Actions\CreateRewardBenefitItemAction;
use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Actions\OpenRewardAppealAction;
use AIArmada\Rewards\Actions\RecordRewardFundingAction;
use AIArmada\Rewards\Actions\RecordRewardFundingSourceAction;
use AIArmada\Rewards\Actions\RequestRewardPayoutAction;
use AIArmada\Rewards\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Actions\TransitionRewardProgramStatusAction;
use AIArmada\Rewards\Actions\VerifyRewardPayeeAction;
use AIArmada\Rewards\Contracts\RewardFinancialReadinessChecker;
use AIArmada\Rewards\Contracts\RewardReadinessChecker;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Enums\RewardAppealStatus;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardProgramStatus;
use AIArmada\Rewards\Enums\RewardRecoveryKind;
use AIArmada\Rewards\Enums\RewardRecoveryStatus;
use AIArmada\Rewards\Enums\RewardSourceFraudStatus;
use AIArmada\Rewards\Models\RewardAppeal;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardBenefitItem;
use AIArmada\Rewards\Models\RewardFundingEntry;
use AIArmada\Rewards\Models\RewardPayee;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Models\RewardProgramVersion;
use AIArmada\Rewards\Models\RewardRecovery;
use AIArmada\Rewards\Support\FundedRewardFinancialReadinessChecker;
use AIArmada\Rewards\Support\FundedRewardReadinessChecker;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::before(static fn (): bool => true);

    app()->bind(RewardReadinessChecker::class, FundedRewardReadinessChecker::class);
    app()->bind(RewardFinancialReadinessChecker::class, FundedRewardFinancialReadinessChecker::class);

    $this->staff = User::factory()->create();
    $this->staff->assignRole(Role::findOrCreate('admin', 'web'));
});

function adminAdaptHeldAward(string $staffId, bool $suspect = false): RewardAward
{
    $memberId = (string) Str::uuid();

    [$program, $version] = RewardLedgerFixtures::seedVersionedProgram(
        'admin-award',
        RewardProgramMode::Recognition,
        $suspect ? [] : ['holds' => ['manual_review_outcomes' => ['attendance']]],
    );
    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);
    EnrollRewardMemberAction::run($program->refresh(), 'user', $memberId, $version, 'member-portal', CarbonImmutable::parse('2026-06-10T00:00:00Z'));

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact(
        'src-admin-' . $memberId,
        $memberId,
        '2026-06-12T00:00:00Z',
        'attendance',
        $suspect ? ['fraudStatus' => RewardSourceFraudStatus::Suspect] : [],
    ));
    app()->instance(RewardSourceFactReader::class, $reader);

    $result = (new EvaluateRewardEntitlementAction($reader))->handle(
        $program->refresh(),
        RewardComponent::Recognition,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'src-admin-' . $memberId,
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    return $result->award->refresh();
}

function adminAdaptCashSetup(string $staffId): array
{
    [$program, $version] = RewardLedgerFixtures::seedVersionedProgram('admin-fin', RewardProgramMode::Cash);
    $funder = RewardLedgerFixtures::staffId();
    $verifier = RewardLedgerFixtures::staffId();
    $requester = RewardLedgerFixtures::staffId();

    RecordRewardFundingSourceAction::run(
        'TREASURY-admin-fin',
        'MYR',
        10000,
        'user',
        $funder,
        'staff',
        null,
        CarbonImmutable::parse('2026-06-02T00:00:00Z'),
    );

    RecordRewardFundingAction::run(
        $program,
        'MYR',
        10000,
        'TREASURY-admin-fin',
        'user',
        $funder,
        'staff',
        null,
        CarbonImmutable::parse('2026-06-02T00:00:00Z'),
    );

    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    $program = TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);

    $memberId = (string) Str::uuid();

    EnrollRewardMemberAction::run(
        $program,
        'user',
        $memberId,
        $version,
        'member-portal',
        CarbonImmutable::parse('2026-06-10T00:00:00Z'),
    );

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact('src-admin-fin-1', $memberId));
    app()->instance(RewardSourceFactReader::class, $reader);

    $result = (new EvaluateRewardEntitlementAction($reader))->handle(
        $program,
        RewardComponent::Cash,
        'user',
        $memberId,
        RewardBeneficiaryRole::Recipient,
        'src-admin-fin-1',
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    $payee = VerifyRewardPayeeAction::run(
        'user',
        $memberId,
        'manual',
        'ACCT-A',
        'Desk A',
        'user',
        $verifier,
        'staff',
        null,
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    $approved = (new TransitionRewardAwardAction(app(RewardFinancialReadinessChecker::class), $reader))->handle(
        $result->award,
        RewardAwardStatus::Approved,
        'user',
        $staffId,
        'staff',
        'policy-ok',
        null,
        null,
        CarbonImmutable::parse('2026-06-17T00:00:00Z'),
    );

    $request = RequestRewardPayoutAction::run(
        $approved,
        $payee,
        'manual',
        'program',
        'user',
        $requester,
        'staff',
        null,
        CarbonImmutable::parse('2026-06-17T00:00:00Z'),
    );

    return [$program, $request];
}

function recordAdminAdaptFunding(RewardProgram $program, string $currency, int $amountMinor): void
{
    $staffId = RewardLedgerFixtures::staffId();
    $sourceRef = 'TREASURY-' . $currency . '-' . Str::uuid();
    $at = CarbonImmutable::parse('2026-06-02T00:00:00Z');

    RecordRewardFundingSourceAction::run(
        $sourceRef,
        $currency,
        $amountMinor,
        'user',
        $staffId,
        'staff',
        null,
        $at,
    );
    RecordRewardFundingAction::run(
        $program,
        $currency,
        $amountMinor,
        $sourceRef,
        'user',
        $staffId,
        'staff',
        null,
        $at,
    );
}

it('lets staff approve a held award from the awards table', function (): void {
    $award = adminAdaptHeldAward((string) $this->staff->getKey());
    expect($award->status)->toBe(RewardAwardStatus::Held);

    Livewire::actingAs($this->staff)
        ->test(ListRewardAwards::class)
        ->assertCanSeeTableRecords([$award])
        ->callTableAction('approve', $award->getKey(), data: [
            'reason' => 'Verified contribution; releasing the hold.',
        ])
        ->assertHasNoTableActionErrors();

    expect($award->fresh()->status)->toBe(RewardAwardStatus::Approved);
});

it('lets staff dismiss fraud without approving, and reverse approved awards', function (): void {
    $suspect = adminAdaptHeldAward((string) $this->staff->getKey(), true);
    expect($suspect->status)->toBe(RewardAwardStatus::Held);

    Livewire::actingAs($this->staff)
        ->test(ListRewardAwards::class)
        ->callTableAction('dismiss_fraud', $suspect->getKey(), data: [
            'reason' => 'Signal investigated; no fraud found.',
        ])
        ->assertHasNoTableActionErrors();

    expect($suspect->fresh()->status)->toBe(RewardAwardStatus::Held);

    Livewire::actingAs($this->staff)
        ->test(ListRewardAwards::class)
        ->callTableAction('approve', $suspect->getKey(), data: [
            'reason' => 'Attempted approval while suspect.',
        ])
        ->assertHasTableActionErrors();

    expect($suspect->fresh()->status)->toBe(RewardAwardStatus::Held);

    $award = adminAdaptHeldAward((string) $this->staff->getKey());

    Livewire::actingAs($this->staff)
        ->test(ListRewardAwards::class)
        ->callTableAction('approve', $award->getKey(), data: [
            'reason' => 'Approved after manual review.',
        ])
        ->assertHasNoTableActionErrors();

    expect($award->fresh()->status)->toBe(RewardAwardStatus::Approved);

    Livewire::actingAs($this->staff)
        ->test(ListRewardAwards::class)
        ->callTableAction('reverse', $award->getKey(), data: [
            'reason' => 'Source withdrawn after approval.',
        ])
        ->assertHasNoTableActionErrors();

    expect($award->fresh()->status)->toBe(RewardAwardStatus::Reversed);
});

it('lets staff publish a version and transition a program', function (): void {
    config(['rewards.outcomes' => ['contribution', 'attendance']]);
    [$program] = RewardLedgerFixtures::seedVersionedProgram('admin-program', RewardProgramMode::Combined);
    $program = $program->refresh();
    expect($program->status)->toBe(RewardProgramStatus::Draft);

    Livewire::actingAs($this->staff)
        ->test(ViewRewardProgram::class, ['record' => $program->getKey()])
        ->callAction('publish_version', data: [
            'effective_from' => CarbonImmutable::now('UTC')->addHour()->toIso8601String(),
            'component_rows' => [[
                'type' => 'recognition',
                'variants' => [[
                    'outcomes' => ['attendance'],
                    'roles' => [RewardBeneficiaryRole::Participant->value],
                    'min_verification' => 'staff_verified',
                    'first_only' => false,
                    'template' => 'general-thanks',
                ]],
            ]],
            'milestone_rows' => [[
                'key' => 'bintang-1',
                'label' => 'Bintang 1',
                'outcomes' => ['attendance'],
                'roles' => [RewardBeneficiaryRole::Participant->value],
                'min_verification' => 'staff_verified',
                'first_only' => false,
                'target_count' => 1,
                'window_days' => 30,
                'reward_type' => 'badge',
                'badge_key' => 'bintang-1',
            ]],
            'eligibility_outcomes' => ['contribution', 'attendance'],
            'eligibility_categories' => '',
            'min_verification' => 'accepted',
            'first_only' => false,
            'exclude_self_credit' => true,
            'require_enrollment' => true,
            'require_claimed_identity' => true,
            'preclaim_lookback_days' => 30,
            'hold_days' => 0,
            'cap_scope_1' => 'recipient_source_component',
            'cap_limit_1' => 1,
            'ambiguous_device' => true,
            'manual_review_outcomes' => [],
            'terms_label' => 'Member terms',
            'terms_version_label' => 'v2',
            'terms_body' => 'Earn only on verified eligible outcomes.',
            'terms_earn_basis' => 'Verified eligible outcomes only.',
            'terms_changes' => 'Changes apply prospectively after reacceptance.',
            'terms_no_ranking' => 'No public ranking.',
            'reason' => 'Second version for admin coverage.',
        ])
        ->assertHasNoActionErrors();

    $publishedVersion = RewardProgramVersion::query()
        ->where('program_id', $program->getKey())
        ->orderByDesc('version')
        ->firstOrFail();

    expect(RewardProgramVersion::query()->where('program_id', $program->getKey())->count())->toBe(2)
        ->and($publishedVersion->rules['components'][0]['variants'][0]['template'])->toBe('general-thanks')
        ->and($publishedVersion->rules['milestones'][0]['label'])->toBe('Bintang 1');

    Livewire::actingAs($this->staff)
        ->test(ViewRewardProgram::class, ['record' => $program->getKey()])
        ->callAction('transition_status', data: ['to' => RewardProgramStatus::Ready->value])
        ->assertHasNoActionErrors();

    expect($program->fresh()->status)->toBe(RewardProgramStatus::Ready);
});

it('hides points as a milestone grant option for badges-mode programs', function (): void {
    [$program] = RewardLedgerFixtures::seedVersionedProgram('admin-badges-milestone-ui', RewardProgramMode::Badges);

    Livewire::actingAs($this->staff)
        ->test(ViewRewardProgram::class, ['record' => $program->getKey()])
        ->callAction('publish_version', data: [
            'effective_from' => CarbonImmutable::now('UTC')->addHour()->toIso8601String(),
            'component_rows' => [[
                'type' => 'badge',
                'variants' => [[
                    'outcomes' => ['attendance'],
                    'roles' => [RewardBeneficiaryRole::Participant->value],
                    'min_verification' => 'accepted',
                    'first_only' => false,
                    'badge_key' => 'member-star',
                ]],
            ]],
            'milestone_rows' => [[
                'key' => 'points-tier',
                'label' => 'Points tier',
                'outcomes' => ['attendance'],
                'roles' => [RewardBeneficiaryRole::Participant->value],
                'min_verification' => 'accepted',
                'first_only' => false,
                'target_count' => 1,
                'window_days' => 30,
                'reward_type' => 'points',
                'points' => 5,
                'unit' => 'pts',
            ]],
            'eligibility_outcomes' => ['attendance'],
            'eligibility_categories' => '',
            'min_verification' => 'accepted',
            'first_only' => false,
            'exclude_self_credit' => true,
            'require_enrollment' => true,
            'require_claimed_identity' => true,
            'preclaim_lookback_days' => 30,
            'hold_days' => 0,
            'cap_scope_1' => 'recipient_source_component',
            'cap_limit_1' => 1,
            'ambiguous_device' => true,
            'manual_review_outcomes' => [],
            'terms_label' => 'Member terms',
            'terms_version_label' => 'v2',
            'terms_body' => 'Earn only on verified eligible outcomes.',
            'terms_earn_basis' => 'Verified eligible outcomes only.',
            'terms_changes' => 'Changes apply prospectively after reacceptance.',
            'terms_no_ranking' => 'No public ranking.',
            'reason' => 'Badges programs cannot grant points from milestones.',
        ])
        ->assertHasFormErrors(['milestone_rows.0.reward_type' => ['in']]);
});

it('maps structured publish failures to the closest form section', function (): void {
    $page = (new ReflectionClass(ViewRewardProgram::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ViewRewardProgram::class, 'publishErrorField');

    expect($method->invoke($page, new InvalidArgumentException('Milestone [0] outcomes are invalid.')))
        ->toBe('milestone_rows')
        ->and($method->invoke($page, new InvalidArgumentException('Component [0] is invalid.')))
        ->toBe('component_rows')
        ->and($method->invoke($page, new InvalidArgumentException('Eligibility outcomes are invalid.')))
        ->toBe('eligibility_outcomes')
        ->and($method->invoke($page, new InvalidArgumentException('Terms label is invalid.')))
        ->toBe('terms_label');
});

it('previews qualification from the program page sandbox', function (): void {
    [$program] = RewardLedgerFixtures::seedVersionedProgram('admin-preview', RewardProgramMode::Points);
    $program = $program->refresh();

    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);

    // The sandbox payload must satisfy the core array contract: string
    // timestamps, string verification, and the beneficiary-role select.
    // No enrollment is supplied, so the deterministic verdict is an
    // ineligible enrollment-missing preview — the notification still
    // carries every refusal reason for the operator.
    Livewire::actingAs($this->staff)
        ->test(ViewRewardProgram::class, ['record' => $program->getKey()])
        ->callAction('preview_qualification', data: [
            'component' => RewardComponent::Points->value,
            'outcome' => 'attendance',
            'verification' => 'accepted',
            'is_first_time' => true,
            'is_attributed' => true,
            'is_claimed_identity' => true,
            'self_credit' => false,
            'beneficiary_role' => RewardBeneficiaryRole::Participant->value,
        ])
        ->assertHasNoActionErrors();

    Notification::assertNotified('Preview: ineligible');
});

it('lets staff decide an appeal from the appeals table', function (): void {
    $award = adminAdaptHeldAward((string) $this->staff->getKey());

    $appeal = OpenRewardAppealAction::run(
        $award,
        'user',
        (string) $award->recipient_id,
        'member',
        'Please recheck my held award.',
        null,
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );
    expect($appeal->status)->toBe(RewardAppealStatus::Open);

    Livewire::actingAs($this->staff)
        ->test(ListRewardAppeals::class)
        ->assertCanSeeTableRecords([$appeal])
        ->callTableAction('decide', $appeal->getKey(), data: [
            'to' => RewardAppealStatus::InReview->value,
            'reason' => 'Reviewing the held evidence.',
        ])
        ->assertHasNoTableActionErrors();

    expect($appeal->fresh()->status)->toBe(RewardAppealStatus::InReview);
    expect(RewardAppeal::query()->whereKey($appeal->getKey())->value('status'))->toBe(RewardAppealStatus::InReview);
});

it('renders finance tables and runs record, verify, and approve actions', function (): void {
    [$program] = RewardLedgerFixtures::seedVersionedProgram('admin-fin-ui', RewardProgramMode::Cash);

    Livewire::actingAs($this->staff)
        ->test(ListRewardFunding::class)
        ->callAction('record_source', data: [
            'source_ref' => 'TREASURY-ui-1',
            'currency' => 'MYR',
            'amount_minor' => 5000,
            'evidence_note' => 'ui test',
        ])
        ->assertHasNoActionErrors()
        ->callAction('record', data: [
            'program_id' => (string) $program->getKey(),
            'currency' => 'MYR',
            'amount_minor' => 5000,
            'source_ref' => 'TREASURY-ui-1',
            'evidence_note' => 'ui test',
        ])
        ->assertHasNoActionErrors();

    expect(RewardFundingEntry::query()->where('source_ref', 'TREASURY-ui-1')->count())->toBe(1);

    $memberId = (string) Str::uuid();

    Livewire::actingAs($this->staff)
        ->test(ListRewardPayees::class)
        ->callAction('verify', data: [
            'recipient_id' => $memberId,
            'method' => 'bank_transfer',
            'account_ref' => 'ACCT-UI',
            'account_display' => 'Desk UI',
            'evidence_note' => 'ui test',
        ])
        ->assertHasNoActionErrors();

    expect(RewardPayee::query()->where('recipient_id', $memberId)->count())->toBe(1);

    [, $request] = adminAdaptCashSetup((string) $this->staff->getKey());

    Livewire::actingAs($this->staff)
        ->test(ListRewardPayouts::class)
        ->assertCanSeeTableRecords([$request])
        ->callTableAction('approve', $request->getKey(), data: [
            'reason' => 'Finance review passed; releasing to the payout queue.',
        ])
        ->assertHasNoTableActionErrors();

    expect($request->fresh()->status->value)->toBe('approved');
});

it('requires funding for every cash variant currency before activation', function (): void {
    config(['rewards.outcomes' => ['attendance', 'signup']]);

    [$program] = RewardLedgerFixtures::seedVersionedProgram('admin-cash-variants', RewardProgramMode::Cash, [
        'components' => [[
            'type' => 'cash',
            'variants' => [
                [
                    'outcomes' => ['attendance'],
                    'roles' => [RewardBeneficiaryRole::Recipient->value],
                    'min_verification' => 'staff_verified',
                    'first_only' => false,
                    'minor_units' => 100,
                    'currency' => 'MYR',
                ],
                [
                    'outcomes' => ['signup'],
                    'roles' => [RewardBeneficiaryRole::Recipient->value],
                    'min_verification' => 'staff_verified',
                    'first_only' => false,
                    'minor_units' => 200,
                    'currency' => 'USD',
                ],
            ],
        ]],
        'eligibility' => [
            'outcomes' => ['attendance', 'signup'],
            'min_verification' => 'staff_verified',
        ],
    ]);
    recordAdminAdaptFunding($program, 'MYR', 10000);

    $ready = TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    $blocked = app(FundedRewardReadinessChecker::class)->canActivate(
        $ready,
        $ready->versions()->orderByDesc('version')->firstOrFail(),
    );

    expect($blocked->ready)->toBeFalse()
        ->and($blocked->blockers)->toContain('no-confirmed-funding:USD');

    recordAdminAdaptFunding($program, 'USD', 10000);
    $active = TransitionRewardProgramStatusAction::run($ready, RewardProgramStatus::Active);

    expect($active->status)->toBe(RewardProgramStatus::Active);
});

it('requires catalog items for every benefit variant key before activation', function (): void {
    config(['rewards.outcomes' => ['attendance', 'signup']]);

    [$program] = RewardLedgerFixtures::seedVersionedProgram('admin-benefit-variants', RewardProgramMode::Benefit, [
        'components' => [[
            'type' => 'benefit',
            'variants' => [
                [
                    'outcomes' => ['attendance'],
                    'roles' => [RewardBeneficiaryRole::Recipient->value],
                    'min_verification' => 'staff_verified',
                    'first_only' => false,
                    'benefit_key' => 'attendance-pack',
                    'conditions' => ['Verified attendance'],
                ],
                [
                    'outcomes' => ['signup'],
                    'roles' => [RewardBeneficiaryRole::Recipient->value],
                    'min_verification' => 'staff_verified',
                    'first_only' => false,
                    'benefit_key' => 'signup-pack',
                    'conditions' => ['Verified signup'],
                ],
            ],
        ]],
        'eligibility' => [
            'outcomes' => ['attendance', 'signup'],
            'min_verification' => 'staff_verified',
        ],
    ]);
    $staffId = RewardLedgerFixtures::staffId();
    $createBenefit = static function (string $key, string $label) use ($program, $staffId): void {
        CreateRewardBenefitItemAction::run(
            $program,
            $key,
            $label,
            ['One verified use'],
            100,
            'pts',
            false,
            10,
            'user',
            $staffId,
            'staff',
        );
    };

    $createBenefit('attendance-pack', 'Attendance Pack');
    $ready = TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    $blocked = app(FundedRewardReadinessChecker::class)->canActivate(
        $ready,
        $ready->versions()->orderByDesc('version')->firstOrFail(),
    );

    expect($blocked->ready)->toBeFalse()
        ->and($blocked->blockers)->toContain('benefit-catalog-unavailable')
        ->and(RewardBenefitItem::query()->where('program_id', $program->getKey())->count())->toBe(1);

    $createBenefit('signup-pack', 'Signup Pack');
    $active = TransitionRewardProgramStatusAction::run($ready, RewardProgramStatus::Active);

    expect($active->status)->toBe(RewardProgramStatus::Active)
        ->and(RewardBenefitItem::query()->where('program_id', $program->getKey())->count())->toBe(2);
});

it('renders catalog, recovery, fraud, and program tables', function (): void {
    [$program] = RewardLedgerFixtures::seedVersionedProgram('admin-cat', RewardProgramMode::Points);
    $staffId = RewardLedgerFixtures::staffId();

    CreateRewardBenefitItemAction::run(
        $program,
        'welcome-credit',
        'Welcome credit',
        ['Good standing'],
        100,
        'pts',
        false,
        10,
        'user',
        $staffId,
        'staff',
    );

    $award = adminAdaptHeldAward((string) $this->staff->getKey());

    foreach ([
        ListRewardPrograms::class => [],
        ListRewardBenefitItems::class => [],
        ListRewardBenefitGrants::class => [],
        ListRewardRecoveries::class => [],
        ListRewardFraudCases::class => [],
    ] as $page => $params) {
        Livewire::actingAs($this->staff)->test($page, $params)->assertOk();
    }

    Livewire::actingAs($this->staff)
        ->test(ListRewardPrograms::class)
        ->assertCanSeeTableRecords([$program]);
});

it('scopes admin queries to programs visible in the current owner context', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $program = OwnerContext::withOwner($owner, fn (): RewardProgram => RewardProgram::factory()->ofMode(RewardProgramMode::Recognition)->withStatus(RewardProgramStatus::Active)->create([
        'family_key' => 'scope-admin-' . Str::lower(Str::random(6)),
    ]));

    $memberId = (string) Str::uuid();

    $award = null;

    OwnerContext::withOwner($owner, function () use ($program, $memberId, &$award): void {
        $version = RewardProgramVersion::factory()->ofMode(RewardProgramMode::Recognition)->create([
            'program_id' => $program->getKey(),
            'effective_from' => CarbonImmutable::parse('2026-06-01T00:00:00Z'),
            'published_at' => CarbonImmutable::parse('2026-06-01T00:00:00Z'),
        ]);

        EnrollRewardMemberAction::run(
            $program,
            'user',
            $memberId,
            $version,
            'member-portal',
            CarbonImmutable::parse('2026-06-10T00:00:00Z'),
        );

        $reader = new FakeRewardSourceFactReader;
        $reader->seed(RewardLedgerFixtures::fact('src-scope-admin-1', $memberId));

        $result = (new EvaluateRewardEntitlementAction($reader))->handle(
            $program,
            RewardComponent::Recognition,
            'user',
            $memberId,
            RewardBeneficiaryRole::Recipient,
            'src-scope-admin-1',
            CarbonImmutable::parse('2026-06-15T00:00:00Z'),
        );

        $award = $result->award;
    });

    // Livewire test calls swap the request, which drops the withOwner()
    // override; rebind the resolver so each request resolves its owner.
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    Livewire::actingAs($owner)->test(ListRewardAwards::class)->assertCanSeeTableRecords([$award]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($other));

    Livewire::actingAs($other)->test(ListRewardAwards::class)->assertCanNotSeeTableRecords([$award]);
});

it('refuses settling a foreign recovery from the admin table', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    [$program, $version, $enrollment, $memberId] = OwnerContext::withOwner(
        $owner,
        static fn (): array => RewardLedgerFixtures::seedActiveEnrolled('adapt-cross-recovery'),
    );

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact('src-adapt-cross-recovery-1', $memberId));

    $award = OwnerContext::withOwner(
        $owner,
        static fn () => (new EvaluateRewardEntitlementAction($reader))->handle(
            $program,
            RewardComponent::Points,
            'user',
            $memberId,
            RewardBeneficiaryRole::Recipient,
            'src-adapt-cross-recovery-1',
            CarbonImmutable::parse('2026-06-15T00:00:00Z'),
        ),
    )->award->refresh();

    $recovery = OwnerContext::withOwner(
        $owner,
        static fn (): RewardRecovery => RewardRecovery::factory()->create([
            'award_id' => $award->getKey(),
            'kind' => RewardRecoveryKind::Dispute,
            'points' => 100,
            'unit' => 'pts',
        ]),
    );

    // Owner B's session invokes the settle table action on owner A's
    // recovery. The recovery table query is award-scoped (RewardFilamentScope),
    // so the action cannot even resolve for a foreign row — and the
    // action-side guard in SettleRewardRecoveryAction remains the handler-level
    // boundary for any other caller (see RewardOwnerScopingTest).
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($other));

    expect(fn () => Livewire::actingAs($other)
        ->test(ListRewardRecoveries::class)
        ->callTableAction('settle', $recovery->getKey(), data: [
            'recovery_proof' => 'BANK-RETURN-1',
        ]))->toThrow(ActionNotResolvableException::class);

    expect($recovery->refresh()->status)->toBe(RewardRecoveryStatus::Open);
});
