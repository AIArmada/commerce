# Billing Templates

> **Document:** 03 of 10  
> **Package:** `aiarmada/chip`  
> **Status:** Vision

---

## Overview

Build a comprehensive **billing template system** that enables reusable invoice templates, payment link generation, custom branding, and dynamic field configurations.

---

## Template Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  BILLING TEMPLATE SYSTEM                     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌───────────────┐     ┌───────────────┐                    │
│  │   Template    │────►│   Instance    │                    │
│  │  Definition   │     │  (Payment)    │                    │
│  └───────────────┘     └───────────────┘                    │
│         │                     │                              │
│         ▼                     ▼                              │
│  ┌───────────────┐     ┌───────────────┐                    │
│  │ Custom Fields │     │ Payment Link  │                    │
│  │ & Branding    │     │ Generation    │                    │
│  └───────────────┘     └───────────────┘                    │
│                                                              │
│  Template Types:                                             │
│  • Invoice • Payment Link • Donation • Subscription Setup   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Template Models

### ChipBillingTemplate

```php
/**
 * Reusable billing template
 * 
 * @property string $id
 * @property string $name
 * @property string $type (invoice, payment_link, donation, subscription)
 * @property string|null $description
 * @property array $line_items
 * @property array $custom_fields
 * @property array $branding
 * @property array $settings
 * @property bool $is_active
 * @property int $times_used
 */
class ChipBillingTemplate extends Model
{
    use HasUuids;
    
    protected $casts = [
        'type' => BillingTemplateType::class,
        'line_items' => 'array',
        'custom_fields' => 'array',
        'branding' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];
    
    public function instances(): HasMany
    {
        return $this->hasMany(ChipBillingInstance::class, 'template_id');
    }
    
    public function createInstance(array $data = []): ChipBillingInstance
    {
        return app(BillingTemplateService::class)->createInstance($this, $data);
    }
}

enum BillingTemplateType: string
{
    case Invoice = 'invoice';
    case PaymentLink = 'payment_link';
    case Donation = 'donation';
    case SubscriptionSetup = 'subscription_setup';
}
```

### ChipBillingInstance

```php
/**
 * Instance created from a template
 * 
 * @property string $id
 * @property string $template_id
 * @property string|null $customer_email
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property array $line_items
 * @property array $custom_field_values
 * @property int $subtotal_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property string $currency
 * @property string $status
 * @property string|null $payment_link
 * @property string|null $chip_purchase_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 */
class ChipBillingInstance extends Model
{
    use HasUuids;
    
    protected $casts = [
        'line_items' => 'array',
        'custom_field_values' => 'array',
        'status' => BillingInstanceStatus::class,
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];
    
    public function template(): BelongsTo
    {
        return $this->belongsTo(ChipBillingTemplate::class, 'template_id');
    }
    
    public function getPaymentUrl(): string
    {
        return $this->payment_link 
            ?? route('chip.billing.pay', $this);
    }
    
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
    
    public function isPaid(): bool
    {
        return $this->status === BillingInstanceStatus::Paid;
    }
}

enum BillingInstanceStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
}
```

---

## Template Service

### BillingTemplateService

