<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Enums\ImpactLevel;
use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Enums\PolicyCombiningAlgorithm;
use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;

// AuditSeverity tests
test('AuditSeverity has all cases', function (): void {
    expect(AuditSeverity::cases())->toHaveCount(4);
});

test('AuditSeverity label returns correct values', function (): void {
    expect(AuditSeverity::Low->label())->toBe('Low')
        ->and(AuditSeverity::Medium->label())->toBe('Medium')
        ->and(AuditSeverity::High->label())->toBe('High')
        ->and(AuditSeverity::Critical->label())->toBe('Critical');
});

test('AuditSeverity description returns non-empty', function (): void {
    foreach (AuditSeverity::cases() as $case) {
        expect($case->description())->toBeString()->not->toBeEmpty();
    }
});

test('AuditSeverity color returns valid values', function (): void {
    expect(AuditSeverity::Low->color())->toBe('gray')
        ->and(AuditSeverity::Medium->color())->toBe('info')
        ->and(AuditSeverity::High->color())->toBe('warning')
        ->and(AuditSeverity::Critical->color())->toBe('danger');
});

test('AuditSeverity icon returns heroicons', function (): void {
    foreach (AuditSeverity::cases() as $case) {
        expect($case->icon())->toStartWith('heroicon-');
    }
});

test('AuditSeverity numericLevel returns ascending values', function (): void {
    expect(AuditSeverity::Low->numericLevel())->toBe(1)
        ->and(AuditSeverity::Medium->numericLevel())->toBe(2)
        ->and(AuditSeverity::High->numericLevel())->toBe(3)
        ->and(AuditSeverity::Critical->numericLevel())->toBe(4);
});

test('AuditSeverity shouldNotify returns true for high severities', function (): void {
    expect(AuditSeverity::Low->shouldNotify())->toBeFalse()
        ->and(AuditSeverity::Medium->shouldNotify())->toBeFalse()
        ->and(AuditSeverity::High->shouldNotify())->toBeTrue()
        ->and(AuditSeverity::Critical->shouldNotify())->toBeTrue();
});

test('AuditSeverity retentionDays increases with severity', function (): void {
    expect(AuditSeverity::Low->retentionDays())->toBe(30)
        ->and(AuditSeverity::Medium->retentionDays())->toBe(90)
        ->and(AuditSeverity::High->retentionDays())->toBe(365)
        ->and(AuditSeverity::Critical->retentionDays())->toBe(730);
});

// ImpactLevel tests
test('ImpactLevel has all cases', function (): void {
    expect(ImpactLevel::cases())->toHaveCount(5);
});

test('ImpactLevel label returns correct values', function (): void {
    expect(ImpactLevel::None->label())->toBe('No Impact')
        ->and(ImpactLevel::Low->label())->toBe('Low Impact')
        ->and(ImpactLevel::Medium->label())->toBe('Medium Impact')
        ->and(ImpactLevel::High->label())->toBe('High Impact')
        ->and(ImpactLevel::Critical->label())->toBe('Critical Impact');
});

test('ImpactLevel description returns non-empty', function (): void {
    foreach (ImpactLevel::cases() as $case) {
        expect($case->description())->toBeString()->not->toBeEmpty();
    }
});

test('ImpactLevel color returns valid values', function (): void {
    expect(ImpactLevel::None->color())->toBe('gray')
        ->and(ImpactLevel::Low->color())->toBe('success')
        ->and(ImpactLevel::Medium->color())->toBe('info')
        ->and(ImpactLevel::High->color())->toBe('warning')
        ->and(ImpactLevel::Critical->color())->toBe('danger');
});

test('ImpactLevel icon returns heroicons', function (): void {
    foreach (ImpactLevel::cases() as $case) {
        expect($case->icon())->toStartWith('heroicon-');
    }
});

