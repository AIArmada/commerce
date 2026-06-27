# Audit: `signals` (AIArmada\Signals)

**Status:** Ready

**Audit date:** 2026-06-27

**Commit:** 7d1dc95fa

**Package role:** Behavioral analytics foundation with ingestion, sessions, rollups, alerts, and reports.

**Surface:** analytics

---

## Findings

### Low
1. **No enums** — Uses string constants instead of native PHP enums.
2. **No exception hierarchy** — 0 custom exceptions across 83 src files.
3. **No domain events** — Package emits no events; all 21 listeners consume external events.

---

## Bill of Health

| Concern | Rating | Notes |
|---------|--------|-------|
| `$guarded` usage | ✅ `$fillable` | All 11 models use explicit `$fillable` |
| Money storage | ✅ Integer minor units | `revenue_minor` as integer + `currency` string |
| Owner scoping | ✅ Full | All 11 models have `HasOwner` + `HasOwnerScopeConfig` |
| Migrations | ✅ Clean | No `constrained()`, no `cascadeOnDelete()` across 11 migrations |
| Actions | ✅ 10 classes | Ingest, identify, resolve session, capture pageview/geo, evaluate alerts, serve tracker |
| Contracts | ✅ 5 interfaces | BrowserContextResolver, MapCommerceEventToSignal, ReportInterface, ReverseGeocoder, SignalLocationResolver |
| Jobs | ✅ 2 classes | Both implement `OwnerScopedJob` with `OwnerContextJob` trait |
| Listeners | ✅ 21 listeners | Cart, checkout, orders, affiliates, vouchers integration |
| Tests | ✅ 27 files | Feature + Unit covering ingestion, commerce integration, alerts, segments |
| Docs | ✅ 6 files | Full standard set + reporting/alerts guide |
| Security | ✅ Strong | Property allowlisting, PII blocking, domain validation, IP anonymization, bot detection, write_key auth |

---

## Summary

Analytics engine: 11 models (TrackedProperty, SignalIdentity, SignalSession, SignalEvent, SignalDailyMetric, SignalSegment, SavedSignalReport, SignalAlertRule, SignalAlertLog, SignalGoal, SignalInteractionRule), 10 actions, 5 contracts, 2 jobs, 21 listeners, 26 services, 5 event mappers, 9 report types. Full ingestion pipeline: browser tracker → ingestion endpoints → identity resolution → session management → event storage → daily rollups → alerts + reports.

Owner scoping on all 11 models. Strong security: property allowlisting with PII hard-blocking, domain validation, IP anonymization, write_key authentication, bot detection. All jobs use `OwnerScopedJob`. Console commands use `OwnerBatchRunner`. `withoutOwnerScope()` used intentionally in cross-tenant query paths (scoped by `tracked_property_id`).

27 test files. 6 docs files.

**Verdict:** Ready. Largest package audited, well-engineered, strong security and analytics architecture.
