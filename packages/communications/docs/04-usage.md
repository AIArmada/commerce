---
title: Usage
---

# Usage

## Basic usage

### Recording a notification (native mode)

The package automatically observes `NotificationSending` and `NotificationSent` events when `native_capture` is enabled. No code changes needed.

### Managed notification

```php
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Facades\Communications;

$context = CommunicationContextData::from([
    'purpose' => 'invoice-paid',
    'category' => 'transactional',
    'subjectType' => $invoice::class,
    'subjectId' => (string) $invoice->getKey(),
]);

Communications::notify($user, new InvoicePaid($invoice), $context);
```

### Using the context trait on notifications

```php
use AIArmada\Communications\Notifications\BaseCommunicationNotification;

class InvoicePaid extends BaseCommunicationNotification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invoice Paid')
            ->line('Your invoice has been paid.');
    }
}
```

## Working with templates

```php
use AIArmada\Communications\Actions\CreateCommunicationAction;

$communication = app(CreateCommunicationAction::class)->handle(
    $contextData,
);
```

## Console commands

```bash
# Dispatch scheduled communications
php artisan communications:dispatch-due

# Prune old communication data
php artisan communications:prune

# Expire communications past deadline
php artisan communications:expire

# Reconcile aggregate statuses
php artisan communications:reconcile
```

## Owner scoping

All tenant-owned queries are automatically scoped to the current owner when `communications.features.owner.enabled` is true. Use `OwnerContext::withOwner()` for scoped operations.
