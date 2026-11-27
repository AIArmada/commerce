---
name: communications-development
description: Develop and integrate Communications with Laravel Dune ownership, security and canonical APIs.
---

# Communications development

- Use owner-scoped communications and delivery lifecycle actions; use `AIArmada\Communications\Contracts\CommunicationManager` for managed notifications. Never construct a second notification delivery pipeline.
- Aggregate owns message creation, recipients, references, threads, content and cancellation. Delivery owns attempts, eligibility, provider events and delivery status; use `Features\Delivery\Actions\ApplyProviderEventAction` for provider events.
- Inbox, Suppressions, Templates and Tracking own their respective mutations. Use `Features\Suppressions\Actions\CreateSuppressionAction` and `LiftSuppressionAction`; use `Features\Tracking\Actions\CreateTrackingTokenAction` for tracked links.
- HTTP, queue, CLI and listener adapters are under `EntryPoints/` or the owning feature. Verify webhook signatures over timestamp plus raw request body, enforce the replay window and preserve owner context. Never log raw tokens or decrypted destinations.
