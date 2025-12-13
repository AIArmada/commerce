---
title: CHIP Send Admin Suite
---

# CHIP Send Admin Suite (Phase 5)

> **Document:** 06 of 09  
> **Package:** `aiarmada/filament-chip`  
> **Status:** Vision  
> **Priority:** P0 - Critical

---

## Overview

Build comprehensive Filament admin interface for **CHIP Send** (Payout/Disbursement) operations. The chip package has full CHIP Send API implementation but **zero Filament representation**.

---

## API Foundation (Already Implemented in chip)

```php
// Send Instructions (Payouts)
ChipSendService::createSendInstruction($amount, $currency, $bankAccountId, $description, $reference, $email)
ChipSendService::getSendInstruction($id)
ChipSendService::listSendInstructions($filters)
ChipSendService::cancelSendInstruction($id)
ChipSendService::deleteSendInstruction($id)
ChipSendService::resendSendInstructionWebhook($id)

// Bank Accounts
ChipSendService::createBankAccount($bankCode, $accountNumber, $holderName, $reference)
ChipSendService::getBankAccount($id)
ChipSendService::listBankAccounts($filters)
ChipSendService::updateBankAccount($id, $data)
ChipSendService::deleteBankAccount($id)
ChipSendService::resendBankAccountWebhook($id)

// Groups
ChipSendService::createGroup($data)
ChipSendService::getGroup($id)
ChipSendService::listGroups($filters)
ChipSendService::updateGroup($id, $data)
ChipSendService::deleteGroup($id)
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  CHIP SEND ADMIN SUITE                       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │              PayoutDashboardPage                       │  │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────────┐  │  │
│  │  │PayoutStats  │ │PayoutAmount │ │RecentPayouts    │  │  │
│  │  │Widget       │ │Widget       │ │Widget           │  │  │
│  │  └─────────────┘ └─────────────┘ └─────────────────┘  │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌─────────────────────┐    ┌─────────────────────┐        │
│  │SendInstructionResource│    │BankAccountResource │        │
│  │                     │    │                     │        │
│  │ - List payouts      │    │ - List accounts     │        │
│  │ - Create payout     │    │ - Add account       │        │
│  │ - View details      │    │ - Verify status     │        │
│  │ - Cancel payout     │    │ - Assign groups     │        │
│  │ - Resend webhook    │    │ - Delete account    │        │
│  └─────────────────────┘    └─────────────────────┘        │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## SendInstructionResource

### Database Model (Existing)

```php
// AIArmada\Chip\Models\SendInstruction
@property int $id
@property int $bank_account_id
@property string $amount
@property string $email
@property string $description
@property string $reference
@property string|null $state
@property string|null $receipt_url
@property string|null $slug
```

### Table Schema

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('id')
                ->label('ID')
                ->searchable()
                ->sortable(),
                
            TextColumn::make('bankAccount.name')
                ->label('Recipient')
                ->searchable(),
                
            TextColumn::make('bankAccount.account_number')
                ->label('Account')
                ->toggleable(),
                
            TextColumn::make('amount')
                ->label('Amount')
                ->money('MYR')
                ->sortable(),
                
            TextColumn::make('state')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'completed', 'processed' => 'success',
                    'received', 'queued', 'verifying' => 'warning',
                    'failed', 'cancelled', 'rejected' => 'danger',
                    default => 'gray',
                }),
                
            TextColumn::make('description')
                ->limit(30)
                ->toggleable(),
                
            TextColumn::make('reference')
                ->searchable()
                ->toggleable(),
                
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
        ])
        ->filters([
            SelectFilter::make('state')
                ->options([
                    'received' => 'Received',
                    'queued' => 'Queued',
                    'verifying' => 'Verifying',
                    'completed' => 'Completed',
                    'failed' => 'Failed',
                    'cancelled' => 'Cancelled',
                    'rejected' => 'Rejected',
                ]),
        ])
        ->actions([
            ViewAction::make(),
            Action::make('cancel')
                ->icon(Heroicon::XCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (SendInstruction $record) => in_array($record->state, ['received', 'queued']))
                ->action(fn (SendInstruction $record) => app(ChipSendService::class)->cancelSendInstruction($record->id)),
                
            Action::make('resend_webhook')
                ->icon(Heroicon::ArrowPath)
                ->action(fn (SendInstruction $record) => app(ChipSendService::class)->resendSendInstructionWebhook($record->id)),
        ]);
}
```

