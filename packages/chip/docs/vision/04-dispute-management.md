# Dispute Management

> **Document:** 04 of 10  
> **Package:** `aiarmada/chip`  
> **Status:** Vision

---

## Overview

Build a comprehensive **dispute and chargeback management system** that handles the full lifecycle from detection to resolution, including evidence collection, automated workflows, and merchant protection.

---

## Dispute Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                   DISPUTE LIFECYCLE                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐        │
│  │ Opened  │─►│Evidence │─►│ Under   │─►│Resolved │        │
│  │         │  │Submitted│  │ Review  │  │         │        │
│  └─────────┘  └─────────┘  └─────────┘  └─────────┘        │
│                                              │               │
│                                              ▼               │
│                                  ┌─────────────────┐        │
│                                  │ Won / Lost /    │        │
│                                  │ Accepted        │        │
│                                  └─────────────────┘        │
│                                                              │
│  Response Deadline: 7-14 days (varies by card network)      │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Dispute Models

### ChipDispute

```php
/**
 * Payment dispute/chargeback record
 * 
 * @property string $id
 * @property string $chip_purchase_id
 * @property string|null $chip_dispute_id
 * @property string $reason_code
 * @property string $reason
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 * @property Carbon $opened_at
 * @property Carbon|null $evidence_due_at
 * @property Carbon|null $resolved_at
 * @property string|null $resolution
 * @property array|null $metadata
 */
class ChipDispute extends Model
{
    use HasUuids;
    
    protected $casts = [
        'status' => DisputeStatus::class,
        'opened_at' => 'datetime',
        'evidence_due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];
    
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ChipPurchase::class, 'chip_purchase_id', 'chip_id');
    }
    
    public function evidence(): HasMany
    {
        return $this->hasMany(ChipDisputeEvidence::class, 'dispute_id');
    }
    
    public function timeline(): HasMany
    {
        return $this->hasMany(ChipDisputeEvent::class, 'dispute_id')
            ->orderBy('occurred_at');
    }
    
    public function isOpen(): bool
    {
        return in_array($this->status, [
            DisputeStatus::Open,
            DisputeStatus::EvidenceRequired,
            DisputeStatus::UnderReview,
        ]);
    }
    
    public function isEvidenceOverdue(): bool
    {
        return $this->status === DisputeStatus::EvidenceRequired 
            && $this->evidence_due_at?->isPast();
    }
    
    public function getRemainingDays(): int
    {
        return max(0, now()->diffInDays($this->evidence_due_at, false));
    }
}

enum DisputeStatus: string
{
    case Open = 'open';
    case EvidenceRequired = 'evidence_required';
    case EvidenceSubmitted = 'evidence_submitted';
    case UnderReview = 'under_review';
    case Won = 'won';
    case Lost = 'lost';
    case Accepted = 'accepted';
    case Canceled = 'canceled';
}
```

### ChipDisputeEvidence

```php
/**
 * Evidence submitted for a dispute
 * 
 * @property string $id
 * @property string $dispute_id
 * @property string $type
 * @property string|null $file_path
 * @property string|null $text_content
 * @property array|null $metadata
 * @property Carbon $submitted_at
 */
class ChipDisputeEvidence extends Model
{
    use HasUuids;
    
    protected $casts = [
        'type' => EvidenceType::class,
        'metadata' => 'array',
        'submitted_at' => 'datetime',
    ];
    
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(ChipDispute::class, 'dispute_id');
    }
}

enum EvidenceType: string
{
    case Receipt = 'receipt';
    case Invoice = 'invoice';
    case ShippingDocumentation = 'shipping_documentation';
    case DeliveryConfirmation = 'delivery_confirmation';
    case CustomerCommunication = 'customer_communication';
    case ServiceAgreement = 'service_agreement';
    case RefundPolicy = 'refund_policy';
    case CustomerSignature = 'customer_signature';
    case AccessLog = 'access_log';
    case Other = 'other';
}
```

---

## Dispute Service

### ChipDisputeService

