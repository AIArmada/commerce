---
title: Token & Webhook Management
---

# Token & Webhook Management (Phase 7)

> **Document:** 08 of 09  
> **Package:** `aiarmada/filament-chip`  
> **Status:** Vision  
> **Priority:** P2 - Medium

---

## Overview

Build administrative interfaces for **Recurring Tokens** and **CHIP Webhook Configuration**. These features provide control over saved payment methods and webhook delivery settings.

---

## API Foundation (Already Implemented in chip)

```php
// Recurring Tokens (via Clients API)
ChipCollectService::listClientRecurringTokens($clientId)
ChipCollectService::getClientRecurringToken($clientId, $tokenId)
ChipCollectService::deleteClientRecurringToken($clientId, $tokenId)
ChipCollectService::deleteRecurringToken($purchaseId)

// Webhook Configuration (CHIP API level)
ChipCollectService::createWebhook($data)
ChipCollectService::getWebhook($webhookId)
ChipCollectService::updateWebhook($webhookId, $data)
ChipCollectService::deleteWebhook($webhookId)
ChipCollectService::listWebhooks($filters)

// Send Webhooks
ChipSendService::createSendWebhook($data)
ChipSendService::getSendWebhook($id)
ChipSendService::updateSendWebhook($id, $data)
ChipSendService::deleteSendWebhook($id)
ChipSendService::listSendWebhooks($filters)
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│              TOKEN & WEBHOOK MANAGEMENT                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │            RecurringTokenResource                      │  │
│  │                                                        │  │
│  │  - List all tokens across clients                     │  │
│  │  - View token details (card type, last 4, expiry)    │  │
│  │  - See associated recurring schedules                 │  │
│  │  - Revoke tokens                                      │  │
│  │  - Token usage history                                │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │            WebhookConfigResource                       │  │
│  │                                                        │  │
│  │  - List configured webhooks in CHIP                   │  │
│  │  - Add new webhook endpoints                          │  │
│  │  - Select events to receive                           │  │
│  │  - Update webhook URLs                                │  │
│  │  - Test webhook delivery                              │  │
│  │  - Delete webhooks                                    │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │            TokenStatsWidget                            │  │
│  │                                                        │  │
│  │  - Total active tokens                                │  │
│  │  - Tokens by card type                                │  │
│  │  - Expiring soon count                                │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## RecurringTokenResource

### Concept

Recurring tokens are stored in CHIP and associated with Clients. This resource provides a unified view across all clients.

### Virtual Model Approach

Since tokens are fetched from API, we create a virtual resource:

```php
class RecurringTokenResource extends Resource
{
    // No Eloquent model - data fetched from API
    protected static ?string $model = null;
    
    protected static ?string $navigationIcon = Heroicon::OutlinedCreditCard;
    protected static ?string $navigationLabel = 'Recurring Tokens';
    protected static ?string $slug = 'chip/recurring-tokens';
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecurringTokens::class,
            'view' => Pages\ViewRecurringToken::class,
        ];
    }
}
```

### ListRecurringTokens Page

```php
class ListRecurringTokens extends Page implements HasTable
{
    use InteractsWithTable;
    