```php
class BillingTemplateService
{
    public function __construct(
        private ChipClient $chip,
        private TemplateRenderer $renderer,
    ) {}
    
    /**
     * Create a billing template
     */
    public function createTemplate(array $data): ChipBillingTemplate
    {
        $validated = $this->validateTemplateData($data);
        
        return ChipBillingTemplate::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'line_items' => $validated['line_items'] ?? [],
            'custom_fields' => $validated['custom_fields'] ?? [],
            'branding' => $validated['branding'] ?? [],
            'settings' => $this->mergeDefaultSettings($validated['settings'] ?? []),
            'is_active' => true,
        ]);
    }
    
    /**
     * Create an instance from a template
     */
    public function createInstance(
        ChipBillingTemplate $template,
        array $data = []
    ): ChipBillingInstance {
        $lineItems = $this->resolveLineItems($template, $data);
        $totals = $this->calculateTotals($lineItems, $data);
        
        $instance = ChipBillingInstance::create([
            'template_id' => $template->id,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'line_items' => $lineItems,
            'custom_field_values' => $data['custom_fields'] ?? [],
            'subtotal_minor' => $totals['subtotal'],
            'tax_minor' => $totals['tax'],
            'total_minor' => $totals['total'],
            'currency' => $data['currency'] ?? $template->settings['currency'] ?? 'MYR',
            'status' => BillingInstanceStatus::Pending,
            'expires_at' => $this->calculateExpiry($template),
        ]);
        
        // Generate payment link
        $instance->payment_link = $this->generatePaymentLink($instance);
        $instance->save();
        
        // Increment usage counter
        $template->increment('times_used');
        
        return $instance;
    }
    
    /**
     * Generate Chip payment link
     */
    private function generatePaymentLink(ChipBillingInstance $instance): string
    {
        $purchase = $this->chip->createPurchase([
            'brand_id' => config('chip.brand_id'),
            'client' => [
                'email' => $instance->customer_email,
                'full_name' => $instance->customer_name,
                'phone' => $instance->customer_phone,
            ],
            'purchase' => [
                'currency' => $instance->currency,
                'products' => array_map(fn ($item) => [
                    'name' => $item['description'],
                    'quantity' => $item['quantity'],
                    'price' => $item['unit_price_minor'],
                ], $instance->line_items),
            ],
            'success_redirect' => route('chip.billing.success', $instance),
            'failure_redirect' => route('chip.billing.failure', $instance),
            'success_callback' => route('chip.webhook'),
        ]);
        
        $instance->update(['chip_purchase_id' => $purchase['id']]);
        
        return $purchase['checkout_url'];
    }
}
```

---

## Custom Fields

### Custom Field Types

```php
enum CustomFieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Phone = 'phone';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case TextArea = 'textarea';
    case File = 'file';
}
```

### Custom Field Configuration

```php
// Example custom fields configuration
$customFields = [
    [
        'name' => 'company_name',
        'label' => 'Company Name',
        'type' => CustomFieldType::Text->value,
        'required' => true,
        'placeholder' => 'Enter company name',
    ],
    [
        'name' => 'tax_id',
        'label' => 'Tax ID / SST Number',
        'type' => CustomFieldType::Text->value,
        'required' => false,
        'pattern' => '^[A-Z0-9-]+$',
    ],
    [
        'name' => 'payment_terms',
        'label' => 'Payment Terms',
        'type' => CustomFieldType::Select->value,
        'required' => true,
        'options' => [
            ['value' => 'immediate', 'label' => 'Immediate'],
            ['value' => 'net_15', 'label' => 'Net 15'],
            ['value' => 'net_30', 'label' => 'Net 30'],
        ],
    ],
    [
        'name' => 'agree_terms',
        'label' => 'I agree to the terms and conditions',
        'type' => CustomFieldType::Checkbox->value,
        'required' => true,
    ],
];
```

---

## Branding Configuration

### BrandingConfig

```php
class BrandingConfig
{
    public static function schema(): array
    {
        return [
            'logo_url' => 'nullable|url',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'font_family' => 'nullable|string',
            'header_text' => 'nullable|string|max:255',
            'footer_text' => 'nullable|string|max:1000',
            'terms_url' => 'nullable|url',
            'privacy_url' => 'nullable|url',
            'custom_css' => 'nullable|string|max:5000',
        ];
    }
}

// Example branding configuration
$branding = [
    'logo_url' => 'https://example.com/logo.png',
    'primary_color' => '#1a56db',
    'secondary_color' => '#7c3aed',
    'font_family' => 'Inter, sans-serif',
    'header_text' => 'Thank you for your business!',
    'footer_text' => 'Payment is processed securely by Chip.',
    'terms_url' => 'https://example.com/terms',
    'privacy_url' => 'https://example.com/privacy',
];
```

---

## Template Renderer

### TemplateRenderer