```php
class ChipDisputeService
{
    public function __construct(
        private ChipClient $chip,
        private DisputeNotifier $notifier,
        private EvidenceCollector $evidenceCollector,
    ) {}
    
    /**
     * Handle incoming dispute webhook
     */
    public function handleDisputeOpened(array $payload): ChipDispute
    {
        $dispute = ChipDispute::create([
            'chip_purchase_id' => $payload['purchase_id'],
            'chip_dispute_id' => $payload['dispute_id'],
            'reason_code' => $payload['reason_code'],
            'reason' => $this->resolveReasonDescription($payload['reason_code']),
            'amount_minor' => $payload['amount'],
            'currency' => $payload['currency'],
            'status' => DisputeStatus::Open,
            'opened_at' => Carbon::parse($payload['created_at']),
            'evidence_due_at' => Carbon::parse($payload['evidence_due_date']),
            'metadata' => $payload['metadata'] ?? null,
        ]);
        
        // Record timeline event
        $this->recordEvent($dispute, 'dispute_opened', $payload);
        
        // Auto-collect evidence
        $this->autoCollectEvidence($dispute);
        
        // Notify merchant
        $this->notifier->disputeOpened($dispute);
        
        event(new DisputeOpened($dispute));
        
        return $dispute;
    }
    
    /**
     * Submit evidence for a dispute
     */
    public function submitEvidence(
        ChipDispute $dispute,
        array $evidence
    ): ChipDispute {
        if (!$dispute->isOpen()) {
            throw new DisputeClosedException();
        }
        
        $evidencePayload = [];
        
        foreach ($evidence as $item) {
            $evidenceRecord = ChipDisputeEvidence::create([
                'dispute_id' => $dispute->id,
                'type' => $item['type'],
                'file_path' => $item['file_path'] ?? null,
                'text_content' => $item['text_content'] ?? null,
                'submitted_at' => now(),
            ]);
            
            $evidencePayload[] = $this->formatEvidenceForChip($evidenceRecord);
        }
        
        // Submit to Chip API
        $this->chip->submitDisputeEvidence(
            $dispute->chip_dispute_id,
            $evidencePayload
        );
        
        $dispute->update(['status' => DisputeStatus::EvidenceSubmitted]);
        
        $this->recordEvent($dispute, 'evidence_submitted', [
            'evidence_count' => count($evidence),
        ]);
        
        event(new DisputeEvidenceSubmitted($dispute));
        
        return $dispute;
    }
    
    /**
     * Accept dispute (refund customer, end dispute)
     */
    public function acceptDispute(ChipDispute $dispute, ?string $reason = null): ChipDispute
    {
        $this->chip->acceptDispute($dispute->chip_dispute_id);
        
        $dispute->update([
            'status' => DisputeStatus::Accepted,
            'resolved_at' => now(),
            'resolution' => 'accepted',
            'metadata' => array_merge($dispute->metadata ?? [], [
                'acceptance_reason' => $reason,
            ]),
        ]);
        
        $this->recordEvent($dispute, 'dispute_accepted', ['reason' => $reason]);
        
        event(new DisputeResolved($dispute, 'accepted'));
        
        return $dispute;
    }
    
    /**
     * Auto-collect available evidence
     */
    private function autoCollectEvidence(ChipDispute $dispute): void
    {
        $purchase = $dispute->purchase;
        
        $evidence = $this->evidenceCollector->collect($purchase);
        
        if (!empty($evidence)) {
            foreach ($evidence as $item) {
                ChipDisputeEvidence::create([
                    'dispute_id' => $dispute->id,
                    'type' => $item['type'],
                    'file_path' => $item['file_path'] ?? null,
                    'text_content' => $item['text_content'] ?? null,
                    'metadata' => $item['metadata'] ?? null,
                    'submitted_at' => now(),
                ]);
            }
            
            $dispute->update(['status' => DisputeStatus::EvidenceRequired]);
        }
    }
}
```

---

## Evidence Collector

### EvidenceCollector

