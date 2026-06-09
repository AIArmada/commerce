<?php

declare(strict_types=1);

namespace AIArmada\Chip\Tests\Feature;

use AIArmada\Chip\Actions\HandleSendInstructionWebhookAction;
use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Tests\TestCase;
use AIArmada\Chip\Webhooks\Handlers\SendCompletedHandler;
use AIArmada\Chip\Webhooks\Handlers\SendRejectedHandler;

uses(TestCase::class);

function createInstruction(array $overrides = []): SendInstruction
{
    static $seq = 100;

    return SendInstruction::create(array_merge([
        'id' => $seq++,
        'bank_account_id' => 1,
        'state' => 'received',
        'amount' => '500.00',
        'email' => 'test@example.com',
        'description' => 'Test payout',
        'reference' => 'REF-' . $seq,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

describe('SendCompletedHandler', function (): void {
    it('handles send instruction completed webhook', function (): void {
        $instruction = createInstruction();

        $payload = EnrichedWebhookPayload::fromPayload('send_instruction.completed', [
            'id' => (string) $instruction->id,
            'status' => 'completed',
        ]);

        $action = app(HandleSendInstructionWebhookAction::class);
        $handler = new SendCompletedHandler($action);
        $result = $handler->handle($payload);

        expect($result->isSuccess())->toBeTrue();
        expect($result->isHandled())->toBeTrue();

        $instruction->refresh();
        expect($instruction->state)->toBe('completed');
    });

    it('skips when no send instruction ID in payload', function (): void {
        $payload = EnrichedWebhookPayload::fromPayload('send_instruction.completed', []);

        $action = app(HandleSendInstructionWebhookAction::class);
        $handler = new SendCompletedHandler($action);
        $result = $handler->handle($payload);

        expect($result->isSkipped())->toBeTrue();
    });

    it('skips when send instruction not found locally', function (): void {
        $payload = EnrichedWebhookPayload::fromPayload('send_instruction.completed', [
            'id' => '999999',
        ]);

        $action = app(HandleSendInstructionWebhookAction::class);
        $handler = new SendCompletedHandler($action);
        $result = $handler->handle($payload);

        expect($result->isSkipped())->toBeTrue();
    });
});

describe('SendRejectedHandler', function (): void {
    it('handles send instruction rejected webhook', function (): void {
        $instruction = createInstruction();

        $payload = EnrichedWebhookPayload::fromPayload('send_instruction.rejected', [
            'id' => (string) $instruction->id,
            'status' => 'rejected',
            'error' => ['message' => 'Insufficient funds'],
        ]);

        $action = app(HandleSendInstructionWebhookAction::class);
        $handler = new SendRejectedHandler($action);
        $result = $handler->handle($payload);

        expect($result->isSuccess())->toBeTrue();
        expect($result->isHandled())->toBeTrue();

        $instruction->refresh();
        expect($instruction->state)->toBe('rejected');
    });

    it('uses default failure reason when none provided', function (): void {
        $instruction = createInstruction(['reference' => 'REF-456']);

        $payload = EnrichedWebhookPayload::fromPayload('send_instruction.rejected', [
            'id' => (string) $instruction->id,
            'status' => 'rejected',
        ]);

        $action = app(HandleSendInstructionWebhookAction::class);
        $handler = new SendRejectedHandler($action);
        $result = $handler->handle($payload);

        expect($result->isSuccess())->toBeTrue();
    });
});
