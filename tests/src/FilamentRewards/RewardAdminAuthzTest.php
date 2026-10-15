<?php

declare(strict_types=1);

use AIArmada\Authz\Models\Role;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentRewards\Resources\RewardAppealResource\Pages\ListRewardAppeals;
use AIArmada\FilamentRewards\Resources\RewardAwardResource\Pages\ListRewardAwards;
use AIArmada\FilamentRewards\Resources\RewardFraudResource\Pages\ListRewardFraudCases;
use AIArmada\FilamentRewards\Resources\RewardPayeeResource\Pages\ListRewardPayees;
use AIArmada\FilamentRewards\Resources\RewardProgramResource\Pages\CreateRewardProgram;
use AIArmada\Rewards\Actions\EnrollRewardMemberAction;
use AIArmada\Rewards\Actions\EvaluateRewardEntitlementAction;
use AIArmada\Rewards\Actions\OpenRewardAppealAction;
use AIArmada\Rewards\Actions\TransitionRewardProgramStatusAction;
use AIArmada\Rewards\Contracts\RewardFinancialReadinessChecker;
use AIArmada\Rewards\Contracts\RewardReadinessChecker;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardBeneficiaryRole;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Enums\RewardProgramStatus;
use AIArmada\Rewards\Enums\RewardSourceFraudStatus;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Support\FundedRewardFinancialReadinessChecker;
use AIArmada\Rewards\Support\FundedRewardReadinessChecker;
use AIArmada\Rewards\Tests\Ledgers\Support\FakeRewardSourceFactReader;
use AIArmada\Rewards\Tests\Ledgers\Support\RewardLedgerFixtures;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * Staff authorization boundary for the rewards panel (F8).
 *
 * This file deliberately does NOT register the blanket
 * `Gate::before(fn () => true)` hook used by the general admin adapt tests:
 * these cases must observe the real Gate outcome.
 *
 * Record-action handlers are the security boundary, but Filament v5 also
 * refuses to mount or call a hidden action (`isDisabled()` folds in
 * `isHidden()`), so a Gate-denied action can never be reached through
 * `callTableAction()`. The handler re-checks are therefore probed by invoking
 * the configured action closure directly — exactly what `callMountedAction()`
 * does once an action is mounted — which is the only way to prove the handler
 * aborts rather than relying on `visible()` for authorization.
 */
beforeEach(function (): void {
    app()->bind(RewardReadinessChecker::class, FundedRewardReadinessChecker::class);
    app()->bind(RewardFinancialReadinessChecker::class, FundedRewardFinancialReadinessChecker::class);

    $this->staff = User::factory()->create();
    $this->staff->assignRole(Role::findOrCreate('admin', 'web'));
});

function rewardAuthzHeldAward(bool $suspect = false): RewardAward
{
    $memberId = (string) Str::uuid();

    [$program, $version] = RewardLedgerFixtures::seedVersionedProgram(
        'reward-authz',
        RewardProgramMode::Recognition,
        ['holds' => ['manual_review_outcomes' => ['attendance']]],
    );

    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Ready);
    TransitionRewardProgramStatusAction::run($program, RewardProgramStatus::Active);
    EnrollRewardMemberAction::run($program->refresh(), 'user', $memberId, $version, 'member-portal', CarbonImmutable::parse('2026-06-10T00:00:00Z'));

    $reader = new FakeRewardSourceFactReader;
    $reader->seed(RewardLedgerFixtures::fact(
        'src-reward-authz-' . $memberId,
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
        'src-reward-authz-' . $memberId,
        CarbonImmutable::parse('2026-06-15T00:00:00Z'),
    );

    return $result->award->refresh();
}

/**
 * Resolve a record action off a page's configured table and invoke its handler
 * closure, returning the abort status (null when the handler ran to
 * completion). Deliberately bypasses Filament's visibility/disabled gate.
 *
 * `$beforeInvoke` runs after the page has mounted and the action is resolved,
 * which lets a test revoke an ability that the page-level gate also consults.
 *
 * @param  array<string, mixed>  $data
 */
function rewardAuthzInvokeAction(
    Authenticatable $actor,
    string $page,
    string $actionName,
    Model $record,
    array $data = [],
    ?Closure $beforeInvoke = null,
): ?int {
    $component = Livewire::actingAs($actor)->test($page);
    $action = $component->instance()->getTable()->getAction($actionName);

    expect($action)->toBeInstanceOf(Action::class);

    $action->record($record);
    $action->data($data);

    $beforeInvoke?->__invoke();

    try {
        $action->call();
    } catch (HttpException $exception) {
        return $exception->getStatusCode();
    }

    return null;
}

it('forbids a non-staff user from listing payees', function (): void {
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test(ListRewardPayees::class)
        ->assertStatus(403);
});

it('forbids a non-staff user from creating a program', function (): void {
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test(CreateRewardProgram::class)
        ->assertStatus(403);

    expect(RewardProgram::query()->count())->toBe(0);
});

// Contract guard: the create form's owner Select already rejects values it
// cannot resolve against `filament-rewards.owners.types`, so this passes before
// the handler hardening too. It locks the outcome (owner-field error, no row)
// that the server-side resolution must preserve.
it('rejects an owner alias that is not configured for the panel', function (): void {
    Livewire::actingAs($this->staff)
        ->test(CreateRewardProgram::class)
        ->fillForm([
            'family_key' => 'authz-unconfigured',
            'title' => 'Authz unconfigured',
            'mode' => RewardProgramMode::Recognition->value,
            'purpose' => 'authz coverage',
            'owner_type' => 'organization',
            'owner_record_id' => (string) Str::uuid(),
        ])
        ->call('create')
        ->assertHasFormErrors(['owner_record_id']);

    expect(RewardProgram::query()->count())->toBe(0);
});

