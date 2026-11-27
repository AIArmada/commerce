<?php

declare(strict_types=1);

use AIArmada\Chip\Features\Payouts\Actions\CreateBankAccount;
use AIArmada\Chip\Features\Payouts\Actions\CreateSendInstruction;
use AIArmada\Chip\Data\BankAccountData;
use AIArmada\Chip\Data\SendInstructionData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Features\Payouts\Models\BankAccount;
use AIArmada\Chip\Services\ChipSendService;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config(['chip.owner.enabled' => false, 'chip.database.table_prefix' => 'chip_']);

    Schema::dropIfExists('chip_send_instructions');
    Schema::dropIfExists('chip_bank_accounts');

    $root = dirname(__DIR__, 4);
    (require $root . '/packages/chip/database/migrations/2000_04_01_000004_create_chip_bank_accounts_table.php')->up();
    (require $root . '/packages/chip/database/migrations/2000_04_01_000006_create_chip_send_instructions_table.php')->up();
});

it('persists the complete remote bank account and creates an exact-cent payout', function (): void {
    $remoteAccount = new BankAccountData(
        id: 84,
        status: 'verified',
        account_number: '123456789',
        bank_code: 'MBBEMYKL',
        group_id: null,
        name: 'Test Recipient',
        reference: 'recipient-84',
        created_at: '2026-01-01T00:00:00Z',
        is_debiting_account: false,
        is_crediting_account: true,
        updated_at: '2026-01-01T00:00:00Z',
        deleted_at: null,
        rejection_reason: null,
    );

    $remoteInstruction = new SendInstructionData(
        id: 50,
        bank_account_id: 84,
        amount: '10.29',
        state: 'received',
        email: 'test@example.com',
        description: 'Payment',
        reference: 'payout-50',
        send_recipient_receipt: false,
        receipt_url: null,
        slug: null,
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
    );

    $service = Mockery::mock(ChipSendService::class);
    $service->shouldReceive('createBankAccount')->once()
        ->with('MBBEMYKL', '123456789', 'Test Recipient', 'recipient-84')
        ->andReturn($remoteAccount);
    $service->shouldReceive('createSendInstruction')->once()
        ->with(1029, 84, 'Payment', 'payout-50', 'test@example.com')
        ->andReturn($remoteInstruction);

    $account = (new CreateBankAccount($service))->handle('MBBEMYKL', '123456789', 'Test Recipient', 'recipient-84');
    $instruction = (new CreateSendInstruction($service))->handle('10.29', 84, 'Payment', 'payout-50', 'test@example.com');

    expect($account->fresh()->is_crediting_account)->toBeTrue()
        ->and($instruction->fresh()->amount)->toBe('10.29')
        ->and($instruction->fresh()->bank_account_id)->toBe(84);
});

it('rejects inactive or non-crediting recipient accounts before contacting CHIP', function (): void {
    $account = BankAccount::query()->create([
        'id' => 84,
        'status' => 'verified',
        'account_number' => '123456789',
        'bank_code' => 'MBBEMYKL',
        'name' => 'Test Recipient',
        'reference' => 'recipient-84',
        'is_crediting_account' => false,
    ]);

    $service = Mockery::mock(ChipSendService::class);
    $service->shouldNotReceive('createSendInstruction');

    expect(fn () => (new CreateSendInstruction($service))->handle('10.29', $account->id, 'Payment', 'payout-50', 'test@example.com'))
        ->toThrow(ChipValidationException::class);
});

it('rejects invalid and overflowing payout amounts before contacting CHIP', function (string $amount): void {
    $service = Mockery::mock(ChipSendService::class);
    $service->shouldNotReceive('createSendInstruction');

    expect(fn () => (new CreateSendInstruction($service))->handle($amount, 84, 'Payment', 'payout-50', 'test@example.com'))
        ->toThrow(ChipValidationException::class);
})->with(['0', '-1', '1e3', '10.291', '99999999999999999999999.99']);