    protected static string $resource = RecurringTokenResource::class;
    
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTokensQuery())
            ->columns([
                TextColumn::make('token_id')
                    ->label('Token ID')
                    ->searchable(),
                    
                TextColumn::make('client.email')
                    ->label('Client')
                    ->searchable(),
                    
                TextColumn::make('card_type')
                    ->label('Card Type')
                    ->badge(),
                    
                TextColumn::make('last_four')
                    ->label('Last 4 Digits'),
                    
                TextColumn::make('expiry')
                    ->label('Expiry')
                    ->date('m/Y'),
                    
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                    
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('card_type')
                    ->options([
                        'visa' => 'Visa',
                        'mastercard' => 'Mastercard',
                        'amex' => 'American Express',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                
                Action::make('revoke')
                    ->icon(Heroicon::Trash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke Token')
                    ->modalDescription('This will permanently delete the recurring token. Any active recurring schedules using this token will fail.')
                    ->action(function (array $data, $record): void {
                        app(ChipCollectService::class)
                            ->deleteClientRecurringToken($record['client_id'], $record['token_id']);
                            
                        Notification::make()
                            ->title('Token revoked')
                            ->success()
                            ->send();
                    }),
            ]);
    }
    
    /**
     * Build query from all clients' tokens
     */
    private function getTokensQuery(): Builder
    {
        // This would need a custom implementation
        // Option 1: Cache tokens locally
        // Option 2: Create a virtual table/collection
        // Option 3: Use a custom query builder
    }
}
```

### ViewRecurringToken Page

```php
class ViewRecurringToken extends Page
{
    protected static string $resource = RecurringTokenResource::class;
    
    public array $token = [];
    public array $schedules = [];
    public array $recentCharges = [];
    
    public function mount(string $clientId, string $tokenId): void
    {
        $this->token = app(ChipCollectService::class)
            ->getClientRecurringToken($clientId, $tokenId);
            
        // Get related recurring schedules using this token
        $this->schedules = RecurringSchedule::query()
            ->where('recurring_token_id', $tokenId)
            ->get()
            ->toArray();
            
        // Get recent charges
        $this->recentCharges = RecurringCharge::query()
            ->whereIn('schedule_id', collect($this->schedules)->pluck('id'))
            ->latest()
            ->take(10)
            ->get()
            ->toArray();
    }
}
```

---

## WebhookConfigResource

### Concept

Manage webhooks configured in CHIP itself (not local webhook logs).

### Table Schema

```php
public static function table(Table $table): Table
{
    return $table
        ->query($this->getWebhooksFromApi())
        ->columns([
            TextColumn::make('id')
                ->label('Webhook ID')
                ->searchable(),
                
            TextColumn::make('url')
                ->label('Endpoint URL')
                ->limit(50)
                ->url(fn ($record) => $record['url'])
                ->openUrlInNewTab(),
                
            TextColumn::make('events')
                ->label('Events')
                ->badge()
                ->formatStateUsing(fn ($state) => count($state) . ' events'),
                
            IconColumn::make('is_active')
                ->label('Active')
                ->boolean(),
                
            TextColumn::make('created_at')
                ->label('Created')
                ->dateTime(),
        ])
        ->actions([
            EditAction::make(),
            
            Action::make('test')
                ->icon(Heroicon::Bolt)
                ->color('warning')
                ->action(function ($record): void {
                    // Send test webhook
                    Notification::make()
                        ->title('Test webhook sent')
                        ->success()
                        ->send();
                }),
                
            DeleteAction::make()
                ->before(function ($record): void {
                    app(ChipCollectService::class)->deleteWebhook($record['id']);
                }),
        ])
        ->headerActions([
            CreateAction::make()
                ->form([
                    TextInput::make('url')
                        ->label('Webhook URL')
                        ->url()
                        ->required()
                        ->placeholder('https://your-domain.com/webhooks/chip'),
                        
                    CheckboxList::make('events')
                        ->label('Events to Receive')
                        ->options([
                            'purchase.created' => 'Purchase Created',
                            'purchase.paid' => 'Purchase Paid',
                            'purchase.cancelled' => 'Purchase Cancelled',
                            'purchase.refunded' => 'Purchase Refunded',
                            'payment.created' => 'Payment Created',
                            'payment.paid' => 'Payment Paid',
                            'payment.failed' => 'Payment Failed',
                        ])
                        ->columns(2)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(ChipCollectService::class)->createWebhook([
                        'url' => $data['url'],
                        'events' => $data['events'],
                    ]);
                }),
        ]);
}
```

### Form Schema (Edit)

```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Webhook Configuration')
                ->schema([
                    TextInput::make('url')
                        ->label('Webhook URL')
                        ->url()
                        ->required()
                        ->placeholder('https://your-domain.com/webhooks/chip'),
                        
                    CheckboxList::make('events')
                        ->label('Events to Receive')
                        ->options([
                            // Collect Events
                            'purchase.created' => 'Purchase Created',
                            'purchase.paid' => 'Purchase Paid',
                            'purchase.cancelled' => 'Purchase Cancelled',
                            'purchase.refunded' => 'Purchase Refunded',
                            'payment.created' => 'Payment Created',
                            'payment.paid' => 'Payment Paid',
                            'payment.failed' => 'Payment Failed',
                        ])
                        ->columns(2)
                        ->required(),
                        
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),
        ]);
}
```

---

## SendWebhookConfigResource

### Similar to Collect Webhooks but for Send Events

```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Send Webhook Configuration')
                ->schema([
                    TextInput::make('url')
                        ->label('Webhook URL')
                        ->url()
                        ->required(),
                        
                    CheckboxList::make('events')
                        ->label('Events to Receive')
                        ->options([
                            // Send Events
                            'send_instruction.received' => 'Send Instruction Received',
                            'send_instruction.completed' => 'Send Instruction Completed',
                            'send_instruction.rejected' => 'Send Instruction Rejected',
                            'bank_account.verified' => 'Bank Account Verified',
                            'bank_account.rejected' => 'Bank Account Rejected',
                        ])
                        ->columns(2)
                        ->required(),
                ]),
        ]);
}
```

---

## TokenStatsWidget

```php
class TokenStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // This requires aggregating from local RecurringSchedule data
        // since tokens are stored in CHIP
        
        $activeTokens = RecurringSchedule::query()
            ->where('status', 'active')
            ->distinct('recurring_token_id')
            ->count('recurring_token_id');
            
        $expiringSoon = RecurringSchedule::query()
            ->where('status', 'active')
            // Tokens expiring in next 30 days would need token expiry stored locally
            ->count();
            
        return [
            Stat::make('Active Tokens', $activeTokens)
                ->description('Tokens in use')
                ->icon(Heroicon::OutlinedCreditCard)
                ->color('success'),
                
            Stat::make('Expiring Soon', $expiringSoon)
                ->description('Within 30 days')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning'),
        ];
    }
}
```

---

## Implementation Challenges

### Token Aggregation

Tokens are stored per-client in CHIP. To list all tokens:

1. **Option A: On-Demand Fetch**
   - List all clients
   - For each client, fetch tokens
   - Aggregate and display
   - **Pro:** Always fresh
   - **Con:** Slow, many API calls

2. **Option B: Local Cache Table**
   - Create `chip_recurring_tokens` table
   - Sync on webhook events
   - Query local table
   - **Pro:** Fast queries
   - **Con:** May be stale

3. **Option C: Background Sync Job**
   - Scheduled job syncs all tokens
   - Store in local table
   - Query local for UI
   - **Pro:** Balance of fresh and fast
   - **Con:** Complexity

**Recommendation:** Option C with hourly sync for production.

---

## Success Criteria

- [ ] RecurringTokenResource lists all tokens
- [ ] Token details show card info and schedules
- [ ] Revoke token action works
- [ ] WebhookConfigResource for Collect webhooks
- [ ] SendWebhookConfigResource for Send webhooks
- [ ] Create/Edit/Delete webhook endpoints
- [ ] Test webhook action sends test payload
- [ ] TokenStatsWidget shows counts
- [ ] PHPStan Level 6 compliance

---

## Navigation

**Previous:** [07-financial-management.md](07-financial-management.md)  
**Next:** [09-operations-bulk-actions.md](09-operations-bulk-actions.md)

