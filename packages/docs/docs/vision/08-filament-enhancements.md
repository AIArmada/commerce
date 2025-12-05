# Filament Enhancements

> **Document:** 08 of 10  
> **Package:** `aiarmada/filament-docs`  
> **Status:** Vision

---

## Overview

Transform the Filament Docs panel into a **complete document management system** with intuitive creation, workflows, email tracking, and comprehensive analytics.

---

## Dashboard Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                     DOCS DASHBOARD                            │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐            │
│  │ Revenue │ │ Pending │ │ Overdue │ │  Sent   │            │
│  │ (MTD)   │ │ Approve │ │ Invoices│ │  Today  │            │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘            │
│                                                               │
│  ┌────────────────────────────────┐ ┌───────────────────┐   │
│  │    Invoice Status Overview     │ │  Quick Actions    │   │
│  │    (Donut Chart)               │ │                   │   │
│  └────────────────────────────────┘ └───────────────────┘   │
│                                                               │
│  ┌────────────────────────────────────────────────────────┐  │
│  │              Recent Documents Table                     │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## Dashboard Widgets

### DocumentStatsWidget

```php
class DocumentStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $pollingInterval = '60s';
    
    protected function getStats(): array
    {
        $startOfMonth = now()->startOfMonth();
        
        return [
            Stat::make('Revenue (MTD)', $this->getMonthlyRevenue())
                ->description($this->getRevenueGrowth() . '% vs last month')
                ->descriptionIcon($this->getRevenueGrowth() >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($this->getRevenueGrowth() >= 0 ? 'success' : 'danger')
                ->chart($this->getDailyRevenueChart()),
            
            Stat::make('Pending Approval', $this->getPendingApprovalCount())
                ->description('Awaiting review')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            
            Stat::make('Overdue Invoices', $this->getOverdueCount())
                ->description($this->getOverdueAmount())
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
            
            Stat::make('Sent Today', $this->getSentTodayCount())
                ->description('Documents delivered')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('success'),
        ];
    }
    
    private function getMonthlyRevenue(): string
    {
        $revenue = Document::query()
            ->where('type', DocumentType::Invoice)
            ->where('status', DocumentStatus::Paid)
            ->whereMonth('created_at', now()->month)
            ->sum('total_minor');
        
        return Money::format($revenue);
    }
    
    private function getOverdueCount(): int
    {
        return Document::query()
            ->where('type', DocumentType::Invoice)
            ->where('status', DocumentStatus::Overdue)
            ->whereColumn('paid_minor', '<', 'total_minor')
            ->count();
    }
}
```

### QuickActionsWidget

```php
class QuickActionsWidget extends Widget
{
    protected static string $view = 'filament-docs::widgets.quick-actions';
    protected int|string|array $columnSpan = 1;
    
    public function getActions(): array
    {
        return [
            Action::make('create_invoice')
                ->label('New Invoice')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->url(DocumentResource::getUrl('create', ['type' => 'invoice'])),
            
            Action::make('create_quotation')
                ->label('New Quotation')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->url(DocumentResource::getUrl('create', ['type' => 'quotation'])),
            
            Action::make('bulk_send')
                ->label('Bulk Send')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->action('openBulkSendModal'),
        ];
    }
}
```

---

## Document Resource

### DocumentResource

