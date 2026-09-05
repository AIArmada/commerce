---
title: Commerce Package Picker
status: current
kind: package-picker
---

# Commerce Package Picker

60-second rule: scan the 1-line column, match your keywords, then open only that package CONTEXT.md + overview. Do not deep-dive more than two packages without a reason.

Machine index: `docs/ai/package-index.json` (grep keywords/triggers). Full manifest: `docs/ai/package-manifests.json`.

## Foundation — shared, identity, tenancy

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `addressing` | Reusable address domain: polymorphic addresses, country/area/state/city/postcode reference data, snapshots, formatting, and import pipeline. | address, addresses, country, state, city, postcode | `filament-addressing` |
| `authz` | Framework-agnostic Spatie Permission core: UUID schema, scopes, wildcard permissions, impersonation services. Role/Permission models live in commerce-support. | roles, permissions, wildcard, scopes, impersonation, spatie | `filament-authz` |
| `commerce-support` | Shared foundation: owner scoping, contracts, targeting engine, webhooks, health, money, reference data. | owner-scope, contracts, targeting, webhooks, health, money | `filament-commerce-support` |
| `contacting` | Polymorphic contact methods (email/phone/WhatsApp) + social profiles with normalization and snapshots. | contact, phone, email, whatsapp, social-profile, normalization | `filament-contacting` |
| `filament-addressing` | Filament v5 admin for addressing: countries/states/cities/areas/postcodes, import/export, schemas. | filament, admin, address-ui, import-export | `addressing` |
| `filament-authz` | Filament v5 roles/permissions/users UI, discovery, impersonation. (Permission math lives in authz core.) | filament, roles-ui, impersonation, discovery | `authz` |
| `filament-commerce-support` | Filament navigation manager + reference-data UI (currencies/languages/timezones read-only). | filament, navigation, overrides, reference-data | `commerce-support` |
| `filament-contacting` | Filament adapter for contact methods/social/snapshots; relation-managers-first, resources off by default. | filament, contacts-ui, relation-manager | `contacting` |
| `filament-organizations` | Filament admin for organizations: org resource + member/invitation managers + lifecycle actions. | filament, organizations-ui, members, invitations | `organizations` |
| `membership` | Polymorphic membership: applications, invitations, member pivots, Spatie role sync for any subject. | membership, invitation, application, member-role | `—` |
| `organizations` | Reusable organization aggregate: identity, lifecycle, visibility, ownership invariants, current-org context. | organization, tenant, ownership, transfer, current-org | `filament-organizations` |

## Communications

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `communications` | Comms records: outbound/inbound/inbox, deliveries, templates, preferences, suppressions, batches. | email, sms, notification, inbox, template, suppression | `filament-communications` |
| `filament-communications` | Read-focused ops UI for messages, deliveries, threads, templates, preferences, suppressions, batches. | filament, comms-inbox, deliveries-ui | `communications` |

## Governance and safety

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `moderation` | Blocking, bans, and moderation-action log (polymorphic). | block, ban, moderation, report | `—` |

## Knowledge and references

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `references` | Bibliographic references: sources, slugs, parent/child hierarchy, structured parts, media covers. | reference, citation, bibliography, slug | `—` |