```php
class EvidenceCollector
{
    private array $collectors = [];
    
    public function __construct()
    {
        $this->collectors = [
            new ReceiptEvidenceCollector(),
            new ShippingEvidenceCollector(),
            new CommunicationEvidenceCollector(),
            new AccessLogEvidenceCollector(),
        ];
    }
    
    public function collect(ChipPurchase $purchase): array
    {
        $evidence = [];
        
        foreach ($this->collectors as $collector) {
            $collected = $collector->collect($purchase);
            
            if ($collected) {
                $evidence = array_merge($evidence, $collected);
            }
        }
        
        return $evidence;
    }
}

class ReceiptEvidenceCollector implements EvidenceCollectorInterface
{
    public function collect(ChipPurchase $purchase): ?array
    {
        // Generate receipt PDF
        $receiptPath = $this->generateReceipt($purchase);
        
        return [
            [
                'type' => EvidenceType::Receipt,
                'file_path' => $receiptPath,
                'metadata' => [
                    'generated_at' => now()->toIso8601String(),
                    'purchase_id' => $purchase->chip_id,
                ],
            ],
        ];
    }
}

class ShippingEvidenceCollector implements EvidenceCollectorInterface
{
    public function collect(ChipPurchase $purchase): ?array
    {
        // Check if order exists and has shipment
        $order = $purchase->order;
        
        if (!$order || !$order->shipment) {
            return null;
        }
        
        $evidence = [];
        
        // Add shipping documentation
        if ($order->shipment->tracking_number) {
            $evidence[] = [
                'type' => EvidenceType::ShippingDocumentation,
                'text_content' => $this->formatShippingInfo($order->shipment),
            ];
        }
        
        // Add delivery confirmation
        if ($order->shipment->delivered_at) {
            $evidence[] = [
                'type' => EvidenceType::DeliveryConfirmation,
                'text_content' => $this->formatDeliveryConfirmation($order->shipment),
                'file_path' => $order->shipment->proof_of_delivery_path,
            ];
        }
        
        return $evidence;
    }
}
```

---

## Dispute Reason Codes

### DisputeReasonResolver

```php
class DisputeReasonResolver
{
    private array $reasonCodes = [
        // Visa reason codes
        '10.1' => 'EMV Liability Shift Counterfeit Fraud',
        '10.2' => 'EMV Liability Shift Non-Counterfeit Fraud',
        '10.3' => 'Other Fraud - Card Present',
        '10.4' => 'Other Fraud - Card Not Present',
        '10.5' => 'Visa Fraud Monitoring Program',
        '11.1' => 'Card Recovery Bulletin',
        '11.2' => 'Declined Authorization',
        '11.3' => 'No Authorization',
        '12.1' => 'Late Presentment',
        '12.2' => 'Incorrect Transaction Code',
        '12.3' => 'Incorrect Currency',
        '12.4' => 'Incorrect Account Number',
        '12.5' => 'Incorrect Amount',
        '12.6' => 'Duplicate Processing',
        '12.7' => 'Invalid Data',
        '13.1' => 'Merchandise/Services Not Received',
        '13.2' => 'Cancelled Recurring Transaction',
        '13.3' => 'Not as Described or Defective',
        '13.4' => 'Counterfeit Merchandise',
        '13.5' => 'Misrepresentation',
        '13.6' => 'Credit Not Processed',
        '13.7' => 'Cancelled Merchandise/Services',
        '13.8' => 'Original Credit Transaction Not Accepted',
        '13.9' => 'Non-Receipt of Cash from ATM',
        
        // Mastercard reason codes
        '4837' => 'No Cardholder Authorization',
        '4840' => 'Fraudulent Transaction - Card Not Present',
        '4841' => 'Cancelled Recurring Transaction',
        '4853' => 'Cardholder Dispute - Defective/Not as Described',
        '4854' => 'Cardholder Dispute - Not Elsewhere Classified',
        '4855' => 'Goods or Services Not Provided',
        '4863' => 'Cardholder Does Not Recognize Transaction',
    ];
    
    public function resolve(string $code): string
    {
        return $this->reasonCodes[$code] ?? 'Unknown Reason';
    }
    
    public function getSuggestedEvidence(string $code): array
    {
        return match ($code) {
            '13.1', '4855' => [
                EvidenceType::ShippingDocumentation,
                EvidenceType::DeliveryConfirmation,
                EvidenceType::CustomerSignature,
            ],
            '13.2', '4841' => [
                EvidenceType::ServiceAgreement,
                EvidenceType::CustomerCommunication,
                EvidenceType::RefundPolicy,
            ],
            '13.3', '4853' => [
                EvidenceType::Receipt,
                EvidenceType::CustomerCommunication,
                EvidenceType::RefundPolicy,
            ],
            default => [
                EvidenceType::Receipt,
                EvidenceType::CustomerCommunication,
            ],
        };
    }
}
```

