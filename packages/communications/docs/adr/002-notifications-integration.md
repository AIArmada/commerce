# ADR 002: Laravel Notifications Managed/Native Integration

**Status:** Accepted

**Context:** The package needs to record communication facts for notifications sent through Laravel's Notification system. Existing application code uses `$user->notify($notification)` and must keep working without changes. Two modes are needed: a lightweight observation mode for existing notifications, and a managed mode that creates the full communication aggregate before dispatching.

**Decision:**

1. **Native observation mode** — listeners hook into `Illuminate\Notifications\Events\NotificationSending` and `NotificationSent` to record whatever facts are observable. When `features.auto_capture` is enabled and no `communicationId` exists on the notification, the listener auto-creates a basic `Communication` + `CommunicationRecipient` + `CommunicationContent` + `CommunicationDelivery` aggregate. This requires zero code changes from the developer.
2. **Managed mode** — the `Communications::notify()` facade first creates the full communication aggregate (communication, recipients, content, planned deliveries), attaches a `HasCommunicationContext` trait carrying `communicationId` and `deliveryIdsByChannel`, then dispatches through Laravel's notification API. The same event listeners resolve the exact delivery from the context rather than creating a new aggregate.
3. **Auto-capture guards** — allowlist (only specified notification classes), denylist (skip specified classes), ignored channels, and a recursion guard (tracks nesting via `AutoCaptureState.recursionDepth`) prevent infinite loops and unwanted capture.
4. **Destination resolution** — uses `routeNotificationFor()` with fallback to `routeNotificationFor{Driver}` convention through `NotifiableDestinationResolver`, then encrypts/hashes/hints via `DestinationProtectorService`.

**Consequences:** Existing application code is untouched. The two modes coexist — managed takes precedence when `communicationId` is present, auto-capture creates records for any notification without context. The `HasCommunicationContext` trait must remain serialization-safe (scalar identifiers only, no service objects or models).
