# 10 — Testing and Acceptance Criteria

## Migration acceptance

- [x] Every table uses UUID primary id.
- [x] No table has `deleted_at`.
- [x] No migration uses `softDeletes()`.
- [x] No migration uses `foreign()`.
- [x] No migration uses `constrained()`.
- [x] No migration uses cascading deletes.
- [x] Every mutable table has `timestampsTz()`.
- [x] Every lifecycle column uses `timestampTz()`.

## Follow tests

- [ ] User can follow any Followable model. (implementation exists, no tests)
- [ ] User can unfollow without row deletion. (implementation exists, no tests)
- [ ] User can mute/unmute follow. (implementation exists, no tests)
- [x] Duplicate active follows are prevented. (verified in code)
- [x] FollowCreated/FollowRemoved/FollowMuted events are dispatched. (verified in code)

## Bookmark tests

- [ ] User can bookmark any Bookmarkable model. (implementation exists, no tests)
- [ ] User can remove bookmark without row deletion. (implementation exists, no tests)
- [ ] User can archive bookmark. (archiveBookmark in EngagementManager)
- [ ] User can add bookmark to collection. (implemented in EngagementManager, no test written)
- [ ] One bookmark can appear in multiple collections. (addBookmarkToCollection + removeBookmarkFromCollection exist, no test written)

## Response tests

- [ ] User can respond to any Respondable model. (implementation exists, no tests)
- [ ] Allowed response types are validated. (policy consults Respondable)
- [x] Response can be changed. (verified in code)
- [x] Response can be cancelled. (verified in code)
- [x] `going` response does not create event registration. (verified in code - no cross-package mutation)
- [x] ResponseCreated/ResponseChanged/ResponseCancelled events are dispatched. (verified in code)

## Reaction tests

- [ ] User can react to any Reactable model. (implementation exists, no tests)
- [ ] Allowed reaction types are validated. (policy consults Reactable)
- [ ] Reaction multiplicity follows subject policy. (policy consults Reactable)
- [x] Removing reaction sets `removed_at`. (verified in code)

## Subscription tests

- [ ] User can subscribe to concrete subject. (implementation exists, no tests)
- [ ] User can subscribe to rule-based criteria. (implementation exists, no tests)
- [ ] User can unsubscribe without row deletion. (implementation exists, no tests)
- [ ] Subscription matching works for live online event criteria. (implementation exists, no tests)
- [x] Subscription matching dispatches SubscriptionMatched. (verified in code)

## Reminder tests

- [ ] User can set reminder for Remindable model. (implementation exists, no tests)
- [ ] Reminder can use exact `remind_at`. (schema supports it, no tests)
- [ ] Reminder can use offset from anchor. (schema supports it, no tests)
- [ ] Due reminder command finds due reminders. (command exists, no tests)
- [x] ReminderDue is dispatched. (verified in code)
- [x] Sent reminders set `sent_at`. (schema/method support it)
- [x] Failed reminders set `failed_at` and `failure_reason`. (schema/method support it)
- [ ] Reminder command is idempotent. (not verified)

## Events integration tests

- [x] Events package boots without Interactions package. (Null adapter is default)
- [x] Events package uses NullEventEngagementManager when Engagement absent. (provider binding)
- [x] Events package binds real adapter when Engagement installed. (provider class_exists check)
- [ ] EventOccurrence can be responded to through Interactions. (adapter exists, no tests)
- [ ] EventOccurrence can be followed/bookmarked through Interactions. (adapter exists, no tests)
- [ ] EventOccurrencePublished can trigger subscription matching. (no cross-package listener yet)
- [ ] EventStartingSoon can trigger reminder logic. (no cross-package listener yet)

## Analytics boundary tests

- [x] No interaction analytics log tables are created. (verified by lint)
- [x] Views/clicks/opens are not stored in Interactions. (verified by audit)
- [x] Interaction events can be listened to by Analytics package. (events exist)

## Notification boundary tests

- [x] No notification delivery tables are created. (verified by lint)
- [x] Laravel Notifications are used or can be overridden. (config-driven channels)
- [ ] Reminder delivery can be handled by host app listener. (ReminderDue event exists, but no example listener)

## Filament acceptance

- [ ] All resources load. (not started)
- [ ] Filters work. (not started)
- [ ] Actions call services. (not started)
- [ ] Relation managers can attach to external resources. (not started)
- [x] Filament features can be disabled from config. (config exists)

## Domain neutrality acceptance

- [x] Package contains no references to Masjid, Ustaz, Kitab, Speaker-specific implementation, Event-specific table ownership, Product, Order, Payment.
- [x] All domain integration is through contracts, morphs, adapters, or listeners.

## Final acceptance statement

The Interactions package is accepted when it can store user intent toward arbitrary models, integrate with Events without owning event truth, use Laravel Notifications for reminder delivery, avoid analytics responsibilities, and remain fully functional as an isolated package.