---

## Dispute Notifications

### DisputeNotifier

```php
class DisputeNotifier
{
    public function disputeOpened(ChipDispute $dispute): void
    {
        $merchant = $this->getMerchantForDispute($dispute);
        
        // Send urgent notification
        Notification::make()
            ->title('New Dispute Opened')
            ->body("A dispute for {$dispute->formatted_amount} requires your attention. Evidence is due in {$dispute->getRemainingDays()} days.")
            ->actions([
                NotificationAction::make('view')
                    ->url(DisputeResource::getUrl('view', ['record' => $dispute])),
            ])
            ->sendToDatabase($merchant);
        
        // Send email
        Mail::to($merchant->email)->send(new DisputeOpenedMail($dispute));
        
        // SMS for urgent disputes
        if ($dispute->amount_minor >= config('chip.disputes.sms_threshold')) {
            $this->sendSms($merchant->phone, $dispute);
        }
    }
    
    public function evidenceDeadlineApproaching(ChipDispute $dispute): void
    {
        $remainingDays = $dispute->getRemainingDays();
        
        if ($remainingDays <= 3) {
            $this->sendUrgentReminder($dispute);
        }
    }
}
```

---

## Dispute Analytics

### DisputeAnalytics

```php
class DisputeAnalytics
{
    public function getDashboardMetrics(): array
    {
        return [
            'open_disputes' => ChipDispute::whereIn('status', [
                DisputeStatus::Open,
                DisputeStatus::EvidenceRequired,
                DisputeStatus::UnderReview,
            ])->count(),
            
            'disputes_this_month' => ChipDispute::query()
                ->whereMonth('opened_at', now()->month)
                ->count(),
            
            'total_at_risk' => ChipDispute::query()
                ->whereIn('status', [
                    DisputeStatus::Open,
                    DisputeStatus::EvidenceRequired,
                ])
                ->sum('amount_minor'),
            
            'win_rate' => $this->calculateWinRate(),
            
            'avg_response_time' => $this->calculateAverageResponseTime(),
            
            'by_reason' => $this->getDisputesByReason(),
        ];
    }
    
    public function calculateWinRate(): float
    {
        $resolved = ChipDispute::query()
            ->whereIn('status', [
                DisputeStatus::Won,
                DisputeStatus::Lost,
            ])
            ->get();
        
        if ($resolved->isEmpty()) {
            return 0;
        }
        
        $won = $resolved->where('status', DisputeStatus::Won)->count();
        
        return round($won / $resolved->count() * 100, 2);
    }
    
    public function getDisputesByReason(): array
    {
        return ChipDispute::query()
            ->selectRaw('reason_code, COUNT(*) as count, SUM(amount_minor) as total_amount')
            ->groupBy('reason_code')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'reason_code' => $row->reason_code,
                'description' => app(DisputeReasonResolver::class)->resolve($row->reason_code),
                'count' => $row->count,
                'total_amount' => $row->total_amount,
            ])
            ->toArray();
    }
}
```

---

## Database Schema

```php
// chip_disputes table
Schema::create('chip_disputes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('chip_purchase_id');
    $table->string('chip_dispute_id')->nullable()->unique();
    $table->string('reason_code');
    $table->string('reason');
    $table->bigInteger('amount_minor');
    $table->string('currency', 3);
    $table->string('status');
    $table->timestamp('opened_at');
    $table->timestamp('evidence_due_at')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->string('resolution')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index(['status', 'evidence_due_at']);
    $table->index('chip_purchase_id');
});

// chip_dispute_evidence table
Schema::create('chip_dispute_evidence', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('dispute_id');
    $table->string('type');
    $table->string('file_path')->nullable();
    $table->text('text_content')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamp('submitted_at');
    $table->timestamps();
    
    $table->index('dispute_id');
});

// chip_dispute_events table (timeline)
Schema::create('chip_dispute_events', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('dispute_id');
    $table->string('event_type');
    $table->json('payload');
    $table->timestamp('occurred_at');
    $table->timestamps();
    
    $table->index(['dispute_id', 'occurred_at']);
});
```

---

## Navigation

**Previous:** [03-billing-templates.md](03-billing-templates.md)  
**Next:** [05-analytics-insights.md](05-analytics-insights.md)
