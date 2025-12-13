---
title: Operations & Bulk Actions
---

# Operations & Bulk Actions (Phase 8)

> **Document:** 09 of 09  
> **Package:** `aiarmada/filament-chip`  
> **Status:** Vision  
> **Priority:** P3 - Medium-Low

---

## Overview

Build operational tools for **bulk operations**, **refund workflows**, and **data export**. These features enhance administrative efficiency for high-volume operations.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                OPERATIONS & BULK ACTIONS                     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │                 RefundCenterPage                       │  │
│  │                                                        │  │
│  │  - Search purchases for refund                        │  │
│  │  - Partial refund calculator                          │  │
│  │  - Bulk refund selection                              │  │
│  │  - Refund history                                     │  │
│  │  - Refund analytics                                   │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │                 BulkPayoutPage                         │  │
│  │                                                        │  │
│  │  - CSV/Excel import template                          │  │
│  │  - Validation preview                                 │  │
│  │  - Bank account verification                          │  │
│  │  - Batch processing queue                             │  │
│  │  - Progress tracking                                  │  │
│  │  - Error handling & retry                             │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │                 Export Features                        │  │
│  │                                                        │  │
│  │  - Transactions CSV/Excel export                      │  │
│  │  - Analytics report generation                        │  │
│  │  - Scheduled report emails                            │  │
│  │  - Statement export                                   │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## RefundCenterPage

### Page Structure

```php
class RefundCenterPage extends Page
{
    protected static string $view = 'filament-chip::pages.refund-center';
    
    protected static ?string $navigationIcon = Heroicon::OutlinedReceiptRefund;
    protected static ?string $navigationLabel = 'Refund Center';
    protected static ?string $title = 'Refund Center';
    protected static ?string $slug = 'chip/refunds';
    protected static ?int $navigationSort = 103;
    
    public string $search = '';
    public array $selectedPurchases = [];
    public array $refundAmounts = [];
    
    // Search purchases eligible for refund
    public function searchPurchases(): void
    {
        $this->purchaseResults = Purchase::query()
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->where('chip_id', 'like', "%{$this->search}%")
                    ->orWhere('reference', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('email', 'like', "%{$this->search}%"));
            })
            ->with('client')
            ->latest()
            ->take(50)
            ->get();
    }
    
    // Process single refund
    public function processRefund(string $purchaseId, ?int $amount = null): void
    {
        try {
            $result = app(ChipCollectService::class)->refundPurchase($purchaseId, $amount);
            
            Notification::make()
                ->title('Refund processed')
                ->body("Purchase {$purchaseId} has been refunded.")
                ->success()
                ->send();
                
        } catch (ChipException $e) {
            Notification::make()
                ->title('Refund failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    // Process bulk refunds
    public function processBulkRefunds(): void
    {
        $succeeded = 0;
        $failed = 0;
        
        foreach ($this->selectedPurchases as $purchaseId) {
            $amount = $this->refundAmounts[$purchaseId] ?? null;
            
            try {
                app(ChipCollectService::class)->refundPurchase($purchaseId, $amount);
                $succeeded++;
            } catch (Throwable $e) {
                $failed++;
                report($e);
            }
        }
        
        Notification::make()
            ->title('Bulk refund complete')
            ->body("{$succeeded} succeeded, {$failed} failed")
            ->success()
            ->send();
            
        $this->selectedPurchases = [];
        $this->refundAmounts = [];
    }
}
```

### View Template

