<?php

declare(strict_types=1);

use AIArmada\Rewards\Enums\RewardProgramMode;
use AIArmada\Rewards\Support\RewardPublishAssembler;
use AIArmada\Rewards\Support\RewardRuleValidator;
use AIArmada\Rewards\Tests\Core\Support\RewardCoreTestFixtures;

it('accepts validator-clean rules and terms for every mode', function (RewardProgramMode $mode): void {
    $validator = new RewardRuleValidator;

    expect($validator->errors(RewardCoreTestFixtures::rules($mode), $mode, 'own-family'))->toBe([])
        ->and($validator->termsErrors(RewardCoreTestFixtures::terms($mode)))->toBe([]);
})->with(RewardProgramMode::cases());

it('rejects unknown keys at every level', function (): void {
    $validator = new RewardRuleValidator;
    $rules = RewardCoreTestFixtures::rules();
    $rules['expression'] = 'points * 2';

    expect($validator->errors($rules, RewardProgramMode::Points, 'own-family'))
        ->toContain('Unknown rules key [expression].');

    $rules = RewardCoreTestFixtures::rules();
    $rules['components'][0]['formula'] = '1+1';

    expect($validator->errors($rules, RewardProgramMode::Points, 'own-family')[0])->toContain('unknown key [formula]');
});

it('enforces per-component amount shapes', function (): void {
    $validator = new RewardRuleValidator;

    $recognition = RewardCoreTestFixtures::rules(RewardProgramMode::Recognition);
    $recognition['components'][0]['points'] = 5;
    expect($validator->errors($recognition, RewardProgramMode::Recognition, 'own-family')[0])->toContain('must not carry [points]');

    $points = RewardCoreTestFixtures::rules();
    $points['components'][0]['points'] = 0;
    expect($validator->errors($points, RewardProgramMode::Points, 'own-family')[0])->toContain('integer 1..');

    $points = RewardCoreTestFixtures::rules();
    $points['components'][0]['points'] = 1.5;
    expect($validator->errors($points, RewardProgramMode::Points, 'own-family')[0])->toContain('integer 1..');

    $points = RewardCoreTestFixtures::rules();
    $points['components'][0]['currency'] = 'MYR';
    expect($validator->errors($points, RewardProgramMode::Points, 'own-family')[0])->toContain('must not carry [currency]');

    $cash = RewardCoreTestFixtures::rules(RewardProgramMode::Cash);
    unset($cash['components'][0]['currency']);
    expect($validator->errors($cash, RewardProgramMode::Cash, 'own-family')[0])->toContain('currency must be one of');

    $cash = RewardCoreTestFixtures::rules(RewardProgramMode::Cash);
    $cash['components'][0]['currency'] = 'XXX';
    expect($validator->errors($cash, RewardProgramMode::Cash, 'own-family')[0])->toContain('currency must be one of');

    $benefit = RewardCoreTestFixtures::rules(RewardProgramMode::Benefit);
    $benefit['components'][0]['conditions'] = [];
    expect($validator->errors($benefit, RewardProgramMode::Benefit, 'own-family')[0])->toContain('explicit list');
});

it('rejects amount overflow and duplicate components', function (): void {
    $validator = new RewardRuleValidator;

    $points = RewardCoreTestFixtures::rules();
    $points['components'][0]['points'] = RewardRuleValidator::MAX_POINTS + 1;
    expect($validator->errors($points, RewardProgramMode::Points, 'own-family')[0])->toContain('integer 1..');

    $cash = RewardCoreTestFixtures::rules(RewardProgramMode::Cash);
    $cash['components'][0]['minor_units'] = RewardRuleValidator::MAX_MINOR_UNITS + 1;
    expect($validator->errors($cash, RewardProgramMode::Cash, 'own-family')[0])->toContain('integer 1..');

    $combined = RewardCoreTestFixtures::rules(RewardProgramMode::Combined);
    $combined['components'][] = ['type' => 'points', 'points' => 5, 'unit' => 'pts'];
    expect($validator->errors($combined, RewardProgramMode::Combined, 'own-family')[0])->toContain('Duplicate component');
});

it('rejects unknown outcomes and circular stacking', function (): void {
    $validator = new RewardRuleValidator;

    $rules = RewardCoreTestFixtures::rules();
    $rules['eligibility']['outcomes'][] = 'teleport_visit';
    expect($validator->errors($rules, RewardProgramMode::Points, 'own-family')[0])->toContain('allowlisted outcome');

    $rules = RewardCoreTestFixtures::rules();
    $rules['stacking']['compatible_with'] = ['own-family'];
    expect($validator->errors($rules, RewardProgramMode::Points, 'own-family')[0])->toContain('circular');
});

