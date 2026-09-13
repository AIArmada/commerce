Review of packages/filament-communications (Filament v5 read-focused ops UI; no models/migrations/routes/jobs in scope — all live in `communications`).

FINDINGS

1. [medium, bug] src/Resources/CommunicationDeliveryResource.php:97-104 — Retry action lets domain RuntimeExceptions bubble to the user. `RetryCommunicationDeliveryAction::handle()` throws RuntimeException when status != failed or `attempt_count >= max_attempts` (verified in packages/communications/src/Actions/RetryCommunicationDeliveryAction.php:18-30). The `visible()` gate is render-time only, so a race (already retried / attempts exhausted) produces an unhandled exception instead of a danger Notification. Recommend try/catch around the action call with `Notification::make()->danger()->title(...)->send()`. Confidence: high.

2. [medium, bug] src/Widgets/DeliveryStatusOverviewWidget.php:20-40 — Status buckets silently drop 7 of 19 DeliveryStatus cases. Only Pending/Sent-accepted-received/Delivered-opened-read-clicked/Failed-bounced-complained-expired are counted; Suppressed, Scheduled, Queued, Sending, Replied, Unsubscribed, Cancelled appear nowhere, so the dashboard misleads on backlog/health. Recommend adding Queued/Sending to Pending (or a separate stat) and a Suppressed/Cancelled bucket, or a Total stat so buckets reconcile. Confidence: high (enum has 19 cases, verified).

3. [low, bug] src/RelationManagers/*.php + src/Resources/*.php — All three relation managers (Communications, Deliveries, CommunicationTimeline) are orphaned: no resource defines `getRelationManagers()` (grep-confirmed), and relationships exist on domain models, so this is dead code / missing wiring, not a wrong relationship. Recommend wiring them (e.g. CommunicationsRelationManager on thread resource, Deliveries + Timeline on communication resource) or deleting them. Confidence: high.

4. [low, bug] src/Resources/CommunicationResource/Pages/ViewCommunication.php:15-30 — Page-level `infolist()` duplicates CommunicationResource::infolist() verbatim; any future field change must be made twice and will drift. Recommend removing the page override so the resource infolist is the single source. Confidence: high.

5. [low, bug] All 7 Resources getNavigationSort() — every resource returns the same config `navigation.sort`, so sidebar order among the 7 resources is nondeterministic. Recommend per-resource offsets (sort, sort+1, …) or distinct config keys. Confidence: high.

6. [low, bug] CommunicationDeliveryResource.php:73-88, CommunicationThreadResource.php:65-71, CommunicationPreferenceResource.php:63-69 — channel/provider filter options are hardcoded string lists duplicated across resources with no domain enum/config backing (no Channel/Provider enum exists; domain stores plain strings). Drift risk when providers change. Recommend a shared constant/helper in `communications` (per adapter-only guardrail) consumed by all three resources. Confidence: med.

7. [low, security] All Resources + DeliveryStatusOverviewWidget — no `canViewAny`/policy/`canView` gates; any authenticated panel user sees all owner-scoped comms data (suppression hashes excluded — good — but purposes, titles, recipient IDs, costs visible). Owner scoping is correct, but there is no role/permission layer. Recommend documenting intended panel audience or adding `shouldRegisterNavigation`/policy checks if least-privilege is required. Confidence: med.

8. [low, performance] src/Widgets/DeliveryStatusOverviewWidget.php:18-40 — Four COUNT(*) subqueries over communication_deliveries run uncached on every dashboard render with no polling limit; on large delivery tables this is the heaviest query on the dashboard. Recommend short-lived cache (e.g. 60s, owner-keyed) or `getPollingInterval`. Confidence: med.

9. [low, bug] src/FilamentCommunicationsPlugin.php:44-46 — DeliveryStatusOverviewWidget is registered unconditionally with no config toggle, unlike all 7 resources. Minor inconsistency; recommend a `widgets.delivery_overview.enabled` key. Confidence: high.

NOTES / NON-FINDINGS (checked, clean)
- Owner scoping is correct everywhere it matters: all 7 resources use `OwnerUiScope::apply(..., includeGlobal: false)` in getEloquentQuery, matching domain default `include_global=false`; widget uses the same; retry action path re-resolves via `OwnerWriteGuard::findOrFailForOwner` AND the domain action's inner `findOrFail` is owner-scoped via the HasOwner global OwnerScope — no IDOR. Relation-manager children resolve through scoped parents.
- No mass assignment (no forms), no validation gaps (read-only + one confirmed action), no XSS (`TextColumn`/`TextEntry` only, no `->html()`), no injection/SSRF/path-traversal/deserialization vectors, no static Octane-unsafe state, no unbounded pagination (Filament defaults), no N+1 (no relation traversal in columns), no empty Policies/Pages stubs wired.
- Package has no tests directory; acceptable for thin UI but widget-bucket logic (finding 2) is worth a Pest test.

POSITIVES
- Consistent OwnerUiScope + OwnerWriteGuard usage with fail-closed semantics; retry is confirmation-gated and status-gated; suppressions expose only truncated destination_hash; money shown as `cost_minor` int minor units per repo rules; navigation group/sort via config per repo rules.