```blade
<x-filament-panels::page>
    {{-- Search Section --}}
    <x-filament::section>
        <x-slot name="heading">Search Purchases</x-slot>
        
        <div class="flex gap-4">
            <x-filament::input.wrapper class="flex-1">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.500ms="search"
                    placeholder="Search by Purchase ID, Reference, or Email..."
                />
            </x-filament::input.wrapper>
            
            <x-filament::button wire:click="searchPurchases">
                Search
            </x-filament::button>
        </div>
    </x-filament::section>
    
    {{-- Results Section --}}
    @if($purchaseResults->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                Refundable Purchases ({{ $purchaseResults->count() }})
            </x-slot>
            
            <x-slot name="headerEnd">
                <x-filament::button
                    wire:click="processBulkRefunds"
                    color="danger"
                    :disabled="empty($selectedPurchases)"
                >
                    Refund Selected ({{ count($selectedPurchases) }})
                </x-filament::button>
            </x-slot>
            
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="p-2 text-left">
                            <x-filament::input.checkbox wire:model.live="selectAll" />
                        </th>
                        <th class="p-2 text-left">Purchase ID</th>
                        <th class="p-2 text-left">Client</th>
                        <th class="p-2 text-left">Amount</th>
                        <th class="p-2 text-left">Date</th>
                        <th class="p-2 text-left">Refund Amount</th>
                        <th class="p-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchaseResults as $purchase)
                        <tr class="border-b">
                            <td class="p-2">
                                <x-filament::input.checkbox 
                                    wire:model.live="selectedPurchases" 
                                    value="{{ $purchase->chip_id }}" 
                                />
                            </td>
                            <td class="p-2 font-mono text-sm">{{ $purchase->chip_id }}</td>
                            <td class="p-2">{{ $purchase->client?->email }}</td>
                            <td class="p-2">RM {{ number_format($purchase->total_minor / 100, 2) }}</td>
                            <td class="p-2">{{ $purchase->created_at->format('Y-m-d H:i') }}</td>
                            <td class="p-2">
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        type="number"
                                        wire:model="refundAmounts.{{ $purchase->chip_id }}"
                                        placeholder="Full"
                                        class="w-24"
                                    />
                                </x-filament::input.wrapper>
                            </td>
                            <td class="p-2">
                                <x-filament::button
                                    size="sm"
                                    color="danger"
                                    wire:click="processRefund('{{ $purchase->chip_id }}', $refundAmounts['{{ $purchase->chip_id }}'] ?? null)"
                                >
                                    Refund
                                </x-filament::button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif
    
    {{-- Refund History --}}
    <x-filament::section>
        <x-slot name="heading">Recent Refunds</x-slot>
        
        <livewire:recent-refunds-table />
    </x-filament::section>
</x-filament-panels::page>
```

---

## BulkPayoutPage

### Page Structure

```php
class BulkPayoutPage extends Page
{
    protected static string $view = 'filament-chip::pages.bulk-payout';
    
    protected static ?string $navigationIcon = Heroicon::OutlinedDocumentArrowUp;
    protected static ?string $navigationLabel = 'Bulk Payouts';
    protected static ?string $title = 'Bulk Payout Upload';
    protected static ?string $slug = 'chip/bulk-payouts';
    protected static ?int $navigationSort = 104;
    
    public ?TemporaryUploadedFile $file = null;
    public array $parsedRows = [];
    public array $validationErrors = [];
    public array $processingStatus = [];
    public bool $isProcessing = false;
    
    public function uploadFile(): void
    {
        $this->validate([
            'file' => 'required|file|mimes:csv,xlsx|max:10240',
        ]);
        
        $this->parsedRows = $this->parseFile($this->file);
        $this->validateRows();
    }
    
    private function parseFile(TemporaryUploadedFile $file): array
    {
        // Parse CSV/Excel using OpenSpout or similar
        $rows = [];
        
        // Expected columns:
        // bank_code, account_number, account_name, amount, description, reference, email
        
        return $rows;
    }
    
    private function validateRows(): void
    {
        $this->validationErrors = [];
        
        foreach ($this->parsedRows as $index => $row) {
            $errors = [];
            
            // Validate bank code
            if (!$this->isValidBankCode($row['bank_code'] ?? '')) {
                $errors[] = 'Invalid bank code';
            }
            
            // Validate account number
            if (empty($row['account_number'])) {
                $errors[] = 'Account number required';
            }
            
            // Validate amount
            if (!is_numeric($row['amount'] ?? '') || $row['amount'] <= 0) {
                $errors[] = 'Invalid amount';
            }
            
            // Validate email
            if (!filter_var($row['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email';
            }
            
            if (!empty($errors)) {
                $this->validationErrors[$index] = $errors;
            }
        }
    }
    
    public function processPayouts(): void
    {
        if (!empty($this->validationErrors)) {
            Notification::make()
                ->title('Cannot process')
                ->body('Please fix validation errors first.')
                ->danger()
                ->send();
            return;
        }
        
        $this->isProcessing = true;
        
        // Dispatch batch job
        Bus::batch(
            collect($this->parsedRows)->map(fn ($row, $index) => 
                new ProcessBulkPayoutJob($row, $index, $this->getId())
            )->toArray()
        )
        ->name('Bulk Payout ' . now()->format('Y-m-d H:i'))
        ->onQueue('payouts')
        ->dispatch();
        
        Notification::make()
            ->title('Processing started')
            ->body('Payouts are being processed in the background.')
            ->success()
            ->send();
    }
    
    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            
            // Header row
            fputcsv($handle, [
                'bank_code',
                'account_number', 
                'account_name',
                'amount',
                'description',
                'reference',
                'email'
            ]);
            
            // Example row
            fputcsv($handle, [
                'MBBEMYKL',
                '1234567890',
                'John Doe',
                '100.00',
                'Affiliate payout',
                'AFF-001',
                'john@example.com'
            ]);
            
            fclose($handle);
        }, 'bulk-payout-template.csv');
    }
}
```

