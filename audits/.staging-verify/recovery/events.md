End-to-end review of packages/events (Laravel monorepo, PHP 8.4). Deep-read: CONTEXT.md, config/events.php, EventsServiceProvider, ScopesByEventOwner, EventSubmissionOwnerScope, EventWriteGuard, Event/EventRegistration/EventOccurrence/EventSession/Venue/Taxonomy/Submission/Attachment/Media/Link/Involvement/Participant/Item models, RegistrationService, RegisterForFree, PromoteInterested, RecordAgentTicketSale, RecordWalkIn, CreateRegistrationsFromOrder, CreateEventComponentRegistrations, Update/CreateSession, SyncEventClassifications, SyncVenueFacilities, SyncManagementAssignmentToAuthz, CloneEventContents, FinalizeOccurredEventOrders(+Command), DefaultEventCheckInService, DefaultEventLifecycleWorkflow, EventQueryService, EloquentEventSearchEngine, EventSearchDocumentBuilder, EventNotificationDispatcher, EventTaxonomyHierarchyService, EventPolicy, BuildEventSearchDocumentJob, observers, notifications, blades, all cited migrations.

FINDINGS

1. [high/bug] packages/events/src/Actions/SyncEventClassificationsAction.php:63 — delete-then-recreate classifications with no transaction, no owner guard, N+1 taxonomy lookup, per-value firstOrCreate race.
Description: handle() deletes all event-level classifications, then recreates them one row at a time with `EventTaxonomy::query()->find()` inside the loop and `EventTerm::firstOrCreate(['event_taxonomy_id','code' => Str::slug($name)])` per value. No DB transaction (readers observe an empty window; crash leaves data deleted), no EventWriteGuard call (relies on callers), and concurrent syncs can duplicate terms if no unique key exists on (taxonomy, code).
Evidence: `EventClassification::query()->where('event_id',...)->...->delete(); ... foreach ($termIds...) { $taxonomy = EventTaxonomy::query()->find(...); EventClassification::query()->create([...]); }`
Recommendation: Wrap in DB::transaction, call EventWriteGuard::findOrFail($event->id), eager-load taxonomies once (whereIn + keyBy), add unique index on event_terms(event_taxonomy_id, code).
Confidence: high.

2. [high/security] packages/events/src/Models/Venue.php:65, EventTaxonomy.php:27, EventTerm.php:33, EventRole.php:29, EventTermPolicy.php:24, FacilityType.php:29, VenueSpace.php:45, VenueSpaceType.php:32, VenueFacility.php:40 — 9 models with no owner boundary at all.
Description: `grep -L HasOwner|ScopesByEventOwner|EventSubmissionOwnerScope Models/*.php` returns exactly these files (+ EventSeriesItemPivot and EventSubmission, the latter covered by EventSubmissionOwnerScope). Any owner context can read/write shared Venue/Taxonomy/Term/Role/FacilityType rows; SyncEventClassificationsAction::firstOrCreateTaxonomy/firstOrCreate term and SyncVenueFacilitiesAction let one tenant mutate or poison names/codes visible to all tenants, and events can reference venues owned by nobody.
Evidence: `final class EventTaxonomy extends Model { use HasFactory; use HasUuids;` (no scope trait); same shape for the other 8.
Recommendation: Either document these as intentional global vocabularies with admin-only write paths enforced in filament-events/policies, or add HasOwner / ScopesByEventOwner as appropriate; at minimum add a policy + write guard on taxonomy/term/facility-type creation.
Confidence: high (for missing boundary); med that exploitability matters (depends on who can reach the sync actions — filament-events not reviewed).

3. [high/bug] packages/events/src/Actions/PromoteInterestedToConfirmedAction.php:30 and RecordAgentTicketSaleAction.php:55 — capacity check-then-act with no lock/transaction; agent sale ships inventory before registrations exist.
Description: Promote reads capacityRemaining() then transitionStatus() with no LockEventRegistrationScopeAction and no transaction, so concurrent promotes overbook (RegisterForFree and CreateRegistrationsFromOrder correctly take the lock). RecordAgentTicketSaleAction checks capacity only when `enforce_scope_capacity_on_paid_registrations` (default false), takes no lock, loops quantity with no transaction, and calls inventory->shipFromDefault() before any registration is created — a later failure orphans shipped inventory.
Evidence: `$capacityRemaining = $this->capacityRemaining($registration); if (... < 1) throw ...; $registration->transitionStatus(Confirmed::class);` ; `$this->enforceCapacity(...); $this->inventory->shipFromDefault(...); for (...) { $this->registrations->register(...); ... }`
Recommendation: Use LockEventRegistrationScopeAction + DB::transaction in both; move shipFromDefault inside the transaction after successful registration creation (or compensate on failure); consider defaulting paid capacity enforcement to true.
Confidence: high.