```php
class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Documents';
    
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', DocumentStatus::Draft)->count();
    }
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Document Details')
                    ->schema([
                        Select::make('type')
                            ->options(DocumentType::class)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => 
                                $set('terms', DocumentType::from($state)?->getDefaultTerms())
                            ),
                        
                        TextInput::make('number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated'),
                        
                        DatePicker::make('issue_date')
                            ->default(now())
                            ->required(),
                        
                        DatePicker::make('due_date')
                            ->visible(fn ($get) => DocumentType::tryFrom($get('type'))?->hasDueDate())
                            ->default(fn () => now()->addDays(30)),
                        
                        DatePicker::make('valid_until')
                            ->visible(fn ($get) => DocumentType::tryFrom($get('type'))?->hasValidityPeriod())
                            ->default(fn () => now()->addDays(30)),
                    ])
                    ->columns(3),
                
                Section::make('Customer')
                    ->schema([
                        TextInput::make('customer_name')
                            ->required()
                            ->maxLength(255),
                        
                        TextInput::make('customer_email')
                            ->email()
                            ->maxLength(255),
                        
                        Textarea::make('customer_address')
                            ->rows(3),
                    ])
                    ->columns(3),
                
                Section::make('Line Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                TextInput::make('description')
                                    ->required()
                                    ->columnSpan(3),
                                
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(),
                                
                                TextInput::make('unit_price_minor')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->live(),
                                
                                TextInput::make('tax_rate')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(0),
                                
                                Placeholder::make('line_total')
                                    ->label('Total')
                                    ->content(fn ($get) => Money::format(
                                        ($get('quantity') ?? 0) * ($get('unit_price_minor') ?? 0)
                                    )),
                            ])
                            ->columns(7)
                            ->defaultItems(1)
                            ->reorderable()
                            ->collapsible(),
                    ]),
                
                Section::make('Additional Details')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->placeholder('Notes visible to customer'),
                        
                        Textarea::make('terms')
                            ->rows(3)
                            ->placeholder('Terms and conditions'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (DocumentType $state) => match ($state) {
                        DocumentType::Invoice => 'primary',
                        DocumentType::Quotation => 'info',
                        DocumentType::CreditNote => 'warning',
                        default => 'gray',
                    }),
                
                TextColumn::make('customer_name')
                    ->searchable()
                    ->limit(30),
                
                TextColumn::make('total_minor')
                    ->label('Total')
                    ->money('MYR')
                    ->sortable(),
                
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (DocumentStatus $state) => $state->color()),
                
                TextColumn::make('issue_date')
                    ->date()
                    ->sortable(),
                
                TextColumn::make('due_date')
                    ->date()
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(DocumentType::class),
                
                SelectFilter::make('status')
                    ->options(DocumentStatus::class),
                
                Filter::make('overdue')
                    ->query(fn (Builder $query) => $query
                        ->where('type', DocumentType::Invoice)
                        ->where('due_date', '<', now())
                        ->whereColumn('paid_minor', '<', 'total_minor')),
                
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('view')
                        ->icon('heroicon-o-eye')
                        ->url(fn ($record) => static::getUrl('view', ['record' => $record])),
                    
                    Action::make('download_pdf')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn ($record) => $this->downloadPdf($record)),
                    
                    Action::make('send_email')
                        ->icon('heroicon-o-envelope')
                        ->form([
                            TextInput::make('recipient')
                                ->email()
                                ->default(fn ($record) => $record->customer_email),
                            Textarea::make('message'),
                        ])
                        ->action(fn ($record, array $data) => app(DocumentEmailService::class)->send($record, $data)),
                    
                    Action::make('convert')
                        ->icon('heroicon-o-arrows-right-left')
                        ->visible(fn ($record) => ! empty($record->type->canConvertTo()))
                        ->form([
                            Select::make('target_type')
                                ->options(fn ($record) => collect($record->type->canConvertTo())
                                    ->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                        ])
                        ->action(fn ($record, array $data) => 
                            app(DocumentFactory::class)->convert($record, DocumentType::from($data['target_type']))
                        ),
                    
                    Action::make('void')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->status !== DocumentStatus::Voided)
                        ->action(fn ($record) => $record->update(['status' => DocumentStatus::Voided])),
                ]),
            ])
            ->bulkActions([
                BulkAction::make('export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Collection $records) => Excel::download(
                        new DocumentsExport($records),
                        'documents.xlsx'
                    )),
                
                BulkAction::make('bulk_send')
                    ->icon('heroicon-o-paper-airplane')
                    ->action(fn (Collection $records) => 
                        $records->each(fn ($doc) => app(DocumentEmailService::class)->send($doc))
                    ),
            ]);
    }
}
```

---

## Sequence Management

### SequenceResource

```php
class SequenceResource extends Resource
{
    protected static ?string $model = DocSequence::class;
    protected static ?string $navigationIcon = 'heroicon-o-hashtag';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Number Sequences';
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Sequence Details')
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true),
                        
                        Select::make('document_type')
                            ->options(DocumentType::class)
                            ->required(),
                    ])
                    ->columns(3),
                
                Section::make('Format')
                    ->schema([
                        TextInput::make('prefix')
                            ->placeholder('INV'),
                        
                        TextInput::make('suffix'),
                        
                        TextInput::make('padding')
                            ->numeric()
                            ->default(5)
                            ->minValue(1)
                            ->maxValue(10),
                        
                        Select::make('reset_frequency')
                            ->options(ResetFrequency::class)
                            ->default('yearly'),
                    ])
                    ->columns(4),
                
                Section::make('Preview')
                    ->schema([
                        Placeholder::make('preview')
                            ->label('Sample Number')
                            ->content(fn ($get) => $this->generatePreview($get)),
                    ]),
                
                Section::make('Status')
                    ->schema([
                        TextInput::make('current_number')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                        
                        Toggle::make('is_default'),
                        
                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(3),
            ]);
    }
}
```

---

## Email Template Builder

### EmailTemplateResource