### View Template

```blade
<x-filament-panels::page>
    {{-- Upload Section --}}
    <x-filament::section>
        <x-slot name="heading">Upload Payout File</x-slot>
        
        <div class="space-y-4">
            <x-filament::button wire:click="downloadTemplate" color="gray">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                Download Template
            </x-filament::button>
            
            <div x-data="{ uploading: false }">
                <input 
                    type="file" 
                    wire:model="file"
                    accept=".csv,.xlsx"
                    class="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-primary-50 file:text-primary-700"
                />
            </div>
            
            @if($file)
                <x-filament::button wire:click="uploadFile">
                    Parse File
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>
    
    {{-- Validation Preview --}}
    @if(!empty($parsedRows))
        <x-filament::section>
            <x-slot name="heading">
                Preview ({{ count($parsedRows) }} rows)
                @if(empty($validationErrors))
                    <x-filament::badge color="success">Valid</x-filament::badge>
                @else
                    <x-filament::badge color="danger">{{ count($validationErrors) }} errors</x-filament::badge>
                @endif
            </x-slot>
            
            <x-slot name="headerEnd">
                <x-filament::button
                    wire:click="processPayouts"
                    :disabled="!empty($validationErrors) || $isProcessing"
                >
                    @if($isProcessing)
                        Processing...
                    @else
                        Process All Payouts
                    @endif
                </x-filament::button>
            </x-slot>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50">
                            <th class="p-2 text-left">#</th>
                            <th class="p-2 text-left">Bank</th>
                            <th class="p-2 text-left">Account</th>
                            <th class="p-2 text-left">Name</th>
                            <th class="p-2 text-left">Amount</th>
                            <th class="p-2 text-left">Email</th>
                            <th class="p-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($parsedRows as $index => $row)
                            <tr class="border-b {{ isset($validationErrors[$index]) ? 'bg-danger-50' : '' }}">
                                <td class="p-2">{{ $index + 1 }}</td>
                                <td class="p-2">{{ $row['bank_code'] ?? '-' }}</td>
                                <td class="p-2 font-mono">{{ $row['account_number'] ?? '-' }}</td>
                                <td class="p-2">{{ $row['account_name'] ?? '-' }}</td>
                                <td class="p-2">RM {{ number_format($row['amount'] ?? 0, 2) }}</td>
                                <td class="p-2">{{ $row['email'] ?? '-' }}</td>
                                <td class="p-2">
                                    @if(isset($validationErrors[$index]))
                                        <x-filament::badge color="danger">
                                            {{ implode(', ', $validationErrors[$index]) }}
                                        </x-filament::badge>
                                    @elseif(isset($processingStatus[$index]))
                                        <x-filament::badge :color="$processingStatus[$index]['success'] ? 'success' : 'danger'">
                                            {{ $processingStatus[$index]['message'] }}
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray">Pending</x-filament::badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 p-4 bg-gray-50 rounded">
                <p class="font-medium">Total Amount: RM {{ number_format(collect($parsedRows)->sum('amount'), 2) }}</p>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
```

---

## Export Features

### Add Export Actions to Resources

