<?php

declare(strict_types=1);

use AIArmada\Chip\Data\BankAccountData;
use AIArmada\Chip\Data\SendInstructionData;
use AIArmada\Chip\Features\Payouts\Actions\DeleteBankAccount;
use AIArmada\Chip\Features\Payouts\Actions\ResendPayoutWebhook;
use AIArmada\Chip\Features\Payouts\Models\BankAccount;
use AIArmada\Chip\Features\Payouts\Models\SendInstruction;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Filament\Pages\ReadOnlyListRecords;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentChip\Actions\PurchaseExporter;
use AIArmada\FilamentChip\Actions\SendInstructionExporter;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\CreateBankAccount;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\ListBankAccounts;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\ViewBankAccount;
use AIArmada\FilamentChip\Resources\ClientResource\Pages\ListClients;
use AIArmada\FilamentChip\Resources\ClientResource\Pages\ViewClient;
use AIArmada\FilamentChip\Resources\CompanyStatementResource\Pages\ListCompanyStatements;
use AIArmada\FilamentChip\Resources\PaymentResource;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ListPayments;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ViewPayment;
use AIArmada\FilamentChip\Resources\PurchaseResource\Pages\ListPurchases;
use AIArmada\FilamentChip\Resources\PurchaseResource\Pages\ViewPurchase;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\CreateSendInstruction;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\ListSendInstructions;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\ViewSendInstruction;
use Filament\Actions\Exports\Models\Export;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    filament()->setCurrentPanel('test');

    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });
});

it('covers exporters notification bodies and column definitions', function (): void {
    $export = new class extends Export
    {
        public function getFailedRowsCount(): int
        {
            return 2;
        }
    };

    $export->successful_rows = 10;

    expect(PurchaseExporter::getColumns())->toBeArray();
    expect(SendInstructionExporter::getColumns())->toBeArray();

    expect(PurchaseExporter::getCompletedNotificationBody($export))->toContain('10')->toContain('2');
    expect(SendInstructionExporter::getCompletedNotificationBody($export))->toContain('10')->toContain('2');
});

it('covers resource pages and read-only list base class', function (): void {
    config()->set('chip.owner.enabled', false);

    app()->bind(OwnerResolverInterface::class, fn () => new class implements OwnerResolverInterface
    {
        public function resolve(): ?RecordReference
        {
            return null;
        }
    });

    $readOnly = new class extends ReadOnlyListRecords
    {
        protected static string $resource = PaymentResource::class;
    };

    $m = (new ReflectionClass(ReadOnlyListRecords::class))->getMethod('getHeaderActions');
    expect($m->invoke($readOnly))->toBeArray()->toBeEmpty();

    expect((new ListPurchases)->getTitle())->toBeString();
    expect((new ListPayments)->getTitle())->toBe('Payments');
    expect((new ListClients)->getTitle())->toBe('Clients');

    expect((new ViewClient)->getTitle())->toBe('Client Details');
    expect((new ViewPayment)->getTitle())->toBe('Payment Details');

    expect((new ListCompanyStatements)->getTitle())->toBeString();

    $purchaseRecord = new class extends Model
    {
        public $reference = 'REF-123';

        public function getKey(): string
        {
            return 'key';
        }
    };

    $viewPurchase = new ViewPurchase;

    $recordProp = (new ReflectionClass(ViewRecord::class))->getProperty('record');
    $recordProp->setValue($viewPurchase, $purchaseRecord);

    expect($viewPurchase->getTitle())->toContain('REF-123');

    Schema::dropIfExists('chip_send_instructions');
    Schema::dropIfExists('chip_bank_accounts');

    $root = dirname(__DIR__, 4);
    (require $root . '/packages/chip/database/migrations/2000_04_01_000004_create_chip_bank_accounts_table.php')->up();
    (require $root . '/packages/chip/database/migrations/2000_04_01_000006_create_chip_send_instructions_table.php')->up();

    $remoteAccount = new BankAccountData(
        id: 10,
        status: 'verified',
        account_number: '123',
        bank_code: 'MBBEMYKL',
        group_id: null,
        name: 'Acct',
        reference: 'ref',
        created_at: '2026-01-01T00:00:00Z',
        is_debiting_account: false,
        is_crediting_account: true,
        updated_at: '2026-01-01T00:00:00Z',
        deleted_at: null,
        rejection_reason: null,
    );

    $remoteInstruction = new SendInstructionData(
        id: 20,
        bank_account_id: 10,
        amount: '1.23',
        state: 'completed',
        email: 'to@example.com',
        description: 'Test',
        reference: 'ref',
        send_recipient_receipt: false,
        receipt_url: null,
        slug: null,
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
    );

    $chipSendService = Mockery::mock(ChipSendService::class);
    $chipSendService->shouldReceive('createBankAccount')->once()->with('MBBEMYKL', '123', 'Acct', 'ref')->andReturn($remoteAccount);
    $chipSendService->shouldReceive('createSendInstruction')->once()->with(123, 10, 'Test', 'ref', 'to@example.com')->andReturn($remoteInstruction);
    $chipSendService->shouldReceive('resendSendInstructionWebhook')->once()->with(20)->andReturn([]);
    app()->instance(ChipSendService::class, $chipSendService);

    $createBank = new CreateBankAccount;
    $create = (new ReflectionClass($createBank))->getMethod('handleRecordCreation');
    $bankAccount = $create->invoke($createBank, [
        'bank_code' => 'MBBEMYKL',
        'account_number' => '123',
        'name' => 'Acct',
        'reference' => 'ref',
    ]);

    expect($bankAccount)->toBeInstanceOf(BankAccount::class)
        ->and($bankAccount->id)->toBe(10);

    $createPayout = new CreateSendInstruction;
    $create = (new ReflectionClass($createPayout))->getMethod('handleRecordCreation');
    $sendInstruction = $create->invoke($createPayout, [
        'amount' => '1.23',
        'bank_account_id' => '10',
        'description' => 'Test',
        'reference' => 'ref',
        'email' => 'to@example.com',
    ]);

    expect($sendInstruction)->toBeInstanceOf(SendInstruction::class)
        ->and($sendInstruction->id)->toBe(20);

    expect(fn () => $create->invoke($createPayout, [
        'amount' => '1.23',
        'bank_account_id' => '999',
        'description' => 'Test',
        'reference' => 'ref',
        'email' => 'to@example.com',
    ]))->toThrow(Halt::class);

    $viewBank = new ViewBankAccount;
    $recordProp->setValue($viewBank, $bankAccount);

    $m = (new ReflectionClass($viewBank))->getMethod('getHeaderActions');

    foreach ($m->invoke($viewBank) as $action) {
        $action->isVisible();
    }

    $viewSend = new ViewSendInstruction;
    $recordProp->setValue($viewSend, $sendInstruction);

    $m = (new ReflectionClass($viewSend))->getMethod('getHeaderActions');

    foreach ($m->invoke($viewSend) as $action) {
        $action->isVisible();

        if ($action->getName() === 'resend_webhook') {
            $fn = $action->getActionFunction();

            if ($fn instanceof Closure) {
                $fn();
            }
        }
    }

    // Cover list record pages' header actions.
    $listBank = new ListBankAccounts;
    $m = (new ReflectionClass($listBank))->getMethod('getHeaderActions');
    expect($m->invoke($listBank))->toBeArray();

    $listSend = new ListSendInstructions;
    $m = (new ReflectionClass($listSend))->getMethod('getHeaderActions');
    expect($m->invoke($listSend))->toBeArray();
});

