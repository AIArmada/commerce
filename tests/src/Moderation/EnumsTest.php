<?php

declare(strict_types=1);

use AIArmada\Moderation\Enums\BlockReason;
use AIArmada\Moderation\Enums\BlockStatus;
use AIArmada\Moderation\Enums\ModerationActionType;

describe('BlockReason', function (): void {
    it('has all required cases', function (): void {
        expect(BlockReason::Spam->value)->toBe('spam');
        expect(BlockReason::AbusiveContent->value)->toBe('abusive_content');
        expect(BlockReason::Harassment->value)->toBe('harassment');
        expect(BlockReason::Impersonation->value)->toBe('impersonation');
        expect(BlockReason::CopyrightViolation->value)->toBe('copyright_violation');
        expect(BlockReason::PolicyViolation->value)->toBe('policy_violation');
        expect(BlockReason::Other->value)->toBe('other');
    });

    it('provides readable labels for all cases', function (): void {
        foreach (BlockReason::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('BlockStatus', function (): void {
    it('has active, expired, and lifted cases', function (): void {
        expect(BlockStatus::Active->value)->toBe('active');
        expect(BlockStatus::Expired->value)->toBe('expired');
        expect(BlockStatus::Lifted->value)->toBe('lifted');
    });

    it('provides readable labels', function (): void {
        foreach (BlockStatus::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

describe('ModerationActionType', function (): void {
    it('has all required cases', function (): void {
        expect(ModerationActionType::Warn->value)->toBe('warn');
        expect(ModerationActionType::Mute->value)->toBe('mute');
        expect(ModerationActionType::Suspend->value)->toBe('suspend');
        expect(ModerationActionType::Ban->value)->toBe('ban');
        expect(ModerationActionType::LiftBlock->value)->toBe('lift_block');
        expect(ModerationActionType::Approve->value)->toBe('approve');
        expect(ModerationActionType::Reject->value)->toBe('reject');
    });

    it('provides readable labels for all cases', function (): void {
        foreach (ModerationActionType::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    });
});

test('all moderation enums are string-backed', function (): void {
    $enums = [
        BlockReason::class,
        BlockStatus::class,
        ModerationActionType::class,
    ];

    foreach ($enums as $enum) {
        $cases = $enum::cases();
        expect($cases)->not->toBeEmpty();
        foreach ($cases as $case) {
            expect($case->value)->toBeString();
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    }
});