```php
class TemplateRenderer
{
    public function render(ChipBillingInstance $instance): string
    {
        $template = $instance->template;
        
        return view('chip::billing.payment-page', [
            'instance' => $instance,
            'template' => $template,
            'branding' => $this->resolveBranding($template),
            'customFields' => $this->resolveCustomFields($template, $instance),
            'lineItems' => $instance->line_items,
            'totals' => [
                'subtotal' => $instance->subtotal_minor,
                'tax' => $instance->tax_minor,
                'total' => $instance->total_minor,
            ],
        ])->render();
    }
    
    public function renderPdf(ChipBillingInstance $instance): string
    {
        $html = $this->renderInvoice($instance);
        
        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->output();
    }
    
    private function renderInvoice(ChipBillingInstance $instance): string
    {
        return view('chip::billing.invoice-pdf', [
            'instance' => $instance,
            'template' => $instance->template,
            'branding' => $this->resolveBranding($instance->template),
        ])->render();
    }
}
```

---

## Quick Payment Links

### QuickLinkService

```php
class QuickLinkService
{
    public function __construct(
        private ChipClient $chip,
    ) {}
    
    /**
     * Generate a quick payment link without template
     */
    public function create(array $data): ChipQuickLink
    {
        $link = ChipQuickLink::create([
            'amount_minor' => $data['amount_minor'],
            'currency' => $data['currency'] ?? 'MYR',
            'description' => $data['description'],
            'customer_email' => $data['customer_email'] ?? null,
            'expires_at' => $data['expires_at'] ?? now()->addDays(7),
            'max_uses' => $data['max_uses'] ?? 1,
            'uses' => 0,
        ]);
        
        $link->short_url = $this->generateShortUrl($link);
        $link->save();
        
        return $link;
    }
    
    /**
     * Process payment for quick link
     */
    public function processPayment(ChipQuickLink $link, array $paymentData): ChipPurchase
    {
        if ($link->isExpired()) {
            throw new LinkExpiredException();
        }
        
        if ($link->max_uses && $link->uses >= $link->max_uses) {
            throw new LinkExhaustedException();
        }
        
        $purchase = $this->chip->createPurchase([
            'brand_id' => config('chip.brand_id'),
            'client' => [
                'email' => $paymentData['email'],
                'full_name' => $paymentData['name'],
            ],
            'purchase' => [
                'currency' => $link->currency,
                'products' => [
                    [
                        'name' => $link->description,
                        'quantity' => 1,
                        'price' => $link->amount_minor,
                    ],
                ],
            ],
        ]);
        
        $link->increment('uses');
        
        return $purchase;
    }
}
```

---

## Database Schema

```php
// chip_billing_templates table
Schema::create('chip_billing_templates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('type');
    $table->text('description')->nullable();
    $table->json('line_items');
    $table->json('custom_fields');
    $table->json('branding');
    $table->json('settings');
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('times_used')->default(0);
    $table->timestamps();
    
    $table->index(['type', 'is_active']);
});

// chip_billing_instances table
Schema::create('chip_billing_instances', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('template_id');
    $table->string('customer_email')->nullable();
    $table->string('customer_name')->nullable();
    $table->string('customer_phone')->nullable();
    $table->json('line_items');
    $table->json('custom_field_values');
    $table->bigInteger('subtotal_minor');
    $table->bigInteger('tax_minor')->default(0);
    $table->bigInteger('total_minor');
    $table->string('currency', 3);
    $table->string('status');
    $table->string('payment_link')->nullable();
    $table->string('chip_purchase_id')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
    
    $table->index(['template_id', 'status']);
    $table->index('chip_purchase_id');
});

// chip_quick_links table
Schema::create('chip_quick_links', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->bigInteger('amount_minor');
    $table->string('currency', 3);
    $table->string('description');
    $table->string('customer_email')->nullable();
    $table->string('short_url')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->integer('max_uses')->nullable();
    $table->integer('uses')->default(0);
    $table->timestamps();
    
    $table->index('short_url');
});
```

---

## Navigation

**Previous:** [02-subscription-management.md](02-subscription-management.md)  
**Next:** [04-dispute-management.md](04-dispute-management.md)
