<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Http\Controllers\ProgramMembershipController;
use AIArmada\Affiliates\Models\AffiliateProgram;

function joinableProgram(array $attributes = []): AffiliateProgram
{
    return AffiliateProgram::create(array_merge([
        'name' => 'Join Program',
        'slug' => 'join-program-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => CommissionType::Percentage,
    ], $attributes));
}

describe('ProgramMembershipController', function (): void {
    test('join enrolls and is idempotent', function (): void {
        $affiliate = createTestAffiliate();
        $program = joinableProgram();
        $controller = app(ProgramMembershipController::class);

        $first = $controller->join($affiliate->code, (string) $program->getKey());
        $second = $controller->join($affiliate->code, (string) $program->getKey());

        expect($first->getStatusCode())->toBe(201)
            ->and($second->getStatusCode())->toBe(200)
            ->and(json_decode($second->getContent(), true)['status'])->toBe('approved');
    });

    test('join respects approval and eligibility', function (): void {
        $affiliate = createTestAffiliate();
        $approval = joinableProgram(['slug' => 'join-approval-' . uniqid(), 'requires_approval' => true]);
        $closed = joinableProgram(['slug' => 'join-closed-' . uniqid(), 'status' => ProgramStatus::Archived]);
        $controller = app(ProgramMembershipController::class);

        $pending = $controller->join($affiliate->code, (string) $approval->getKey());
        $rejected = $controller->join($affiliate->code, (string) $closed->getKey());

        expect($pending->getStatusCode())->toBe(201)
            ->and(json_decode($pending->getContent(), true)['status'])->toBe('pending')
            ->and($rejected->getStatusCode())->toBe(422);
    });

    test('membership reports status and unknowns', function (): void {
        $affiliate = createTestAffiliate();
        $program = joinableProgram();
        $controller = app(ProgramMembershipController::class);

        $before = $controller->membership($affiliate->code, (string) $program->getKey());
        $controller->join($affiliate->code, (string) $program->getKey());
        $after = $controller->membership($affiliate->code, (string) $program->getKey());
        $missing = $controller->membership('NOPE', (string) $program->getKey());

        expect(json_decode($before->getContent(), true))->toBe(['member' => false, 'status' => null])
            ->and(json_decode($after->getContent(), true)['member'])->toBeTrue()
            ->and($missing->getStatusCode())->toBe(404);
    });
});