4. [medium/security] packages/events/src/Services/RegistrationService.php:100 — registration items/answers/participants accepted with almost no validation; prices, ticket types, statuses caller-controlled.
Description: register() does `$registration->items()->create(array_merge($itemData, $scopeFields))` and same for answers — ticket_type_id is never verified to belong to the event scope, quantity/unit_price/total_price/currency/status/metadata are taken verbatim (price tampering, cross-event ticket types). Participants strip only email/phone/company/answers, leaving participant_type/id (arbitrary morph), status, age, gender unvalidated. Email/phone get only trim (cleanString, ~line 343), no format validation.
Evidence: `foreach ($data['items'] as $itemData) { $registration->items()->create(array_merge($itemData, $scopeFields)); }`
Recommendation: Validate items against scope ticket types, recompute/verify prices server-side, allowlist participant morph types and status values, validate email format.
Confidence: high.

5. [medium/bug] packages/events/src/Actions/UpdateEventSessionAction.php:30 (+ UpdateEventOccurrenceAction.php:33) — status written via mass update, bypassing the spatie state machine.
Description: Actions intersect caller attributes with getFillable() (which includes `status`) and call `$session->update($allowed)`, setting timestamps via match(). Direct attribute assignment does not run transitionTo() allowed-transition validation, so illegal jumps (e.g. completed → draft) succeed silently and lifecycle events/change-chain entries for most transitions are skipped (only a narrow status-change branch fires DispatchEventChangeChainAction). The lifecycle workflow (DefaultEventLifecycleWorkflow) correctly uses transitionTo().
Evidence: `$fillable = $session->getFillable(); $allowed = array_intersect_key(...); ... $session->update($allowed);`
Recommendation: Remove `status` from the mass-update allowlist and route status changes through EventLifecycleWorkflow (or call transitionTo() explicitly and reject unknown transitions).
Confidence: med (relies on spatie/laravel-model-states only validating in transitionTo, which is its documented behavior).

6. [medium/bug] packages/events/src/Resolvers/DefaultEventRegistrationEligibility.php:18 — eligibility checks occurrence status only; cancelled/completed sessions or events still accept registrations.
Description: ensureEligible() returns early when occurrence is null or canAcceptRegistrations(); it never inspects session status or event status. A cancelled session under a published occurrence, or an event-level registration on a cancelled event, passes eligibility.
Evidence: `if ($scope->occurrence === null || $this->lifecyclePolicy->canAcceptRegistrations($scope->occurrence)) { return; }`
Recommendation: Also evaluate session status (when scope has a session) and event status via LifecyclePolicy.
Confidence: high.

7. [medium/security] packages/events/src/Services/DefaultEventCheckInService.php:95 — attendee_type/attendee_id arbitrary morph + unvalidated metadata/notes/verified_by.
Description: checkInWithResult() persists attendee_type/attendee_id straight from $data into EventAttendance with no allowlist, letting callers attach check-ins to arbitrary model types. notes/metadata/check_in_source/verified_by_user_id/performed_by_* are likewise unvalidated.
Evidence: `'attendee_type' => $data['attendee_type'] ?? null, 'attendee_id' => $data['attendee_id'] ?? null, ... 'metadata' => $data['metadata'] ?? null,`
Recommendation: Allowlist attendee types (or require registration/participant/pass identity), validate source/verified_by, cap notes/metadata size.
Confidence: high.

8. [medium/performance] packages/events/src/Actions/RegisterForFreeAction.php:160 — idempotency check loads entire scope then filters metadata in PHP.
Description: findIdempotentRegistrations() runs `$query->get()->filter(fn => data_get(metadata,'registration.idempotency_key') === $key)` — every free registration scans all rows in the event/occurrence/session scope with no usable index. Grows linearly with event size on the hottest path.
Evidence: `$existing = $query->get()->filter(static fn (EventRegistration $r): bool => data_get($r->metadata ?? [], 'registration.idempotency_key') === $idempotencyKey)`
Recommendation: Store idempotency_key in a real indexed column (or JSON->> expression index where supported) and filter in SQL with limit.
Confidence: high.

