<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\SendInstructionState;

describe('SendInstructionState Enum', function (): void {
    it('correctly identifies which instructions can be deleted', function (): void {
        expect(SendInstructionState::RECEIVED->canBeDeleted())->toBeTrue()
            ->and(SendInstructionState::ENQUIRING->canBeDeleted())->toBeTrue()
            ->and(SendInstructionState::COMPLETED->canBeDeleted())->toBeFalse()
            ->and(SendInstructionState::REJECTED->canBeDeleted())->toBeFalse();
    });
});