### Form Schema (Create Payout)

```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Recipient')
                ->schema([
                    Select::make('bank_account_id')
                        ->label('Bank Account')
                        ->relationship('bankAccount', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('bank_code')->required(),
                            TextInput::make('account_number')->required(),
                            TextInput::make('name')->required(),
                        ]),
                ]),
                
            Section::make('Payment Details')
                ->schema([
                    TextInput::make('amount')
                        ->label('Amount (MYR)')
                        ->numeric()
                        ->required()
                        ->prefix('RM'),
                        
                    TextInput::make('description')
                        ->required()
                        ->maxLength(255),
                        
                    TextInput::make('reference')
                        ->required()
                        ->maxLength(100),
                        
                    TextInput::make('email')
                        ->email()
                        ->required(),
                ]),
        ]);
}
```

### Infolist Schema (View Details)

```php
public static function infolist(Infolist $infolist): Infolist
{
    return $infolist
        ->schema([
            Section::make('Payout Details')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('state')
                        ->badge()
                        ->color(fn (string $state) => SendInstruction::stateColor($state)),
                    TextEntry::make('amount')
                        ->money('MYR'),
                    TextEntry::make('description'),
                    TextEntry::make('reference'),
                    TextEntry::make('email'),
                ]),
                
            Section::make('Recipient')
                ->schema([
                    TextEntry::make('bankAccount.name'),
                    TextEntry::make('bankAccount.bank_code'),
                    TextEntry::make('bankAccount.account_number'),
                ]),
                
            Section::make('Receipt')
                ->schema([
                    TextEntry::make('receipt_url')
                        ->url()
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => $record->receipt_url),
                ]),
                
            Section::make('Timestamps')
                ->schema([
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ]),
        ]);
}
```

---

## BankAccountResource

### Database Model (Existing)

```php
// AIArmada\Chip\Models\BankAccount
@property int $id
@property string|null $status
@property string|null $account_number
@property string|null $bank_code
@property string|null $name
@property bool $is_debiting_account
@property bool $is_crediting_account
@property int|null $group_id
@property string|null $reference
@property string|null $rejection_reason
```

### Table Schema

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('id')
                ->searchable()
                ->sortable(),
                
            TextColumn::make('name')
                ->label('Account Holder')
                ->searchable()
                ->sortable(),
                
            TextColumn::make('bank_code')
                ->label('Bank')
                ->searchable(),
                
            TextColumn::make('account_number')
                ->searchable(),
                
            TextColumn::make('status')
                ->badge()
                ->color(fn (?string $state) => match ($state) {
                    'approved', 'active' => 'success',
                    'pending', 'verifying' => 'warning',
                    'rejected', 'disabled' => 'danger',
                    default => 'gray',
                }),
                
            IconColumn::make('is_crediting_account')
                ->label('Can Receive')
                ->boolean(),
                
            TextColumn::make('rejection_reason')
                ->limit(30)
                ->toggleable(isToggledHiddenByDefault: true),
                
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
        ])
        ->filters([
            SelectFilter::make('status')
                ->options([
                    'pending' => 'Pending',
                    'verifying' => 'Verifying',
                    'approved' => 'Approved',
                    'active' => 'Active',
                    'rejected' => 'Rejected',
                    'disabled' => 'Disabled',
                ]),
                
            TernaryFilter::make('is_crediting_account')
                ->label('Can Receive Payouts'),
        ])
        ->actions([
            ViewAction::make(),
            EditAction::make(),
            
            Action::make('resend_webhook')
                ->icon(Heroicon::ArrowPath)
                ->action(fn (BankAccount $record) => app(ChipSendService::class)->resendBankAccountWebhook($record->id)),
                
            DeleteAction::make()
                ->before(fn (BankAccount $record) => app(ChipSendService::class)->deleteBankAccount($record->id)),
        ]);
}
```

### Form Schema

```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Bank Account Details')
                ->schema([
                    Select::make('bank_code')
                        ->label('Bank')
                        ->options([
                            'MBBEMYKL' => 'Maybank',
                            'CIABORML' => 'CIMB Bank',
                            'PABORMY' => 'Public Bank',
                            'RHBBMY' => 'RHB Bank',
                            'HLBEMYKL' => 'Hong Leong Bank',
                            'AMMBMY' => 'AmBank',
                            'BIMBMY' => 'Bank Islam',
                            'BKRMMY' => 'Bank Rakyat',
                            'BSNAMYK' => 'BSN',
                            'OCBCMY' => 'OCBC Bank',
                            'UOBBMY' => 'UOB Bank',
                            'SCBLMY' => 'Standard Chartered',
                            'HSBCMY' => 'HSBC Bank',
                            'AIBBMY' => 'Affin Bank',
                            'ARBKMY' => 'Alliance Bank',
                        ])
                        ->required()
                        ->searchable(),
                        
                    TextInput::make('account_number')
                        ->required()
                        ->maxLength(20),
                        
                    TextInput::make('name')
                        ->label('Account Holder Name')
                        ->required()
                        ->maxLength(100),
                        
                    TextInput::make('reference')
                        ->maxLength(100)
                        ->helperText('Optional reference for your records'),
                ]),
        ]);
}
```

---

## PayoutDashboardPage

### Page Structure

```php
class PayoutDashboardPage extends Page
{
    protected static string $view = 'filament-chip::pages.payout-dashboard';
    