it('enforces owner scoping in mutation action record resolution', function (): void {
    config()->set('chip.owner.enabled', true);

    Schema::dropIfExists('chip_bank_accounts');
    Schema::create('chip_bank_accounts', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->nullableMorphs('owner');
        $table->string('status')->nullable();
        $table->string('name')->nullable();
        $table->string('account_number')->nullable();
        $table->string('bank_code')->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('chip_send_instructions');
    Schema::create('chip_send_instructions', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->nullableMorphs('owner');
        $table->integer('bank_account_id')->nullable();
        $table->string('amount')->default('0');
        $table->string('email')->nullable();
        $table->string('description')->nullable();
        $table->string('reference')->nullable();
        $table->string('state')->nullable();
        $table->timestamps();
    });

    $ownerModel = new class extends Model
    {
        protected $table = 'users';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerA = $ownerModel::query()->create([
        'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = $ownerModel::query()->create([
        'id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });

    $bankAccountForA = OwnerContext::withOwner($ownerA, function () use ($ownerA): BankAccount {
        return BankAccount::query()->create([
            'id' => 101,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'status' => 'verified',
            'name' => 'A Account',
            'account_number' => '111',
            'bank_code' => 'MBBEMYKL',
        ]);
    });

    $bankAccountForB = OwnerContext::withOwner($ownerB, function () use ($ownerB): BankAccount {
        return BankAccount::query()->create([
            'id' => 102,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'status' => 'verified',
            'name' => 'B Account',
            'account_number' => '222',
            'bank_code' => 'MBBEMYKL',
        ]);
    });

    $instructionForA = OwnerContext::withOwner($ownerA, function () use ($ownerA): SendInstruction {
        return SendInstruction::query()->create([
            'id' => 201,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'bank_account_id' => 101,
            'amount' => '10.00',
            'email' => 'a@example.com',
            'description' => 'A payout',
            'reference' => 'A-REF',
            'state' => 'completed',
        ]);
    });

    $instructionForB = OwnerContext::withOwner($ownerB, function () use ($ownerB): SendInstruction {
        return SendInstruction::query()->create([
            'id' => 202,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'bank_account_id' => 102,
            'amount' => '20.00',
            'email' => 'b@example.com',
            'description' => 'B payout',
            'reference' => 'B-REF',
            'state' => 'completed',
        ]);
    });

    $send = Mockery::mock(ChipSendService::class);
    $send->shouldNotReceive('deleteBankAccount');
    $send->shouldNotReceive('resendSendInstructionWebhook');

    expect(fn () => (new DeleteBankAccount($send))->handle((int) $bankAccountForB->getKey()))
        ->toThrow(AuthorizationException::class);
    expect(fn () => (new ResendPayoutWebhook($send))->handle((int) $instructionForB->getKey()))
        ->toThrow(AuthorizationException::class);
});