9. [medium/performance] Unbounded result sets: EventQueryService.php:21 findPublished()->get(), :48 findByOwner()->get(); EloquentEventSearchEngine.php:~80 limit cast with no max/default; EventNotificationDispatcher.php:45 fallback registrations ->get(); FinalizeOccurredEventOrdersAction.php:23 ->get() + per-row fulfill; Console/Commands/FinalizeEventOrdersCommand.php:30 ->get().
Description: Five read paths materialize unbounded collections; search `limit` accepts any client-supplied int (including huge) and defaults to unlimited. Large tenants risk memory exhaustion and mail-storm loops (dispatcher notifies synchronously per recipient).
Evidence: `return $eventClass::published()->get();`, `$query->limit((int) $criteria['limit']); return $query->get();`
Recommendation: Paginate/chunk all five; clamp search limit (e.g. max 100, default 25); queue change-notice fan-out.
Confidence: high.

10. [medium/bug] packages/events/src/Console/Commands/FinalizeEventOrdersCommand.php:24 — command runs owner-scoped queries with no owner context.
Description: handle() queries EventOccurrence directly, but ScopesByEventOwner's global scope calls OwnerContext::assertResolvedOrExplicitGlobal(), which throws when console has neither an owner nor an explicit global context. The command will fail unless the operator already set one up; there is no --owner/--global option.
Evidence: `$query = EventOccurrence::query()->where('status', ...COMPLETED);` with no OwnerContext setup.
Recommendation: Add --owner-type/--owner-id and --global options and wrap execution in OwnerContext::withOwner()/explicit-global.
Confidence: med (depends on commerce-support OwnerContext console behavior; the assert call is confirmed in ScopesByEventOwner.php:36).

11. [medium/security] packages/events/src/Policies/EventPolicy.php:15 + EventsServiceProvider.php:~140 — only Event has a policy; viewAny always true; 60+ other models unregistered.
Description: Gate::policy is registered solely for the Event class. viewAny() returns true unconditionally and view() trusts isPubliclyVisible(). Whether registrations/occurrences/venues are authorization-checked depends entirely on filament-events (out of scope) — the domain package provides no defense in depth.
Evidence: `public function viewAny(mixed $user): bool { return true; }`, `Gate::policy($eventClass, EventPolicy::class);` (sole registration).
Recommendation: Add policies (or explicit deny-defaults) for registration/occurrence/session/venue/submission, or document that filament-events owns all authorization and verify it there.
Confidence: med.

12. [medium/bug] packages/events/src/Actions/CloneEventContentsAction.php:60 — clone has no write guard, no transaction, per-row saves; unknown relation names silently skipped.
Description: handle() takes raw source/target IDs with no EventWriteGuard verification, saves each replica individually (N+1 writes, partial clone on failure), and `continue`s on unknown $relations entries so caller typos silently no-op. (Cross-owner target writes are mitigated by the ScopesByEventOwner saving guard, which re-checks event visibility — hence medium, not high.)
Evidence: `$modelClass = self::MODEL_MAP[$relation] ?? null; if ($modelClass === null) { continue; } ... $replica->save();`
Recommendation: Verify both events via EventWriteGuard, wrap in DB::transaction, throw on unknown relation names, consider insert batching.
Confidence: high.

13. [low/security] packages/events/src/Models/Event.php:162 (also EventSeries.php:43, EventItinerary.php:40, EventTemplate.php:54, EventOrganizer.php:58) — owner_type/owner_id in $fillable.
Description: Direct-owner models mass-assign the owner tuple, so any create/update path passing user input to fill()/create() can set or reassign ownership. EventWalkIn/EventHeadcountLog (also HasOwner) correctly exclude owner columns from fillable — inconsistent. Whether HasOwner auto-assign overwrites on create was not verified in commerce-support.
Evidence: `protected $fillable = ['owner_type', 'owner_id', 'created_by_type', ...]`
Recommendation: Remove owner_* from fillable (rely on OwnerContext auto-assign) or explicitly guard reassignment on update.
Confidence: med.

14. [low/bug] packages/events/src/Models/EventRegistration.php:98 — fillable includes registrant morph, parent_registration_id, is_bundle_root, pass_entitlements; createFromOrderItem defaults currency to 'USD' (RegistrationService.php:~310) vs config default MYR.
Description: Bundle linkage fields are caller-settable with no same-event verification of parent_registration_id; registrant_type/id unverified. Currency default inconsistency ('USD' hardcoded vs events.defaults.currency MYR).
Evidence: `'registrant_type','registrant_id',...,'parent_registration_id','is_bundle_root','pass_entitlements',` ; `'currency' => $orderItemData['currency'] ?? 'USD'`
Recommendation: Verify parent/registrant belong to the same event scope; use config('events.defaults.currency').
Confidence: med.