it('requires verified evidence for cash and benefit components', function (): void {
    $validator = new RewardRuleValidator;

    $cash = RewardCoreTestFixtures::rules(RewardProgramMode::Cash);
    $cash['eligibility']['min_verification'] = 'accepted';
    expect($validator->errors($cash, RewardProgramMode::Cash, 'own-family')[0])
        ->toContain('accepted registration is not attendance');

    $benefit = RewardCoreTestFixtures::rules(RewardProgramMode::Benefit);
    $benefit['eligibility']['min_verification'] = 'unverified';
    expect($validator->errors($benefit, RewardProgramMode::Benefit, 'own-family')[0])
        ->toContain('staff_verified or higher');
});

it('rejects malformed terms snapshots', function (): void {
    $validator = new RewardRuleValidator;

    expect($validator->termsErrors([])[0])->toContain('Terms label');

    $terms = RewardCoreTestFixtures::terms();
    $terms['material'] = [];
    expect($validator->termsErrors($terms)[0])->toContain('non-empty associative map');
});

it('assembles and validates outcome-specific component amounts and milestone levels', function (): void {
    config(['rewards.outcomes' => ['signup', 'attendance']]);

    $payload = RewardPublishAssembler::assemble(RewardProgramMode::Combined, [
        'component_rows' => [[
            'type' => 'points',
            'variants' => [
                [
                    'outcomes' => ['signup'],
                    'roles' => ['sharer'],
                    'min_verification' => 'accepted',
                    'first_only' => true,
                    'points' => 5,
                    'unit' => 'pts',
                ],
                [
                    'outcomes' => ['attendance'],
                    'roles' => ['participant'],
                    'min_verification' => 'staff_verified',
                    'first_only' => false,
                    'points' => 15,
                    'unit' => 'pts',
                ],
            ],
        ]],
        'milestone_rows' => [[
            'key' => 'bintang-1',
            'label' => 'Bintang 1',
            'outcomes' => ['attendance'],
            'roles' => ['participant'],
            'min_verification' => 'staff_verified',
            'first_only' => false,
            'target_count' => 1,
            'window_days' => 30,
            'reward_type' => 'badge',
            'badge_key' => 'bintang-1',
        ]],
        'eligibility_outcomes' => ['signup', 'attendance'],
        'eligibility_categories' => '',
        'min_verification' => 'accepted',
        'preclaim_lookback_days' => 30,
        'hold_days' => 0,
        'terms_label' => 'Member participation',
        'terms_version_label' => 'v1',
        'terms_body' => 'Earn points for eligible community activity.',
        'terms_earn_basis' => 'Verified facts are counted once.',
        'terms_changes' => 'Changes apply prospectively.',
        'terms_no_ranking' => 'There is no ranking or spiritual merit claim.',
    ]);

    expect((new RewardRuleValidator)->errors($payload['rules'], RewardProgramMode::Combined, 'own-family'))->toBe([])
        ->and($payload['rules']['components'][0]['variants'][0]['points'])->toBe(5)
        ->and($payload['rules']['components'][0]['variants'][1]['points'])->toBe(15)
        ->and($payload['rules']['milestones'][0]['label'])->toBe('Bintang 1');
});

it('keeps legacy flat publications valid when they have no milestone rows', function (): void {
    $payload = RewardPublishAssembler::assemble(RewardProgramMode::Recognition, [
        'recognition_template' => 'quiet-thanks',
        'eligibility_outcomes' => ['attendance'],
        'eligibility_categories' => '',
        'min_verification' => 'accepted',
        'preclaim_lookback_days' => 30,
        'hold_days' => 0,
        'terms_label' => 'Member participation',
        'terms_version_label' => 'legacy-v1',
        'terms_body' => 'Earn recognition for eligible activity.',
        'terms_earn_basis' => 'Verified facts are counted once.',
        'terms_changes' => 'Changes apply prospectively.',
        'terms_no_ranking' => 'There is no public ranking.',
    ]);

    expect($payload['rules']['components'][0])->toBe([
        'type' => 'recognition',
        'template' => 'quiet-thanks',
    ])
        ->and(array_key_exists('milestones', $payload['rules']))->toBeFalse()
        ->and((new RewardRuleValidator)->errors($payload['rules'], RewardProgramMode::Recognition, 'own-family'))->toBe([]);
});