    protected static ?string $navigationIcon = Heroicon::OutlinedBanknotes;
    protected static ?string $navigationLabel = 'Payouts';
    protected static ?string $title = 'Payout Dashboard';
    protected static ?string $slug = 'chip/payouts';
    protected static ?int $navigationSort = 101;
    
    public function getHeaderWidgets(): array
    {
        return [
            PayoutStatsWidget::class,
            PayoutAmountWidget::class,
        ];
    }
    
    public function getFooterWidgets(): array
    {
        return [
            RecentPayoutsWidget::class,
            BankAccountStatusWidget::class,
        ];
    }
}
```

### PayoutStatsWidget

```php
class PayoutStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $pending = SendInstruction::whereIn('state', ['received', 'queued', 'verifying'])->count();
        $completed = SendInstruction::where('state', 'completed')->count();
        $failed = SendInstruction::whereIn('state', ['failed', 'rejected', 'cancelled'])->count();
        
        return [
            Stat::make('Pending Payouts', $pending)
                ->icon(Heroicon::OutlinedClock)
                ->color('warning'),
                
            Stat::make('Completed', $completed)
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success'),
                
            Stat::make('Failed', $failed)
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger'),
        ];
    }
}
```

### PayoutAmountWidget

```php
class PayoutAmountWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = SendInstruction::where('state', 'completed')
            ->whereDate('created_at', today())
            ->sum('amount');
            
        $thisWeek = SendInstruction::where('state', 'completed')
            ->whereBetween('created_at', [now()->startOfWeek(), now()])
            ->sum('amount');
            
        $thisMonth = SendInstruction::where('state', 'completed')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');
        
        return [
            Stat::make('Today', 'RM ' . number_format($today, 2)),
            Stat::make('This Week', 'RM ' . number_format($thisWeek, 2)),
            Stat::make('This Month', 'RM ' . number_format($thisMonth, 2)),
        ];
    }
}
```

---

## Database Migrations

The `SendInstruction` and `BankAccount` models already exist. Ensure local sync tables exist:

```php
// Verify these migrations exist or create them
Schema::create('chip_send_instructions', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('bank_account_id');
    $table->string('amount');
    $table->string('email');
    $table->string('description');
    $table->string('reference');
    $table->string('state')->nullable();
    $table->string('receipt_url')->nullable();
    $table->string('slug')->nullable();
    $table->timestamps();
    
    $table->index('state');
    $table->index('bank_account_id');
});

Schema::create('chip_bank_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('status')->nullable();
    $table->string('account_number')->nullable();
    $table->string('bank_code')->nullable();
    $table->string('name')->nullable();
    $table->boolean('is_debiting_account')->default(false);
    $table->boolean('is_crediting_account')->default(false);
    $table->unsignedBigInteger('group_id')->nullable();
    $table->string('reference')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('status');
});
```

---

## Success Criteria

- [ ] SendInstructionResource with full CRUD
- [ ] BankAccountResource with full CRUD
- [ ] PayoutDashboardPage with 4 widgets
- [ ] Cancel payout action works
- [ ] Resend webhook actions work
- [ ] Malaysian bank codes dropdown
- [ ] PHPStan Level 6 compliance
- [ ] Resource tests written

---

## Navigation

**Previous:** [05-implementation-roadmap.md](05-implementation-roadmap.md)  
**Next:** [07-financial-management.md](07-financial-management.md)

