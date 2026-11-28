---
name: cashier-development
description: Develop integrations with cashier using its Dune package boundaries.
---

# cashier development

Cashier is the unified billing abstraction for Stripe and CHIP. Resolve gateways through the Cashier facade and GatewayContract; concrete SDK adapters live under Drivers. Use Actions for billing operations, Queries for subscription reads, and EntryPoints for CLI commands. Scope billable records to the current owner before payment or subscription changes. Never trust unsigned HTTP webhook payloads; replay only gateway-fetched events.

Read the package documentation and tests for current APIs. Verify changed behavior using the monorepo Pest suite and the package architecture checks.