## Catalog and identity

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `customers` | Customer identity/CRM: profiles, addresses, segments, groups, notes. | customer, crm, segment, profile | `filament-customers` |
| `filament-customers` | Filament admin for customers/segments incl. merge, rebuild, validation pages. | filament, customers-ui, merge, segments | `customers` |
| `filament-inventory` | Filament inventory ops: locations/levels/movements/allocations/batches/serials + transfer/receive/ship actions. | filament, stock-ui, transfer, cycle-count | `inventory` |
| `filament-persons` | Filament v5 admin for persons/titles/issuers/credential definitions. | filament, persons-ui, titles | `persons` |
| `filament-pricing` | Filament pricing admin: price lists, simulator, settings pages. | filament, pricing-ui, simulator | `pricing` |
| `filament-products` | Filament catalog admin: products/categories/collections/attributes. | filament, catalog-ui, variants | `products` |
| `filament-tax` | Filament tax config UI: zones/classes/rates/exemptions/certificates/settings. | filament, tax-ui, exemptions | `tax` |
| `filament-ticketing` | Filament ticketing admin: types/passes/holders/transfers + ticketable registry. | filament, tickets-ui, passes | `ticketing` |
| `inventory` | Multi-location stock: levels, movements, allocations, batches/serials, costing (FIFO/WA/standard), replenishment. | stock, warehouse, allocation, fifo, replenishment, reservation | `filament-inventory` |
| `persons` | Normalized person identity: canonical persons + multi-context names, titles, credentials, affiliations (polymorphic). | person, identity, title, credential, affiliation, names | `filament-persons` |
| `pricing` | Price lists, tiers, priority price-resolution engine. | price, price-list, tier, resolution | `filament-pricing` |
| `products` | Catalog/PIM source of truth: products, variants, taxonomy, attributes. | product, variant, category, collection, attribute | `filament-products` |
| `tax` | Zone-based tax calculation + exemption request/approve workflow. | tax, zone, rate, exemption, calculation | `filament-tax` |
| `ticketing` | Polymorphic ticket types, passes, transfers, bundle products for any ticketable. | ticket, pass, transfer, bundle | `filament-ticketing` |

## Growth and incentives

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `affiliate-network` | Multi-merchant affiliate marketplace: sites, offers, applications, tracking links on top of affiliates. | marketplace, offers, sites, merchant-network, applications, tracking-links | `filament-affiliate-network` |
| `affiliates` | Affiliate attribution, programs/tiers, commissions, payouts, fraud signals, and analytics. | affiliate, commission, payout, attribution, referral, fraud | `filament-affiliates` |
| `engagement` | Polymorphic engagement: follows, bookmarks, likes/reactions, subscriptions, reminders, shares, counters. | follow, bookmark, like, reaction, subscribe, reminder | `filament-engagement` |
| `filament-affiliate-network` | Filament marketplace/admin for affiliate-network: sites, offers, applications. | filament, marketplace-admin, offers-ui | `affiliate-network` |
| `filament-affiliates` | Filament admin + affiliate self-service portal for affiliates. | filament, portal, payout-queue, fraud-review | `affiliates` |
| `filament-engagement` | Filament admin for follows/bookmarks/RSVPs/reactions/subs/reminders + actions. | filament, engagement-ui | `engagement` |
| `filament-growth` | Filament admin for experiments/variants, dashboards, results. | filament, experiments-ui, ab-test | `growth` |
| `filament-promotions` | Filament admin for promotions + issue-vouchers actions. | filament, promotions-ui | `promotions` |
| `filament-vouchers` | Filament admin for vouchers/usage/wallets + stacking/targeting pages. | filament, vouchers-ui, wallets, stacking | `vouchers` |
| `growth` | Revenue experimentation: experiments/variants, sticky assignments, presets, winner metrics on Signals. | experiment, ab-test, variant, assignment, preset | `filament-growth` |
| `promotions` | Automatic and code-based discount campaigns with targeting evaluation. | promotion, discount, campaign, targeting | `filament-promotions` |
| `vouchers` | Voucher issuance, cart-condition redemption, wallets, stacking, usage tracking. | voucher, coupon, wallet, redemption, stacking | `filament-vouchers` |

## Checkout flow

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `cart` | Cart persistence: items, conditions, metadata, migration on login, owner-aware storage. | cart, basket, cart-items, conditions, abandonment, migration | `filament-cart` |
| `checkout` | Checkout orchestration: session → validate → price/tax → pay → create order → reserve stock → complete. | checkout, session, steps, payment-resolver, orchestration | `—` |
| `filament-cart` | Filament admin for carts: snapshots, conditions, live monitoring, abandonment. | filament, cart-admin, abandonment, monitoring | `cart` |
| `filament-jnt` | Filament admin for J&T orders, tracking events, webhook logs, sync/print actions. | filament, jnt-ui, waybill, tracking | `jnt` |
| `filament-orders` | Filament admin for orders: timelines, fulfillment page, invoice downloads. | filament, orders-ui, timeline, fulfillment | `orders` |
| `filament-shipping` | Filament admin for shipments, zones, RMAs, fulfilment queue, manifests. | filament, shipments-ui, fulfilment, rma | `shipping` |
| `jnt` | J&T Express MY carrier adapter: orders, waybills, tracking, webhooks on top of shipping. | jnt, jt-express, waybill, tracking, carrier | `filament-jnt` |
| `orders` | Order records, payments/refunds, notes, invoices, 13-state machine. | order, refund, payment, invoice, state-machine | `filament-orders` |
| `shipping` | Carrier-agnostic shipping: shipments, zones, rates, labels, tracking, returns (Manager+drivers). | shipping, shipment, carrier, zone, rate, label | `filament-shipping` |