it('rejects overlapping outcome and role variants', function (): void {
    config(['rewards.outcomes' => ['signup', 'attendance']]);
    $rules = RewardCoreTestFixtures::rules();
    $rules['eligibility']['outcomes'] = ['signup', 'attendance'];
    $rules['components'] = [[
        'type' => 'points',
        'variants' => [
            ['outcomes' => ['signup'], 'roles' => ['sharer'], 'min_verification' => 'accepted', 'first_only' => false, 'points' => 5, 'unit' => 'pts'],
            ['outcomes' => ['signup', 'attendance'], 'roles' => ['sharer'], 'min_verification' => 'accepted', 'first_only' => false, 'points' => 15, 'unit' => 'pts'],
        ],
    ]];

    expect((new RewardRuleValidator)->errors($rules, RewardProgramMode::Points, 'own-family'))
        ->toContain('Component [0] variants overlap for outcome [signup] and role [sharer].');
});

it('reports field-specific messages for unknown outcomes, negative amounts, duplicate milestone keys, and empty outcomes', function (): void {
    $validator = new RewardRuleValidator;

    $unknownOutcome = RewardCoreTestFixtures::rules();
    $unknownOutcome['eligibility']['outcomes'] = ['teleport_visit'];
    expect($validator->errors($unknownOutcome, RewardProgramMode::Points, 'own-family'))
        ->toContain('Eligibility outcomes must all be allowlisted outcome values.');

    $negativeAmount = RewardCoreTestFixtures::rules();
    $negativeAmount['components'][0]['points'] = -1;
    expect($validator->errors($negativeAmount, RewardProgramMode::Points, 'own-family'))
        ->toContain('Component [0] (points) points must be an integer 1..1000000.');

    $milestone = [
        'key' => 'bintang-1',
        'outcomes' => ['attendance'],
        'roles' => ['participant'],
        'target_count' => 1,
        'window_days' => 30,
        'reward' => ['type' => 'points', 'points' => 5, 'unit' => 'pts'],
    ];
    $duplicateMilestones = RewardCoreTestFixtures::rules();
    $duplicateMilestones['milestones'] = [$milestone, $milestone];
    expect($validator->errors($duplicateMilestones, RewardProgramMode::Points, 'own-family'))
        ->toContain('Duplicate milestone key [bintang-1].');

    $duplicateMilestoneOutcomes = RewardCoreTestFixtures::rules();
    $duplicateMilestoneOutcomes['milestones'] = [[...$milestone, 'outcomes' => ['attendance', 'attendance']]];
    expect($validator->errors($duplicateMilestoneOutcomes, RewardProgramMode::Points, 'own-family'))
        ->toContain('Milestone [0] has duplicate outcome [attendance].');

    $duplicateMilestoneRoles = RewardCoreTestFixtures::rules();
    $duplicateMilestoneRoles['milestones'] = [[...$milestone, 'roles' => ['participant', 'participant']]];
    expect($validator->errors($duplicateMilestoneRoles, RewardProgramMode::Points, 'own-family'))
        ->toContain('Milestone [0] has duplicate role [participant].');

    $emptyOutcomes = RewardCoreTestFixtures::rules();
    $emptyOutcomes['milestones'] = [[...$milestone, 'outcomes' => []]];
    expect($validator->errors($emptyOutcomes, RewardProgramMode::Points, 'own-family'))
        ->toContain('Milestone [0] outcomes must be a non-empty allowlisted list.');
});

it('rejects unknown assembler row keys instead of silently dropping them', function (): void {
    expect(fn () => RewardPublishAssembler::assemble(RewardProgramMode::Points, [
        'component_rows' => [[
            'type' => 'points',
            'unknown_amount_field' => 50,
        ]],
    ]))->toThrow(InvalidArgumentException::class, 'unknown key [unknown_amount_field]');
});

it('prevents badges-mode programs from granting points through milestones', function (): void {
    $rules = RewardCoreTestFixtures::rules(RewardProgramMode::Badges);
    $rules['milestones'] = [[
        'key' => 'points-tier',
        'outcomes' => ['attendance'],
        'roles' => ['participant'],
        'target_count' => 1,
        'window_days' => 30,
        'reward' => ['type' => 'points', 'points' => 5, 'unit' => 'pts'],
    ]];

    expect((new RewardRuleValidator)->errors($rules, RewardProgramMode::Badges, 'own-family'))
        ->toContain('Milestone [0] reward type [points] is not allowed in program mode [badges].');
});
