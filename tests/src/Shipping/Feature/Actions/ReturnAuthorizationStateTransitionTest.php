<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature\Actions;

use AIArmada\Shipping\Actions\ApproveReturnAuthorization;
use AIArmada\Shipping\Actions\RejectReturnAuthorization;
use AIArmada\Shipping\Models\ReturnAuthorization;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Spatie\ModelStates\Events\StateChanged;

function pendingReturnAuthorization(): ReturnAuthorization
{
    return ReturnAuthorization::query()->create([
        'status' => 'pending',
        'type' => 'return',
        'reason' => 'defective',
    ]);
}

describe('Return authorization state transitions', function (): void {
    it('approves through the state machine with audit fields', function (): void {
        Event::fake([StateChanged::class]);

        $approved = ApproveReturnAuthorization::run(pendingReturnAuthorization(), 'Looks valid', 'actor-1');

        expect($approved->isApproved())->toBeTrue()
            ->and($approved->approved_at)->not->toBeNull()
            ->and($approved->approved_by)->toBe('actor-1')
            ->and($approved->metadata['approval_notes'])->toBe('Looks valid');

        Event::assertDispatched(StateChanged::class);
    });

    it('rejects through the state machine with audit fields', function (): void {
        Event::fake([StateChanged::class]);

        $rejected = RejectReturnAuthorization::run(pendingReturnAuthorization(), 'Outside window', 'actor-2');

        expect($rejected->isRejected())->toBeTrue()
            ->and($rejected->rejected_at)->not->toBeNull()
            ->and($rejected->rejected_by)->toBe('actor-2')
            ->and($rejected->metadata['rejection_reason'])->toBe('Outside window');

        Event::assertDispatched(StateChanged::class);
    });

    it('refuses to approve or reject non-pending authorizations', function (): void {
        $approved = ApproveReturnAuthorization::run(pendingReturnAuthorization());

        expect(fn () => ApproveReturnAuthorization::run($approved))->toThrow(RuntimeException::class)
            ->and(fn () => RejectReturnAuthorization::run($approved, 'Too late'))->toThrow(RuntimeException::class);
    });
});