```php
class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Settings';
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Template Details')
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true),
                        
                        Select::make('document_type')
                            ->options(DocumentType::class)
                            ->nullable(),
                    ])
                    ->columns(3),
                
                Section::make('Content')
                    ->schema([
                        TextInput::make('subject_template')
                            ->required()
                            ->helperText('Use {{ variable }} for dynamic values'),
                        
                        RichEditor::make('body_template')
                            ->required()
                            ->helperText('Available variables: {{ document_number }}, {{ customer_name }}, {{ total }}, {{ due_date }}, {{ view_url }}, {{ pay_url }}'),
                    ]),
                
                Section::make('Settings')
                    ->schema([
                        Toggle::make('is_default')
                            ->helperText('Default template for this document type'),
                        
                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
```

---

## Approval Management

### PendingApprovalsPage

```php
class PendingApprovalsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Documents';
    protected static string $view = 'filament-docs::pages.pending-approvals';
    
    public static function getNavigationBadge(): ?string
    {
        $userId = auth()->id();
        
        return (string) DocumentApproval::query()
            ->where('status', 'pending')
            ->whereHas('workflowConfig', function ($query) use ($userId) {
                // Check if user can approve at this level
            })
            ->count();
    }
    
    public function getTableQuery(): Builder
    {
        return Document::query()
            ->whereHas('approvals', fn ($q) => $q->where('status', 'pending'))
            ->with(['approvals' => fn ($q) => $q->where('status', 'pending')]);
    }
    
    public function approveAction(): Action
    {
        return Action::make('approve')
            ->icon('heroicon-o-check')
            ->color('success')
            ->form([
                Textarea::make('comment'),
            ])
            ->action(function (Document $record, array $data) {
                app(ApprovalService::class)->approve($record, auth()->user(), $data['comment']);
                
                Notification::make()
                    ->title('Document approved')
                    ->success()
                    ->send();
            });
    }
    
    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->form([
                Textarea::make('reason')
                    ->required()
                    ->label('Rejection Reason'),
            ])
            ->action(function (Document $record, array $data) {
                app(ApprovalService::class)->reject($record, auth()->user(), $data['reason']);
                
                Notification::make()
                    ->title('Document rejected')
                    ->warning()
                    ->send();
            });
    }
}
```

---

## Version History

### DocumentVersionsRelationManager

```php
class DocumentVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';
    protected static ?string $title = 'Version History';
    
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')
                    ->badge(),
                
                TextColumn::make('changedBy.name')
                    ->label('Changed By'),
                
                TextColumn::make('change_reason')
                    ->limit(30),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('view_changes')
                    ->icon('heroicon-o-eye')
                    ->modalContent(fn ($record) => view('filament-docs::modals.version-diff', [
                        'changes' => $record->changes,
                    ])),
                
                Action::make('restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn ($record) => 
                        app(VersioningService::class)->restore(
                            $this->ownerRecord,
                            $record,
                            auth()->user()
                        )
                    ),
            ]);
    }
}
```

---

## E-Invoice Submission

### EInvoiceSubmissionAction

```php
// In DocumentResource view page
public function getHeaderActions(): array
{
    return [
        Action::make('submit_einvoice')
            ->label('Submit E-Invoice')
            ->icon('heroicon-o-cloud-arrow-up')
            ->color('success')
            ->visible(fn ($record) => 
                $record->type === DocumentType::Invoice &&
                ! $record->is_e_invoiced &&
                config('docs.einvoice.enabled')
            )
            ->requiresConfirmation()
            ->action(function ($record) {
                try {
                    $submission = app(EInvoiceService::class)->submit($record);
                    
                    Notification::make()
                        ->title('E-Invoice Submitted')
                        ->body("Submission ID: {$submission->submission_uid}")
                        ->success()
                        ->send();
                } catch (EInvoiceException $e) {
                    Notification::make()
                        ->title('Submission Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            }),
        
        Action::make('view_einvoice')
            ->label('View E-Invoice')
            ->icon('heroicon-o-qr-code')
            ->visible(fn ($record) => $record->is_e_invoiced)
            ->url(fn ($record) => $record->eInvoiceSubmission?->qr_url)
            ->openUrlInNewTab(),
    ];
}
```

---

## Navigation Structure

```
Documents
├── All Documents
├── Invoices
├── Quotations
├── Credit Notes
├── Pending Approvals (badge)
└── Email Log

Analytics
├── Revenue Report
├── Aging Report
└── Document Metrics

Settings
├── Number Sequences
├── Email Templates
├── Approval Workflows
└── E-Invoice Settings
```

---

## Navigation

**Previous:** [07-database-evolution.md](07-database-evolution.md)  
**Next:** [09-implementation-roadmap.md](09-implementation-roadmap.md)