test('ImpactLevel numericLevel returns ascending values', function (): void {
    expect(ImpactLevel::None->numericLevel())->toBe(0)
        ->and(ImpactLevel::Low->numericLevel())->toBe(1)
        ->and(ImpactLevel::Medium->numericLevel())->toBe(2)
        ->and(ImpactLevel::High->numericLevel())->toBe(3)
        ->and(ImpactLevel::Critical->numericLevel())->toBe(4);
});

test('ImpactLevel requiresApproval returns true for high levels', function (): void {
    expect(ImpactLevel::None->requiresApproval())->toBeFalse()
        ->and(ImpactLevel::Low->requiresApproval())->toBeFalse()
        ->and(ImpactLevel::Medium->requiresApproval())->toBeFalse()
        ->and(ImpactLevel::High->requiresApproval())->toBeTrue()
        ->and(ImpactLevel::Critical->requiresApproval())->toBeTrue();
});

test('ImpactLevel requiresConfirmation returns true for medium and higher', function (): void {
    expect(ImpactLevel::None->requiresConfirmation())->toBeFalse()
        ->and(ImpactLevel::Low->requiresConfirmation())->toBeFalse()
        ->and(ImpactLevel::Medium->requiresConfirmation())->toBeTrue()
        ->and(ImpactLevel::High->requiresConfirmation())->toBeTrue()
        ->and(ImpactLevel::Critical->requiresConfirmation())->toBeTrue();
});

test('ImpactLevel fromAffectedUsers returns None for zero', function (): void {
    expect(ImpactLevel::fromAffectedUsers(0))->toBe(ImpactLevel::None);
});

test('ImpactLevel fromAffectedUsers calculates by absolute count', function (): void {
    expect(ImpactLevel::fromAffectedUsers(1))->toBe(ImpactLevel::Low)
        ->and(ImpactLevel::fromAffectedUsers(10))->toBe(ImpactLevel::Medium)
        ->and(ImpactLevel::fromAffectedUsers(100))->toBe(ImpactLevel::High)
        ->and(ImpactLevel::fromAffectedUsers(1000))->toBe(ImpactLevel::Critical);
});

test('ImpactLevel fromAffectedUsers calculates by percentage', function (): void {
    // 5% of 100 users = Low
    expect(ImpactLevel::fromAffectedUsers(5, 100))->toBe(ImpactLevel::Low);
    // 25% of 100 users = Medium
    expect(ImpactLevel::fromAffectedUsers(25, 100))->toBe(ImpactLevel::Medium);
    // 50% of 100 users = High
    expect(ImpactLevel::fromAffectedUsers(50, 100))->toBe(ImpactLevel::High);
    // 75% of 100 users = Critical
    expect(ImpactLevel::fromAffectedUsers(75, 100))->toBe(ImpactLevel::Critical);
});

test('ImpactLevel fromAffectedUsers returns None for small percentage', function (): void {
    // 2% of 100 = None
    expect(ImpactLevel::fromAffectedUsers(2, 100))->toBe(ImpactLevel::None);
});

// PermissionScope tests
test('PermissionScope has all cases', function (): void {
    expect(PermissionScope::cases())->toHaveCount(6);
});

test('PermissionScope label returns correct values', function (): void {
    expect(PermissionScope::Global->label())->toBe('Global')
        ->and(PermissionScope::Team->label())->toBe('Team')
        ->and(PermissionScope::Tenant->label())->toBe('Tenant')
        ->and(PermissionScope::Resource->label())->toBe('Resource')
        ->and(PermissionScope::Temporal->label())->toBe('Temporal')
        ->and(PermissionScope::Owner->label())->toBe('Owner');
});

test('PermissionScope description returns non-empty', function (): void {
    foreach (PermissionScope::cases() as $case) {
        expect($case->description())->toBeString()->not->toBeEmpty();
    }
});

test('PermissionScope icon returns heroicons', function (): void {
    foreach (PermissionScope::cases() as $case) {
        expect($case->icon())->toStartWith('heroicon-');
    }
});