// Contract guard: like the unconfigured-alias case, the owner Select resolves
// the record for its label lookup, so a missing record is already refused by
// the form. The handler check is the fail-closed backstop for crafted payloads.
it('rejects a configured owner alias that points at a missing record', function (): void {
    config()->set('filament-rewards.owners.types', [
        User::class => ['model' => User::class, 'label' => 'User', 'name_column' => 'name'],
    ]);

    $component = Livewire::actingAs($this->staff)
        ->test(CreateRewardProgram::class)
        ->fillForm([
            'family_key' => 'authz-missing-owner',
            'title' => 'Authz missing owner',
            'mode' => RewardProgramMode::Recognition->value,
            'purpose' => 'authz coverage',
            'owner_type' => User::class,
            'owner_record_id' => (string) Str::uuid(),
        ])
        ->call('create')
        ->assertHasFormErrors(['owner_record_id']);

    expect(RewardProgram::query()->withoutOwnerScope()->count())->toBe(0);
});

it('creates a program owned by the resolved owner record, not the configured alias', function (): void {
    config()->set('filament-rewards.owners.types', [
        'member' => ['model' => User::class, 'label' => 'Member', 'name_column' => 'name'],
    ]);

    // The program's owner tuple must match the active owner context
    // (HasOwner::assertOwnerMatchesCurrentContext), and the Spatie team
    // resolver reads the same context, so reuse the harness default owner.
    $owner = OwnerContext::resolve();

    expect($owner)->toBeInstanceOf(Model::class);

    Livewire::actingAs($this->staff)
        ->test(CreateRewardProgram::class)
        ->fillForm([
            'family_key' => 'authz-owned',
            'title' => 'Authz owned',
            'mode' => RewardProgramMode::Recognition->value,
            'purpose' => 'authz coverage',
            'owner_type' => 'member',
            'owner_record_id' => (string) $owner->getKey(),
        ])
        ->call('create');

    $program = RewardProgram::query()->where('family_key', 'authz-owned')->firstOrFail();

    expect($program->owner_type)->toBe($owner->getMorphClass())
        ->and($program->owner_id)->toBe((string) $owner->getKey());
});

it('attaches an invalid configured owner UUID to the owner field instead of family key', function (): void {
    config()->set('filament-rewards.owners.types', [
        'member' => ['model' => User::class, 'label' => 'Member', 'name_column' => 'name'],
    ]);

    $owner = User::factory()->create(['id' => 'not-a-uuid']);

    $component = Livewire::actingAs($this->staff)
        ->test(CreateRewardProgram::class)
        ->fillForm([
            'family_key' => 'authz-invalid-owner-id',
            'title' => 'Authz invalid owner id',
            'mode' => RewardProgramMode::Recognition->value,
            'purpose' => 'authz coverage',
            'owner_type' => 'member',
            'owner_record_id' => (string) $owner->getKey(),
        ])
        ->call('create');

    expect($component->errors()->keys())->toBe(['owner_record_id']);

    expect(RewardProgram::query()->withoutOwnerScope()->count())->toBe(0);
});

it('refuses an award decision the Gate denies, even when the action is invoked directly', function (string $actionName, string $ability, bool $suspect): void {
    $award = rewardAuthzHeldAward($suspect);
    expect($award->status)->toBe(RewardAwardStatus::Held);

    Gate::define($ability, static fn (): bool => false);

    $status = rewardAuthzInvokeAction(
        $this->staff,
        ListRewardAwards::class,
        $actionName,
        $award,
        ['reason' => 'Attempted decision without the ability.'],
    );

    expect($status)->toBe(403)
        ->and($award->fresh()->status)->toBe(RewardAwardStatus::Held);
})->with([
    'approve' => ['approve', 'approve', false],
    'reject' => ['reject', 'review', false],
    'reverse' => ['reverse', 'reverse', false],
    // A suspect source, so the dismissal itself would otherwise succeed.
    'dismiss_fraud' => ['dismiss_fraud', 'dismissFraud', true],
]);

it('refuses an appeal decision the Gate denies, even when the action is invoked directly', function (): void {
    $award = rewardAuthzHeldAward();

    $appeal = OpenRewardAppealAction::run(
        $award,
        'user',
        (string) $award->recipient_id,
        'member',
        'Please recheck my held award.',
        null,
        CarbonImmutable::parse('2026-06-16T00:00:00Z'),
    );

    $before = $appeal->status;

    Gate::define('decide', static fn (): bool => false);

    $status = rewardAuthzInvokeAction(
        $this->staff,
        ListRewardAppeals::class,
        'decide',
        $appeal,
        ['to' => 'in_review', 'reason' => 'Attempted decision without the ability.'],
    );

    expect($status)->toBe(403)
        ->and($appeal->fresh()->status)->toBe($before);
});

it('refuses a fraud dismissal without the steward ability, even when the action is invoked directly', function (): void {
    $award = rewardAuthzHeldAward(true);
    expect($award->status)->toBe(RewardAwardStatus::Held);

    // The fraud page itself is gated on the steward ability, so grant it for
    // the mount and revoke it before the handler runs: the staff role alone
    // must not be enough to dismiss a fraud signal.
    Gate::define('reward-fraud-steward', static fn (): bool => true);

    $status = rewardAuthzInvokeAction(
        $this->staff,
        ListRewardFraudCases::class,
        'dismiss',
        $award,
        ['reason' => 'Attempted dismissal without stewardship.'],
        static function (): void {
            Gate::define('reward-fraud-steward', static fn (): bool => false);
        },
    );

    expect($status)->toBe(403)
        ->and($award->fresh()->status)->toBe(RewardAwardStatus::Held);
});
