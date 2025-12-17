<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\AccessPolicy;
use AIArmada\FilamentAuthz\Pages\PolicyDesignerPage;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Mockery::close();
});

test('policy designer initializes with a default condition', function (): void {
    $user = User::create([
        'name' => 'Policy Admin',
        'email' => 'policy-admin@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = new PolicyDesignerPage;
    $page->mount();

    expect($page->conditions)->toHaveCount(1)
        ->and($page->conditions[0])->toHaveKeys(['id', 'type', 'field', 'operator', 'value']);
});

test('policy designer can add remove and update condition type', function (): void {
    $user = User::create([
        'name' => 'Policy Admin 2',
        'email' => 'policy-admin-2@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = new PolicyDesignerPage;
    $page->mount();

    $page->addCondition();
    expect($page->conditions)->toHaveCount(2);

    $page->updateConditionType(0, 'permission');
    expect($page->conditions[0]['type'])->toBe('permission')
        ->and($page->conditions[0]['field'])->toBe('permission')
        ->and($page->conditions[0]['operator'])->toBe('equals')
        ->and($page->conditions[0]['value'])->toBe('');

    $page->updateConditionType(0, 'does-not-exist');
    expect($page->conditions[0]['type'])->toBe('permission');

    $page->removeCondition(0);
    expect($page->conditions)->toHaveCount(1);
});

test('policy designer preview methods return content', function (): void {
    $user = User::create([
        'name' => 'Policy Admin 3',
        'email' => 'policy-admin-3@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = new PolicyDesignerPage;
    $page->mount();

    $page->policyName = 'Custom Policy';
    $page->policyDescription = 'Example policy';
    $page->effect = 'allow';
    $page->priority = 10;

    $json = $page->getPreviewJson();
    expect($json)->toContain('Custom Policy')
        ->and($json)->toContain('Example policy');

    $code = $page->getPreviewCode();
    expect($code)->toContain('class CustomPolicy')
        ->and($code)->toContain('Generated from Policy Designer');
});

test('policy designer saves policy when name is provided', function (): void {
    $user = User::create([
        'name' => 'Policy Admin 4',
        'email' => 'policy-admin-4@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = new PolicyDesignerPage;
    $page->mount();

    $page->policyName = 'My Policy';
    $page->policyDescription = 'Test policy';
    $page->effect = 'deny';
    $page->priority = 5;
    $page->combiningAlgorithm = 'any';

    $page->savePolicy();

    $policy = AccessPolicy::query()->where('name', 'My Policy')->first();

    expect($policy)->not->toBeNull()
        ->and($policy?->description)->toBe('Test policy')
        ->and($policy?->effect)->toBe('deny')
        ->and($policy?->priority)->toBe(5)
        ->and($policy?->is_active)->toBeTrue();
});

test('policy designer header actions expose save test and reset actions', function (): void {
    $page = new PolicyDesignerPage;

    $method = new ReflectionMethod($page, 'getHeaderActions');
    $method->setAccessible(true);

    /** @var array<int, Action> $actions */
    $actions = $method->invoke($page);

    $names = collect($actions)
        ->map(fn (Action $action): string => $action->getName())
        ->all();

    expect($names)->toContain('save')
        ->and($names)->toContain('test')
        ->and($names)->toContain('reset');
});