test('PermissionScope color returns expected values', function (): void {
    expect(PermissionScope::Global->color())->toBe('primary')
        ->and(PermissionScope::Team->color())->toBe('success')
        ->and(PermissionScope::Tenant->color())->toBe('info')
        ->and(PermissionScope::Resource->color())->toBe('warning')
        ->and(PermissionScope::Temporal->color())->toBe('danger')
        ->and(PermissionScope::Owner->color())->toBe('gray');
});

test('PermissionScope requiresScopeId is false only for Global and Owner', function (): void {
    expect(PermissionScope::Global->requiresScopeId())->toBeFalse()
        ->and(PermissionScope::Owner->requiresScopeId())->toBeFalse()
        ->and(PermissionScope::Team->requiresScopeId())->toBeTrue()
        ->and(PermissionScope::Tenant->requiresScopeId())->toBeTrue()
        ->and(PermissionScope::Resource->requiresScopeId())->toBeTrue()
        ->and(PermissionScope::Temporal->requiresScopeId())->toBeTrue();
});

test('PermissionScope supportsExpiration is only true for Temporal', function (): void {
    foreach (PermissionScope::cases() as $case) {
        expect($case->supportsExpiration())->toBe($case === PermissionScope::Temporal);
    }
});

// PolicyCombiningAlgorithm tests
test('PolicyCombiningAlgorithm has all cases', function (): void {
    expect(PolicyCombiningAlgorithm::cases())->toHaveCount(6);
});

test('PolicyCombiningAlgorithm label returns correct values', function (): void {
    expect(PolicyCombiningAlgorithm::DenyOverrides->label())->toBe('Deny Overrides')
        ->and(PolicyCombiningAlgorithm::PermitOverrides->label())->toBe('Permit Overrides')
        ->and(PolicyCombiningAlgorithm::FirstApplicable->label())->toBe('First Applicable')
        ->and(PolicyCombiningAlgorithm::OnlyOneApplicable->label())->toBe('Only One Applicable');
});

test('PolicyCombiningAlgorithm description returns non-empty', function (): void {
    foreach (PolicyCombiningAlgorithm::cases() as $case) {
        expect($case->description())->toBeString()->not->toBeEmpty();
    }
});