## Payments and documents

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `cashier` | Unified multi-gateway billing abstraction over Stripe (laravel/cashier) and CHIP-style providers. | billing, subscription, invoice, stripe, multi-gateway, billable | `filament-cashier` |
| `cashier-chip` | Cashier-style recurring billing on CHIP with app-managed renewals, billables, invoices. | chip-billing, recurring, subscription, renewal, billable, webhook | `filament-cashier-chip` |
| `chip` | Direct CHIP gateway: Collect payments + Send payouts, webhooks, local payment data. | chip, collect, send, payout, purchase, webhook | `filament-chip` |
| `docs` | Business documents: numbering, PDF render, email, approvals, versions, e-invoice tracking. | document, invoice-pdf, numbering, sequence, approval, e-invoice | `filament-docs` |
| `filament-cashier` | Unified Filament billing UI across Stripe + CHIP. | filament, billing-ui, mrr, gateway-compare | `cashier` |
| `filament-cashier-chip` | Filament admin + customer portal for CHIP subscriptions. | filament, chip-portal, subscriptions-ui | `cashier-chip` |
| `filament-chip` | Filament explorer for CHIP purchases/clients (+ optional Send surfaces). | filament, chip-admin, purchases-ui | `chip` |
| `filament-docs` | Filament admin for documents, templates, sequences, reports, secure downloads. | filament, documents-ui, approvals-ui | `docs` |

## Analytics and events

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `events` | Events domain: series/venues/occurrences/sessions, registrations, check-in, change workflows (60+ models). | event, venue, occurrence, registration, check-in, waitlist | `filament-events` |
| `filament-events` | Filament admin for events/occurrences/sessions/venues/registrations + check-in console. | filament, events-ui, check-in | `events` |
| `filament-signals` | Filament analytics UI: dashboards, reports, goals/segments/alerts. | filament, analytics-ui, funnel, alerts | `signals` |
| `signals` | Privacy-first behavioural analytics: ingestion, sessions, rollups, goals, alerts, reports. | analytics, event-tracking, funnel, session, alert | `filament-signals` |

## Feedback

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `feedback` | Surveys, responses, invitations, scoring/analytics, testimonials with lifecycle management. | survey, response, invitation, nps, testimonial, analytics | `filament-feedback` |
| `filament-feedback` | Filament admin for surveys/responses/invitations/templates/testimonials + NPS dashboards. | filament, surveys-ui, nps | `feedback` |

## Venue / seating

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `filament-seating` | Filament seat-map admin: maps, editor + occupancy pages, overview widget. | filament, seat-map, occupancy | `seating` |
| `seating` | Vendor-agnostic seat maps/holds/allocations + Livewire picker + allocator contract. | seat, seat-map, hold, allocation, livewire | `filament-seating` |

## Bundle

| Package | 1-line | Triggers | Pair |
| --- | --- | --- | --- |
| `csuite` | Metapackage bundle: one Composer dependency installing the curated Commerce suite. No runtime code. | bundle, metapackage, install, suite, starter | `—` |

## Decision shortcuts

- New checkout/payment work → `checkout` first, then the gateway package (`chip` / `cashier` / `cashier-chip`).
- New admin screen → paired `filament-*` package; behavior change → core package first.
- Tenant/role question → `organizations` + `membership` + `authz`, then `commerce-support` owner rules.
- Person vs customer → person identity (titles/credentials) is `persons`; CRM/segments is `customers`.
- Address vs contact → postal/geography is `addressing`; emails/phones/socials is `contacting`; sent messages is `communications`.
- Discount vs coupon → automatic/code campaigns is `promotions`; issued codes/wallets is `vouchers`.
- Event vs seat vs ticket → schedule/registrations is `events`; maps/holds is `seating`; issuance is `ticketing`.
- Track vs test → analytics ingestion is `signals`; A/B winners is `growth`.