15. [low/bug] Interested status is not capacity-blocking + walk-in count clamp + slug non-unique.
Description: (a) CAPACITY_BLOCKING_STATUSES (EventRegistration.php:94) omits `interested`, so unlimited Interested RSVPs accumulate and later Promote calls contend for scarce seats. (b) RecordWalkInAction.php:37 uses `max(1, $count)`, silently turning 0/negative counts into 1 instead of validating. (c) events.slug is a nullable non-unique index (migration 000001:23) while EventQueryService::findBySlug() returns first() — duplicate slugs are ambiguous.
Recommendation: Decide whether Interested should block capacity (or cap it); throw on count < 1; scope-unique slugs or order+document first-match.
Confidence: med.

16. [low/performance] Missing composite index for capacity math; float price comparisons.
Description: capacityRemaining() (EventOccurrence.php:526, EventSession.php:467) filters (event_occurrence_id|event_session_id, status) + sum — only single-column indexes exist; add composite (scope_id, status) indexes. effectivePricingMode() and ObserveEventTicketTypePricingConsistency compare `(float) $price` — fine for zero checks on int minor units but float-cast of money is a smell; compare `(int) $price === 0`.
Evidence: `->whereIn('status', $blockingStatuses)->sum('total_participants')`; `$hasPaid = $ticketTypes->contains(fn ($t): bool => (float) $t->price > 0);` (Event.php:560, Occurrence:442, Session:422).
Confidence: med.

17. [low/bug] packages/events/src/Actions/SyncManagementAssignmentToAuthzAction.php:58 — silent no-op when role missing; raw pivot write.
Description: assignManagerToScope() returns silently if the authz role row is absent — the management assignment looks successful while the permission never materializes. The DB::table($pivotTable)->updateOrInsert() bypasses model scopes (acceptable for a pivot, but unlogged).
Recommendation: Log a warning or throw when the role is missing; log pivot writes at debug.
Confidence: med.

18. [low/info] Dead DTOs + overbroad search-doc removal.
Description: Data/RegisterInput, ParticipantInput, CheckInInput carry no validation rules and are referenced nowhere in src, filament-events, or tests — dead code that will mislead the next author into thinking inputs are validated. EventSearchDocumentBuilder::remove(EventSearchDocument) with only event_id set deletes all docs for the event (all occurrences/sessions) — plausible intent but worth a comment/guard.
Recommendation: Delete or adopt+validate the DTOs; narrow remove() or document the cascade.
Confidence: med.

POSITIVES (verified)
- Owner scoping architecture is strong where applied: HasOwner+HasOwnerScopeConfig on 7 direct-owner models, ScopesByEventOwner (whereHas event chain + saving/deleting write guards via EventWriteGuard/OwnerWriteGuard) on ~40 children, EventSubmissionOwnerScope registered in provider; RegisterForFree/CreateRegistrationsFromOrder/CreateQuestion take lockForUpdate under the event/occurrence/session row inside transactions.
- Repo rules honored: uuid PKs everywhere; no FK constraints/cascades/SoftDeletes in migrations (only `foreignUuid()->index()` column types); money as bigInteger minor units (total_amount/unit_price/total_price); orchestration lives in Actions; no routes/widgets/jobs bypassing scopes except noted (BuildEventSearchDocumentJob correctly implements OwnerScopedJob with OwnerJobContext).
- Injection: no eval/exec/shell/unserialize; search LIKE uses bound parameters with allowlisted sort field/dir; whereRaw('1 = 0') constants only.
- XSS: both mail blades use {{ }} escaped output; welcome notification is ShouldQueue + afterCommit with owner context capture.
- Octane/cache: singletons (EventQueryService, RegistrationService, lifecycle/change-notice workflows) are stateless; taxonomy hierarchy caches in request attributes (request-scoped, Octane-safe); no static mutable state, no Cache:: stampede surface found.
- Indexes: events/registrations/attendances migrations index owner, slug, status, visibility, scope FKs, timestamps; registration_no unique.
- State machines: registration lifecycle timestamps recorded via initializeStatus/transitionStatus; lifecycle workflow uses transitionTo + domain events.
- Tests exist for the hot paths (RegisterForFree, PromoteInterested, RecordWalkIn/Headcount, AgentSale, CreateRegistrationsFromOrder, CheckInService, CrossTenantIsolation, OwnershipExceptionsMachineCheck, MigrationLint).

Out of scope / not verified: filament-events authorization + Filament navigation config (no Filament resources in this package); commerce-support HasOwner internals (auto-assign/overwrite semantics); whether console OwnerContext defaults make finding #10 fatal or merely inconvenient.