test('PolicyCombiningAlgorithm combine with DenyOverrides', function (): void {
    $algo = PolicyCombiningAlgorithm::DenyOverrides;

    // Any deny = deny
    expect($algo->combine([PolicyDecision::Permit, PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);

    // All permits = permit
    expect($algo->combine([PolicyDecision::Permit, PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);

    // All not applicable = not applicable
    expect($algo->combine([PolicyDecision::NotApplicable, PolicyDecision::NotApplicable]))->toBe(PolicyDecision::NotApplicable);

    // Empty = default (Deny for DenyOverrides)
    expect($algo->combine([]))->toBe(PolicyDecision::Deny);
});

test('PolicyCombiningAlgorithm combine with PermitOverrides', function (): void {
    $algo = PolicyCombiningAlgorithm::PermitOverrides;

    // Any permit = permit
    expect($algo->combine([PolicyDecision::Deny, PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);

    // All denies = deny
    expect($algo->combine([PolicyDecision::Deny, PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);

    // Empty = default (Permit for PermitOverrides)
    expect($algo->combine([]))->toBe(PolicyDecision::Permit);
});

test('PolicyCombiningAlgorithm combine with FirstApplicable', function (): void {
    $algo = PolicyCombiningAlgorithm::FirstApplicable;

    // Returns first non-NotApplicable
    expect($algo->combine([PolicyDecision::NotApplicable, PolicyDecision::Permit, PolicyDecision::Deny]))->toBe(PolicyDecision::Permit);
    expect($algo->combine([PolicyDecision::NotApplicable, PolicyDecision::Deny, PolicyDecision::Permit]))->toBe(PolicyDecision::Deny);

    // All not applicable = not applicable
    expect($algo->combine([PolicyDecision::NotApplicable, PolicyDecision::NotApplicable]))->toBe(PolicyDecision::NotApplicable);

    // Empty = not applicable
    expect($algo->combine([]))->toBe(PolicyDecision::NotApplicable);
});

test('PolicyCombiningAlgorithm combine with OnlyOneApplicable', function (): void {
    $algo = PolicyCombiningAlgorithm::OnlyOneApplicable;

    // One applicable = return it
    expect($algo->combine([PolicyDecision::NotApplicable, PolicyDecision::Permit, PolicyDecision::NotApplicable]))->toBe(PolicyDecision::Permit);

    // More than one applicable = indeterminate
    expect($algo->combine([PolicyDecision::Permit, PolicyDecision::Deny]))->toBe(PolicyDecision::Indeterminate);

    // Empty = default (Deny for OnlyOneApplicable)
    expect($algo->combine([]))->toBe(PolicyDecision::Deny);
});

// PolicyDecision tests
test('PolicyDecision has all cases', function (): void {
    expect(PolicyDecision::cases())->toHaveCount(4);
});

test('PolicyDecision label returns correct values', function (): void {
    expect(PolicyDecision::Permit->label())->toBe('Permit')
        ->and(PolicyDecision::Deny->label())->toBe('Deny')
        ->and(PolicyDecision::NotApplicable->label())->toBe('Not Applicable')
        ->and(PolicyDecision::Indeterminate->label())->toBe('Indeterminate');
});

test('PolicyDecision isApplicable returns correct values', function (): void {
    expect(PolicyDecision::Permit->isConclusive())->toBeTrue()
        ->and(PolicyDecision::Deny->isConclusive())->toBeTrue()
        ->and(PolicyDecision::NotApplicable->isConclusive())->toBeFalse()
        ->and(PolicyDecision::Indeterminate->isConclusive())->toBeFalse();
});

test('PolicyDecision color returns valid values', function (): void {
    expect(PolicyDecision::Permit->color())->toBe('success')
        ->and(PolicyDecision::Deny->color())->toBe('danger')
        ->and(PolicyDecision::NotApplicable->color())->toBe('gray')
        ->and(PolicyDecision::Indeterminate->color())->toBe('warning');
});

test('PolicyDecision icon returns heroicons', function (): void {
    foreach (PolicyDecision::cases() as $case) {
        expect($case->icon())->toStartWith('heroicon-');
    }
});

// PolicyEffect tests
test('PolicyEffect has all cases', function (): void {
    expect(PolicyEffect::cases())->toHaveCount(2);
});

test('PolicyEffect label returns correct values', function (): void {
    expect(PolicyEffect::Allow->label())->toBe('Allow')
        ->and(PolicyEffect::Deny->label())->toBe('Deny');
});

test('PolicyEffect opposite returns inverse', function (): void {
    expect(PolicyEffect::Allow->isPermissive())->toBeTrue()
        ->and(PolicyEffect::Deny->isRestrictive())->toBeTrue();
});

test('PolicyEffect isPermissive and isRestrictive work correctly', function (): void {
    expect(PolicyEffect::Allow->isPermissive())->toBeTrue()
        ->and(PolicyEffect::Allow->isRestrictive())->toBeFalse()
        ->and(PolicyEffect::Deny->isRestrictive())->toBeTrue()
        ->and(PolicyEffect::Deny->isPermissive())->toBeFalse();
});

test('PolicyEffect color returns valid values', function (): void {
    expect(PolicyEffect::Allow->color())->toBe('success')
        ->and(PolicyEffect::Deny->color())->toBe('danger');
});

test('PolicyEffect icon returns heroicons', function (): void {
    foreach (PolicyEffect::cases() as $case) {
        expect($case->icon())->toStartWith('heroicon-');
    }
});
