---
title: Document Status
---

# Document Status

## Common model helpers

```php
$document->markAsPaid();
$document->markAsPaid('Payment received via bank transfer');

$document->markAsSent();
$document->markAsSent('Emailed to customer');

$document->cancel();
$document->cancel('Customer requested cancellation');
```

## Updating Status

Use the service for full tracking:

```php
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\States\Paid;

app(DocService::class)->updateStatus(
    $document, 
    Paid::class,
    'Payment received via bank transfer'
);
```

## Checking Status

```php
use AIArmada\Docs\States\Cancelled;
use AIArmada\Docs\States\Paid;

// Check current status
if ($document->status->equals(Paid::class)) {
    // Document is paid
}

// Check if document can be paid
if ($document->canBePaid()) {
    // Show payment options
}

// Check if overdue
if ($document->isOverdue()) {
    // Send reminder
}

// Get status label
echo $document->status->label();  // "Paid", "Pending", etc.
```

## Status History

View the complete history of status changes:

```php
$history = $document->statusHistories()
    ->orderBy('created_at', 'desc')
    ->get();

foreach ($history as $entry) {
    echo $entry->status->label();      // Status
    echo $entry->notes;                 // Change notes
    echo $entry->created_at->format('Y-m-d H:i');
}
```

## Automatic Overdue Detection

```php
// Update status if document is past due
$document->updateStatus();

// Query overdue documents
$overdue = Doc::where('due_date', '<', now())
    ->whereNotIn('status', [Paid::class, Cancelled::class])
    ->get();

foreach ($overdue as $doc) {
    $doc->updateStatus();
}
```
