<?php /** @var \Illuminate\View\ComponentAttributeBag $attributes */ ?>

## Spatie integration guidelines

Use Spatie packages deliberately and consistently across Commerce packages.
Prefer official Filament plugins for Spatie packages when Filament UI is involved.

### Decision table (what to use when)

#### Auditing vs activity logging (hybrid architecture)

- Use `owen-it/laravel-auditing` for compliance-grade audit trails on compliance-critical domains:
	- Orders, payments, customers, inventory adjustments and other regulated/forensic records.
	- Requirements usually include IP/UA/URL capture, state restoration, redaction, and pivot auditing.

- Use `spatie/laravel-activitylog` for business event logging and product analytics:
	- Cart actions, voucher usage, affiliate events, pricing changes, admin actions.
	- Prefer it when you need flexible “what happened” narratives, log categories, and batch grouping.

Rule of thumb:
- If the question is “who changed this model and what were old/new values for compliance?” → auditing.
- If the question is “what business event happened and why, across multiple models?” → activity log.

#### Webhooks

- Use `spatie/laravel-webhook-client` for all inbound webhooks (payments, shipping carriers, etc).
	- Do not implement bespoke webhook persistence/retry/signature validation if webhook-client can do it.
	- Implement provider-specific `SignatureValidator`, optional `WebhookProfile` for event filtering, and a single `ProcessWebhookJob` per provider.

#### State machines

- Use `spatie/laravel-model-states` when a domain has complex lifecycle transitions:
	- Orders, shipments, payouts, subscription/payment states.
	- Always enforce allowed transitions (never “set status string directly” in business logic).

#### API filtering/sorting

- Use `spatie/laravel-query-builder` for public/internal read APIs that require filtering/sorting/includes.
	- Only expose `allowedFilters`, `allowedSorts`, `allowedIncludes`, `allowedFields`.
	- Never accept arbitrary column filtering from user input.

#### Media

- Use `spatie/laravel-medialibrary` for product/customer media (images, PDFs, documents).
	- Keep conversions queued where appropriate; avoid large synchronous conversions.

#### Slugs

- Use `spatie/laravel-sluggable` for stable SEO slugs (products, categories) and optionally voucher-friendly codes.
	- For vouchers, prefer a purpose-built code generator when codes must be random/non-guessable.

#### Tags

- Use `spatie/laravel-tags` for flexible categorization and segmentation:
	- Products (attributes/labels), customers (segments/marketing cohorts), vouchers (campaign categorization).
	- Prefer typed tags (tag “types”) when tags mean different things (e.g. `colors`, `segments`).

#### Runtime settings

- Use `spatie/laravel-settings` for runtime configuration that business users change without deploys:
	- Pricing rules, tax defaults/zones, operational thresholds.
	- Settings changes should be logged (typically via activity log) unless compliance requires auditing.

#### Translations

- Use `spatie/laravel-translatable` for multi-language content models (product names/descriptions, segments).
	- Avoid rolling your own JSON translation structures.

#### Operational health

- Use `spatie/laravel-health` for operational monitoring and dependency checks (payment gateways, queues, storage).

### Implementation rules

#### Activity logging (`spatie/laravel-activitylog`)

- Prefer model-based logging when a model is the “subject” of the event.
- Prefer manual `activity()` logging when the event is cross-cutting (e.g., cart session actions).
- Log categories must be explicit (use log names) so consumers can filter by domain.
- Log payload must be minimal and safe:
	- Do not log secrets or full payloads containing sensitive data.
	- Use redaction/whitelisting strategies (log only what you need).

#### Auditing (`owen-it/laravel-auditing`)

- Only enable it for compliance-critical models.
- Use redaction/encoding for PII where applicable.
- Do not rely on database cascades/constraints for integrity (application-level behavior only).

#### Webhooks (`spatie/laravel-webhook-client`)

- Every provider integration must:
	- Validate signatures.
	- Persist webhook calls.
	- Process via a job that is idempotent.
	- Emit domain events rather than doing business logic in controllers.

### Filament rules

- If a Spatie package has an official Filament plugin (e.g., tags/settings/media library), use it.
- Do not build custom Filament integrations when an official plugin exists.

### Package matrix (default choices)

Use this as the default mapping unless a package has documented exceptions.

- `commerce-support`
	- DTOs: `spatie/laravel-data`
	- Activity logging primitives: `spatie/laravel-activitylog` (business events)
	- Compliance auditing primitives: `owen-it/laravel-auditing` (regulated domains)
	- Settings: `spatie/laravel-settings` (pricing/tax/ops settings) + log settings changes

- `cart`
	- Business events: `spatie/laravel-activitylog` (cart add/remove/update/abandon)

- `inventory`
	- Compliance auditing (critical): `owen-it/laravel-auditing` for inventory adjustments/movements when required
	- Business events: `spatie/laravel-activitylog` for operational analytics
	- Optional lifecycle: `spatie/laravel-model-states` for movement status

- `vouchers`
	- Business events: `spatie/laravel-activitylog` (redeem/apply/deny)
	- Categorization: `spatie/laravel-tags` (campaigns/segments)
	- Codes: prefer custom secure generator; `spatie/laravel-sluggable` only for human-friendly codes

- `products`
	- Media: `spatie/laravel-medialibrary`
	- Slugs: `spatie/laravel-sluggable`
	- Tags: `spatie/laravel-tags`
	- Translations: `spatie/laravel-translatable` (customer-facing content)
	- APIs: `spatie/laravel-query-builder` for catalog filtering/sorting

- `customers`
	- Compliance auditing (PII): `owen-it/laravel-auditing` for profile/PII changes where required
	- Business events: `spatie/laravel-activitylog` (logins, address changes, CRM events)
	- Segmentation: `spatie/laravel-tags`
	- Media (optional): `spatie/laravel-medialibrary` for avatars/documents

- `orders`
	- Compliance auditing (critical): `owen-it/laravel-auditing`
	- State machine (critical): `spatie/laravel-model-states`
	- Documents: `spatie/laravel-pdf` for invoices/packing slips
	- APIs: `spatie/laravel-query-builder` for listing/filtering

- `shipping` + carriers (e.g. `jnt`)
	- State machine: `spatie/laravel-model-states` for shipment lifecycle
	- Inbound webhooks: `spatie/laravel-webhook-client` (carrier status updates)
	- Business events: `spatie/laravel-activitylog` for operational visibility

- payments (`chip`, `cashier`, `cashier-chip`)
	- Inbound webhooks (critical): `spatie/laravel-webhook-client`
	- Compliance auditing: `owen-it/laravel-auditing` for payment/refund/subscription state changes where required
	- Business events: `spatie/laravel-activitylog` for customer support + analytics

- `affiliates`
	- Business events: `spatie/laravel-activitylog` (referrals, commissions, payouts)
	- Optional lifecycle: `spatie/laravel-model-states` for payout status

- `pricing` + `tax`
	- Runtime config (critical): `spatie/laravel-settings`
	- Business events: `spatie/laravel-activitylog` for rate/rule changes
	- APIs: `spatie/laravel-query-builder` where listing/filtering is needed

- Filament packages (e.g. `filament-products`, `filament-vouchers`)
	- Always prefer official Filament plugins for Spatie integrations (tags/settings/media library).

### Config rules

- Only add configuration keys that are referenced in code.
- Keep package configs minimal and ordered per the repo config guidelines.

