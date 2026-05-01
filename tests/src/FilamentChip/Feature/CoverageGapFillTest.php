<?php

declare(strict_types=1);

use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentChip\Actions\PurchaseExporter;
use AIArmada\FilamentChip\Actions\SendInstructionExporter;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\CreateBankAccount;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\ListBankAccounts;
use AIArmada\FilamentChip\Resources\BankAccountResource\Pages\ViewBankAccount;
use AIArmada\FilamentChip\Resources\BankAccountResource\Tables\BankAccountTable;
use AIArmada\FilamentChip\Resources\ClientResource\Pages\ListClients;
use AIArmada\FilamentChip\Resources\ClientResource\Pages\ViewClient;
use AIArmada\FilamentChip\Resources\CompanyStatementResource\Pages\ListCompanyStatements;
use AIArmada\FilamentChip\Resources\Pages\ReadOnlyListRecords;
use AIArmada\FilamentChip\Resources\PaymentResource;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ListPayments;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ViewPayment;
use AIArmada\FilamentChip\Resources\PurchaseResource\Pages\ListPurchases;
use AIArmada\FilamentChip\Resources\PurchaseResource\Pages\ViewPurchase;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\CreateSendInstruction;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\ListSendInstructions;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Pages\ViewSendInstruction;
use AIArmada\FilamentChip\Resources\SendInstructionResource\Tables\SendInstructionTable;
use Filament\Actions\Exports\Models\Export;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
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
        public function resolve(): ?Model
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

    // Cover create record mutators + view record actions.
    Schema::dropIfExists('chip_bank_accounts');
    Schema::create('chip_bank_accounts', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->nullableMorphs('owner');
        $table->string('status')->nullable();
        $table->string('name')->nullable();
        $table->string('account_number')->nullable();
        $table->string('bank_code')->nullable();
        $table->string('reference')->nullable();
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

    /** @var BankAccount $bankAccount */
    $bankAccount = OwnerContext::withOwner(null, function (): BankAccount {
        return BankAccount::query()->create([
            'id' => 10,
            'status' => 'approved',
            'name' => 'Acct',
            'account_number' => '123',
            'bank_code' => 'MBBEMYKL',
        ]);
    });

    /** @var SendInstruction $sendInstruction */
    $sendInstruction = OwnerContext::withOwner(null, function (): SendInstruction {
        return SendInstruction::query()->create([
            'id' => 20,
            'bank_account_id' => 10,
            'amount' => '1.00',
            'reference' => 'SI',
            'state' => 'completed',
        ]);
    });

    app()->instance(ChipSendService::class, new class($bankAccount, $sendInstruction)
    {
        public function __construct(private BankAccount $bankAccount, private SendInstruction $sendInstruction) {}

        public function createBankAccount(string $bankCode, string $accountNumber, string $accountHolderName, ?string $reference = null): BankAccount
        {
            return $this->bankAccount;
        }

        public function createSendInstruction(int $amountInCents, string $currency, string $recipientBankAccountId, string $description, string $reference, string $email): SendInstruction
        {
            return $this->sendInstruction;
        }

        public function updateBankAccount(string $id, array $data): void {}

        public function deleteBankAccount(string $id): void {}

        public function resendSendInstructionWebhook(string $id): void {}

        public function cancelSendInstruction(string $id): void {}
    });

    $createBank = new CreateBankAccount;

    $mutate = (new ReflectionClass($createBank))->getMethod('mutateFormDataBeforeCreate');

    $data = $mutate->invoke($createBank, [
        'bank_code' => 'MBBEMYKL',
        'account_number' => '123',
        'name' => 'Acct',
    ]);

    expect($data)->toHaveKey('id')->toHaveKey('status');

    $createPayout = new CreateSendInstruction;

    $mutate = (new ReflectionClass($createPayout))->getMethod('mutateFormDataBeforeCreate');

    $data = $mutate->invoke($createPayout, [
        'amount' => 1.23,
        'bank_account_id' => '10',
        'description' => 'Test',
        'reference' => 'ref',
        'email' => 'to@example.com',
    ]);

    expect($data)->toHaveKey('id')->toHaveKey('state');

    OwnerContext::withOwner(null, function (): void {
        BankAccount::query()->create([
            'id' => 11,
            'status' => 'pending',
            'name' => 'Pending Acct',
            'account_number' => '124',
            'bank_code' => 'MBBEMYKL',
        ]);
    });

    expect(fn () => $mutate->invoke($createPayout, [
        'amount' => 1.23,
        'bank_account_id' => '11',
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

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $bankAccountForA = OwnerContext::withOwner($ownerA, function () use ($ownerA): BankAccount {
        return BankAccount::query()->create([
            'id' => 101,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'status' => 'active',
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
            'status' => 'active',
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

    $resolveBankTable = (new ReflectionClass(BankAccountTable::class))->getMethod('resolveScopedBankAccount');
    $resolveSendTable = (new ReflectionClass(SendInstructionTable::class))->getMethod('resolveScopedSendInstruction');

    expect($resolveBankTable->invoke(null, $bankAccountForA)?->getKey())->toBe($bankAccountForA->getKey());
    expect($resolveBankTable->invoke(null, $bankAccountForB))->toBeNull();
    expect($resolveSendTable->invoke(null, $instructionForA)?->getKey())->toBe($instructionForA->getKey());
    expect($resolveSendTable->invoke(null, $instructionForB))->toBeNull();

    $viewBank = new ViewBankAccount;
    $resolveBankView = (new ReflectionClass($viewBank))->getMethod('resolveScopedBankAccount');
    expect($resolveBankView->invoke($viewBank, $bankAccountForA)?->getKey())->toBe($bankAccountForA->getKey());
    expect($resolveBankView->invoke($viewBank, $bankAccountForB))->toBeNull();

    $viewSend = new ViewSendInstruction;
    $resolveSendView = (new ReflectionClass($viewSend))->getMethod('resolveScopedSendInstruction');
    expect($resolveSendView->invoke($viewSend, $instructionForA)?->getKey())->toBe($instructionForA->getKey());
    expect($resolveSendView->invoke($viewSend, $instructionForB))->toBeNull();
});
