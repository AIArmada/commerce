<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentRewards\FilamentRewardsPlugin;
use AIArmada\FilamentRewards\Resources\RewardAppealResource\RewardAppealResource;
use AIArmada\FilamentRewards\Resources\RewardAwardResource\RewardAwardResource;
use AIArmada\FilamentRewards\Resources\RewardBenefitGrantResource\RewardBenefitGrantResource;
use AIArmada\FilamentRewards\Resources\RewardBenefitItemResource\RewardBenefitItemResource;
use AIArmada\FilamentRewards\Resources\RewardFraudResource\RewardFraudResource;
use AIArmada\FilamentRewards\Resources\RewardFundingResource\RewardFundingResource;
use AIArmada\FilamentRewards\Resources\RewardPayeeResource\RewardPayeeResource;
use AIArmada\FilamentRewards\Resources\RewardPayoutResource\RewardPayoutResource;
use AIArmada\FilamentRewards\Resources\RewardProgramResource\RewardProgramResource;
use AIArmada\FilamentRewards\Resources\RewardRecoveryResource\RewardRecoveryResource;
use AIArmada\FilamentRewards\Support\RewardAdminPresenter;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardComponent;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardProgram;
use Illuminate\Support\Str;

describe('FilamentRewardsPlugin', function (): void {
    it('creates a plugin instance with the package id', function (): void {
        $plugin = FilamentRewardsPlugin::make();

        expect($plugin)->toBeInstanceOf(FilamentRewardsPlugin::class)
            ->and($plugin->getId())->toBe('filament-rewards');
    });

    it('resolves all ten resources', function (): void {
        foreach ([
            RewardProgramResource::class,
            RewardAwardResource::class,
            RewardAppealResource::class,
            RewardFundingResource::class,
            RewardPayeeResource::class,
            RewardPayoutResource::class,
            RewardBenefitItemResource::class,
            RewardBenefitGrantResource::class,
            RewardRecoveryResource::class,
            RewardFraudResource::class,
        ] as $resource) {
            expect(class_exists($resource))->toBeTrue();
            expect($resource::getPages())->toHaveKey('index');
        }
    });
});

describe('reward resource navigation', function (): void {
    it('reads the navigation group from config', function (): void {
        config()->set('filament-rewards.navigation.group', 'Loyalty');

        expect(RewardProgramResource::getNavigationGroup())->toBe('Loyalty')
            ->and(RewardAwardResource::getNavigationGroup())->toBe('Loyalty');
    });

    it('reads navigation sort order from config', function (): void {
        config()->set('filament-rewards.navigation.sort.awards', 42);

        expect(RewardAwardResource::getNavigationSort())->toBe(42)
            ->and(RewardProgramResource::getNavigationSort())->toBe(1);
    });
});

describe('RewardAdminPresenter', function (): void {
    it('serves outcome and currency options from the rewards registry', function (): void {
        expect(RewardAdminPresenter::outcomeOptions())->toBe([
            'contribution' => 'contribution',
            'attendance' => 'attendance',
            'referral' => 'referral',
            'registration' => 'registration',
            'person_follow' => 'person_follow',
            'org_follow' => 'org_follow',
            'series_follow' => 'series_follow',
            'reference_follow' => 'reference_follow',
            'saved_search' => 'saved_search',
            'saved_item' => 'saved_item',
            'rsvp' => 'rsvp',
        ])->and(RewardAdminPresenter::currencyOptions())->toHaveKeys(['MYR', 'USD']);
    });

    it('serves payout methods from configured methods', function (): void {
        config()->set('rewards.payouts.methods', ['manual', 'bank_transfer']);

        expect(RewardAdminPresenter::payeeMethodOptions())->toBe([
            'manual' => 'Manual / bank handoff',
            'bank_transfer' => 'Bank transfer',
        ]);
    });

    it('labels recipients through the morph map without leaking across requests', function (): void {
        $user = User::factory()->create(['email' => 'recipient-' . Str::lower(Str::random(6)) . '@example.com']);

        expect(RewardAdminPresenter::recipientLabel(User::class, (string) $user->getKey()))->toBe($user->email)
            ->and(RewardAdminPresenter::recipientLabel('user', (string) Str::uuid()))->toBeString();
    });
});

describe('reward admin query scoping', function (): void {
    it('hides awards of foreign programs from the award table query', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $program = OwnerContext::withOwner($owner, fn (): RewardProgram => RewardProgram::factory()->create([
            'family_key' => 'fil-scope-' . Str::lower(Str::random(6)),
        ]));
        $foreign = OwnerContext::withOwner($other, fn (): RewardProgram => RewardProgram::factory()->create([
            'family_key' => 'fil-foreign-' . Str::lower(Str::random(6)),
        ]));

        $award = RewardAward::factory()->create([
            'program_id' => $program->getKey(),
            'component' => RewardComponent::Points,
            'status' => RewardAwardStatus::Held,
        ]);
        $stranger = RewardAward::factory()->create([
            'program_id' => $foreign->getKey(),
            'component' => RewardComponent::Points,
            'status' => RewardAwardStatus::Held,
        ]);

        $visible = OwnerContext::withOwner($owner, fn () => RewardAwardResource::getEloquentQuery()->pluck('id')->all());

        expect($visible)->toContain((string) $award->getKey())
            ->and($visible)->not->toContain((string) $stranger->getKey());
    });

    it('keeps the fraud queue to held and pending awards', function (): void {
        $program = RewardProgram::factory()->create(['family_key' => 'fil-fraud-' . Str::lower(Str::random(6))]);

        RewardAward::factory()->create([
            'program_id' => $program->getKey(),
            'component' => RewardComponent::Points,
            'status' => RewardAwardStatus::Approved,
        ]);
        $held = RewardAward::factory()->create([
            'program_id' => $program->getKey(),
            'component' => RewardComponent::Points,
            'status' => RewardAwardStatus::Held,
        ]);

        $visible = RewardFraudResource::getEloquentQuery()->pluck('status')->all();

        expect($visible)->not->toContain(RewardAwardStatus::Approved->value)
            ->and(RewardFraudResource::getEloquentQuery()->whereKey($held->getKey())->exists())->toBeTrue();
    });
});