```php
// In PurchaseResource table actions
->headerActions([
    ExportAction::make()
        ->exporter(PurchaseExporter::class)
        ->fileName(fn () => 'purchases-' . now()->format('Y-m-d'))
        ->formats([
            ExportFormat::Csv,
            ExportFormat::Xlsx,
        ]),
])

// In SendInstructionResource table actions
->headerActions([
    ExportAction::make()
        ->exporter(PayoutExporter::class)
        ->fileName(fn () => 'payouts-' . now()->format('Y-m-d')),
])
```

### PurchaseExporter

```php
class PurchaseExporter extends Exporter
{
    protected static ?string $model = Purchase::class;
    
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('chip_id')
                ->label('Purchase ID'),
                
            ExportColumn::make('client.email')
                ->label('Client Email'),
                
            ExportColumn::make('status'),
            
            ExportColumn::make('total_minor')
                ->label('Amount')
                ->formatStateUsing(fn ($state) => number_format($state / 100, 2)),
                
            ExportColumn::make('currency'),
            
            ExportColumn::make('payment_method'),
            
            ExportColumn::make('created_at')
                ->label('Date'),
                
            ExportColumn::make('reference'),
        ];
    }
}
```

### Scheduled Report Generation

```php
// In console kernel
$schedule->job(new GenerateWeeklyPaymentReport)
    ->weekly()
    ->mondays()
    ->at('08:00');
    
$schedule->job(new GenerateMonthlyAnalyticsReport)
    ->monthly()
    ->at('08:00');
```

```php
class GenerateWeeklyPaymentReport implements ShouldQueue
{
    public function handle(): void
    {
        $startDate = now()->subWeek()->startOfWeek();
        $endDate = now()->subWeek()->endOfWeek();
        
        $report = app(LocalAnalyticsService::class)
            ->getDashboardMetrics($startDate, $endDate);
        
        // Generate PDF or Excel
        $file = $this->generateReport($report, $startDate, $endDate);
        
        // Email to configured recipients
        Mail::to(config('chip.reports.recipients'))
            ->send(new WeeklyPaymentReport($file, $startDate, $endDate));
    }
}
```

---

## ProcessBulkPayoutJob

```php
class ProcessBulkPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public array $row,
        public int $index,
        public string $pageId
    ) {}
    
    public function handle(ChipSendService $sendService): void
    {
        try {
            // First ensure bank account exists
            $bankAccount = $this->getOrCreateBankAccount($sendService);
            
            // Create send instruction
            $result = $sendService->createSendInstruction(
                amountInCents: (int) ($this->row['amount'] * 100),
                currency: 'MYR',
                recipientBankAccountId: $bankAccount->id,
                description: $this->row['description'],
                reference: $this->row['reference'],
                email: $this->row['email'],
            );
            
            // Broadcast success
            BulkPayoutProcessed::dispatch($this->pageId, $this->index, true, 'Created');
            
        } catch (Throwable $e) {
            // Broadcast failure
            BulkPayoutProcessed::dispatch($this->pageId, $this->index, false, $e->getMessage());
            
            throw $e;
        }
    }
    
    private function getOrCreateBankAccount(ChipSendService $sendService): BankAccountData
    {
        // Check if account exists locally
        $existing = BankAccount::where('bank_code', $this->row['bank_code'])
            ->where('account_number', $this->row['account_number'])
            ->first();
            
        if ($existing && $existing->is_active) {
            return BankAccountData::from($existing);
        }
        
        // Create in CHIP
        return $sendService->createBankAccount(
            bankCode: $this->row['bank_code'],
            accountNumber: $this->row['account_number'],
            accountHolderName: $this->row['account_name'],
            reference: $this->row['reference'] ?? null,
        );
    }
}
```

---

## Success Criteria

- [ ] RefundCenterPage with search and partial refunds
- [ ] Bulk refund selection and processing
- [ ] Refund history display
- [ ] BulkPayoutPage with file upload
- [ ] CSV template download
- [ ] Validation preview with error highlighting
- [ ] Background batch processing
- [ ] Progress tracking via broadcasting
- [ ] Export action on PurchaseResource
- [ ] Export action on SendInstructionResource
- [ ] Weekly and monthly scheduled reports
- [ ] PHPStan Level 6 compliance

---

## Navigation

**Previous:** [08-token-webhook-management.md](08-token-webhook-management.md)

