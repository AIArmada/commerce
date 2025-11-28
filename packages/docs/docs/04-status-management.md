# Document Status

## Available Statuses

```php
use AIArmada\Docs\Enums\DocStatus;

DocStatus::DRAFT           // Initial state
DocStatus::PENDING         // Awaiting approval or action
DocStatus::SENT            // Delivered to customer
DocStatus::PAID            // Payment received
DocStatus::PARTIALLY_PAID  // Partial payment received
DocStatus::OVERDUE         // Past due date
DocStatus::CANCELLED       // Cancelled
DocStatus::REFUNDED        // Payment refunded
```

## Updating Status

Use the service for full tracking:

```php
$docService->updateDocStatus(
    $document, 
    DocStatus::PAID, 
    'Payment received via bank transfer'
);
```

## Model Convenience Methods

Quick status updates with automatic history tracking:

```php
// Mark as paid
$document->markAsPaid();
$document->markAsPaid('Payment received via PayPal');

// Mark as sent
$document->markAsSent();
$document->markAsSent('Emailed to customer');

// Cancel document
$document->cancel();
$document->cancel('Customer requested cancellation');
```

## Checking Status

```php
// Check current status
if ($document->status === DocStatus::PAID) {
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
    ->whereNotIn('status', [DocStatus::PAID, DocStatus::CANCELLED])
    ->get();

foreach ($overdue as $doc) {
    $doc->update(['status' => DocStatus::OVERDUE]);
}
```
