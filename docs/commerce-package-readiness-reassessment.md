# Re-assessment: ilmu360° Full Rewrite on aiarmada/pockages

> Flipping the question from "what can we adopt?" to **"what if we build everything on top of packages?"**
> Then re-checking every gap against actual package code.

---

## 1. Critical Discovery: Communications Package

**The original docs missed the `communications` package entirely.** This is a full notification/delivery engine:

- **15 models**: Communication, Batch, Thread, Content, Recipient, Delivery, Attempt, Event, TrackingToken, Preference, Template, TemplateVersion, Suppression, Attachment, Reference
- **10 enums**: Direction, Category, Priority, Status, DeliveryStatus, SuppressionReason, RecipientRole, TemplateStatus, ThreadStatus, EventSource
- **30 actions**: DispatchManagedNotification, Create, Cancel, Retry, PlanDeliveries, RecordSending/Sent/Failure, ResolveEligibility, LiftSuppression, TrackInteraction, etc.
- **Contracts**: ContentRenderer, DestinationResolver, RecipientSnapshotResolver
- **Events**: CommunicationCreated, Queued, etc.
- **Batching, threads, quiet hours, suppression lists, template management, tracking tokens, rate limiting, digest support**

This covers **~80% of ilmu360's notification engine** (6 custom tables, 3,500 lines).

---

## 2. Full Gap Re-assessment (Rewrite Perspective)

### 2.1 Events Domain — Islamic Features (~12,000 lines)

| Feature | Package Model | Can Cover? | Notes |
|---------|--------------|-----------|-------|
| Prayer-relative timing | `EventTimeExpression` | **✅ Yes** | `anchor_type=prayer`, `anchor_code=maghrib`, `offset_minutes`, `display_label="Selepas Maghrib"`, `resolver_class` for computation |
| Gender restriction | `EventAttribute` | **✅ Yes** | `attribute_key="gender_restriction"`, `attribute_value="male_only"` |
| Age group | `EventAudience` | **✅ Yes** | `audience_type="age_group"`, `value="children"` |
| Muslim only | `EventAttribute` | **✅ Yes** | `attribute_key="is_muslim_only"`, `attribute_value="true"` |
| Event type | `EventAttribute` | **✅ Yes** | `attribute_key="event_type"` |
| Event structure (standalone/parent/child) | `EventClassification` or `EventAttribute` | **✅ Yes** | Could use EventTaxonomy/EventTerm for structure types |
| Timing mode (fixed/prayer-relative) | `EventTimeExpression.time_mode` | **✅ Yes** | `time_mode="fixed"` or `time_mode="prayer_relative"` |
| Islamic key roles (Imam, Khatib, Bilal) | `EventRole` (seedable) | **✅ Yes** | Seed roles with codes: `speaker`, `imam`, `khatib`, `bilal`, `moderator`, `person_in_charge` |
| Spatie Tags | `EventTaxonomy` + `EventTerm` + `EventClassification` | **✅ Yes** | Can seed taxonomies for Domain/Discipline/Source/Issue. Terms have `code`, `name`, `parent_id` for hierarchy. |
| Spatie MediaLibrary | `EventMedia` | ⚠️ **Bridge needed** | Package uses `EventMedia` (file_id/url) not MediaLibrary directly. Bridge layer needed. |
| Malay-optimized search | `EventSearchDocument` | **✅ Yes** | Package has search document model with facets + coordinates. Custom `EventSearchEngine` contract. |

**Verdict: ALL Islamic-domain features CAN be modeled on top of the package's Event model using EventAttribute, EventAudience, EventTimeExpression, EventRole, and EventClassification.**

The previous analysis said "50% of fields have no equivalent" — but that was looking for exact column matches. The package's EAV + audience + time-expression + taxonomy architecture provides equivalent coverage through composition.

### 2.2 Institutions (~4,500 lines)

| Feature | Package Model | Can Cover? |
|---------|--------------|-----------|
| Basic org (name, slug, bio, status, visibility) | `Organization` | **✅ Yes** |
| Email/phone/social contacts | `HasContactMethods` + `HasSocialProfiles` (Organization already uses these) | **✅ Yes** |
| Own events | `OwnsEvents` + `CanOrganizeEvents` traits | **✅ Yes** |
| Be involved in events | `HasEventInvolvements` + `CanBeInvolvedInEvents` | **✅ Yes** |
| Media/logo | `InteractsWithMedia` (Organization already has `logo` collection) | **✅ Yes** |
| Languages | `HasLanguages` trait (JSON array from commerce-support) | **✅ Yes** |
| **Membership/claims** | `aiarmada/membership` package: `MembershipApplication` model + `ApplyForMembership`/`Approve`/`Reject` actions + `HasMembers` trait | **✅ Yes** — generic, standalone, Spatie role-integrated |
| **Member invitations** | `aiarmada/membership` package: `MembershipInvitation` model + `InviteMember`/`AcceptInvitation`/`RevokeInvitation` actions | **✅ Yes** — dedicated invitation model with token, expiry, full lifecycle |
| **Dashboard** | None | ❌ Custom Livewire |
| **Ban/block system** | Follow model has `STATUS_BLOCKED` + `blocked_at` | ⚠️ Partial — embedded in Follow, no standalone Ban model |
| **Donation channels** | None | ❌ Stays custom |
| Search/Scout | Custom `toSearchableArray()` | ⚠️ Custom search config needed |

**Verdict: ~80% coverage.** Organization provides a solid base. Membership workflow via `aiarmada/membership` package (applications → approval → member with Spatie role assignment). Missing: donation channels, public dashboard UI.

### 2.3 Speakers (~2,500 lines)

| Feature | Package Model | Can Cover? |
|---------|--------------|-----------|
| **Speaker as entity** | No dedicated Speaker model | **❌ No standalone model** |
| Speaker involvement in events | `EventInvolvement` (polymorphic) | **✅ Yes** — any model can be `involveable` |
| Speaker roles | `EventRole` + `EventInvolvement.role_code` | **✅ Yes** |
| Claims workflow | `aiarmada/membership` package (`MembershipApplication` model) | **✅ Yes** |
| Bio, contact, social, media | `HasContactMethods`, `HasSocialProfiles`, `InteractsWithMedia` | **✅ Yes** |
| Speaker listing/detail | None (Livewire) | ❌ Custom UI |
| Speaker search | Custom Scout config | ❌ Custom |

**Verdict: ~60% coverage.** No dedicated Speaker model exists. Thin custom model implementing `CanBeInvolvedInEvents` + `HasContactMethods` + `HasSocialProfiles` + `InteractsWithMedia` + `HasMembers` would be trivial (~100 lines). Membership claims workflow covered by `aiarmada/membership`.

### 2.4 References (~2,000 lines)

| Feature | Package Model | Can Cover? |
|---------|--------------|-----------|
| **Reference as entity** | `Reference` (title, slug, author, publisher, year, isbn, url, status, hierarchy via `parent_id`) | **✅ Yes** — `aiarmada/references` package (June 2026) |
| **Event→Reference linking** | `EventReference` (polymorphic, with `reference_type`, `citation`, `url`) | **✅ Yes** |
| `ReferencedByEvents` trait | ✅ For event-side linking | **✅ Yes** |
| **Reference hierarchy (parent/child)** | `HasReferenceParts` trait + `parent_id` self-reference | **✅ Yes** — Jilid/Juz/Surah via `ref_part_type`+`ref_part_value` |
| **Slug generation** | `GenerateReferenceSlugAction` (uses spatie/laravel-sluggable) | **✅ Yes** |
| **Reference status workflow** | `ReferenceStatus` enum (draft, verified, rejected) | **✅ Yes** |
| **Claims workflow** | `aiarmada/membership` package (`MembershipApplication` model) | **✅ Yes** |
| **Search/listing/detail** | None (Livewire/API) | ❌ Custom UI |

**Verdict: ~85% coverage.** The `aiarmada/references` package provides a full `Reference` model with title, author, publisher, year, ISBN, slug, hierarchy, and status workflow. The remaining gap is public UI (index, show, search pages) — ~300 lines.

### 2.5 Notifications Engine (~3,500 lines)

| Feature | communications Package | Can Cover? |
|---------|----------------------|-----------|
| Communication record (unified model) | `Communication` with direction, category, priority, status, purpose, lifecycle timestamps | **✅ Yes** |
| Batching | `CommunicationBatch` | **✅ Yes** |
| Threading/conversations | `CommunicationThread` | **✅ Yes** |
| Multi-channel content | `CommunicationContent` (per-channel) | **✅ Yes** |
| Recipient tracking | `CommunicationRecipient` with snapshot, role, locale, timezone | **✅ Yes** |
| Delivery per channel | `CommunicationDelivery` with full status lifecycle (queued→sending→sent→delivered→opened→read→clicked→bounced→complained→failed) | **✅ Yes** |
| Delivery attempts | `CommunicationAttempt` with attempt count, max attempts, provider, response | **✅ Yes** |
| Provider events | `RecordProviderEventAction`, `ApplyProviderEventAction` | **✅ Yes** |
| Webhook lifecycle | Webhooks, webhook event types | **✅ Yes** |
| Suppression | `CommunicationSuppression` with reason, expiry, scope | **✅ Yes** |
| Tracking tokens | `CommunicationTrackingToken` with interaction recording | **✅ Yes** |
| Templates | `CommunicationTemplate` + `CommunicationTemplateVersion` with publishing | **✅ Yes** |
| Preferences | `CommunicationPreference` (channel, category, sub_category, enabled, digest_frequency, quiet_hours) | **✅ Yes** |
| Eligibility (suppression + preferences + quiet hours + rate limits) | `ResolveCommunicationEligibilityAction` | **✅ Yes** |
| Laravel Notification integration | `DispatchManagedNotificationAction` (receives Laravel `Notification` and creates full Communication record) | **✅ Yes** |
| Attachments | `CommunicationAttachment` | **✅ Yes** |
| Pruning/cleanup | `PruneCommunicationDataAction` | **✅ Yes** |
| **In-app inbox** | `NotificationInbox` model + `HasInbox` trait + `DispatchInboxNotificationAction` + `InboxIndex` Livewire component | **✅ Yes** — added to `communications` package (June 2026) |
| **FCM push channel** | Package doesn't implement provider channels | ❌ Custom channel impl |
| **WhatsApp channel** | Same as above | ❌ Custom channel impl |
| **Digest delivery** | Preferences have digest_frequency, but no digest scheduling job | ⚠️ Custom digest scheduling needed |

**Verdict: ~91% coverage** (with the communications package completely missed in original analysis). The package now includes the in-app inbox (`NotificationInbox`, `HasInbox`, `InboxIndex` Livewire component). Missing: specific provider channel implementations (FCM, WhatsApp), and digest aggregation scheduling.

### 2.6 Contributions / Moderation (~2,500 lines)

| Feature | Package Model | Can Cover? |
|---------|--------------|-----------|
| Event submissions (CRUD) | `EventSubmission` (polymorphic submitter + target, `submission_data` JSON, state machine) | **✅ Yes** |
| Moderation actions | `EventModerationAction` (actionable polymorphic, action_type, reason, reversal) | **✅ Yes** |
| Moderation audit trail | `EventSubmissionLog` | **✅ Yes** |
| Approval workflow | `EventApprovalRequest` (approvable polymorphic, assigned_to, status) | **✅ Yes** |
| Submission attachments | `EventSubmissionAttachment` | **✅ Yes** |
| Moderation workflow contract | `EventModerationWorkflow` + `DefaultEventModerationWorkflow` | **✅ Yes** |
| **Non-event submissions** (Institution, Speaker, Reference, Venue) | `EventSubmission.target` is polymorphic | **✅ Yes** — `target_type`/`target_id` can be any model |
| `ContributionEntityMutationService` (applying accepted data to entities) | None | ❌ Custom — but simplified vs current (1,546 lines) |

**Verdict: ~85% coverage.** The `EventSubmission` model is polymorphic on both `submitter` and `target`, so it handles ALL entity types (not just events). The `EventModerationWorkflow` contract covers the moderation flow. The main remaining custom code is `ContributionEntityMutationService` — the mutation logic for applying submission data to entities — but this would be simpler given the submissions already hold structured JSON data.

### 2.7 Donation Channels (~500 lines)

No package coverage anywhere in the 54 packages. Islamic charity-specific feature (zakat tracking, payment gateways). **Stays 100% custom.**

### 2.8 MCP Servers (~2,000 lines)

No package coverage. Application-specific AI tools. **Stays 100% custom.**

### 2.9 Public Livewire Pages (~2,500 lines)

No package provides public Livewire UI. Packages only provide Filament admin resources. **Stays 100% custom** — expected, UI is always app-specific.

### 2.10 Bans / Blocks (~300 lines)

| Feature | Package Model | Can Cover? |
|---------|--------------|-----------|
| Block user (any entity) | `Block` model + `HasBlocks` trait (morphTo `blockable`) | **✅ Yes** — `aiarmada/moderation` package (June 2026) |
| Entity-level block | `Block.blockable` is polymorphic — any model | **✅ Yes** |
| Block lifecycle | `BlockStatus` enum (active, expired, lifted) + `expires_at`/`lifted_at` timestamps | **✅ Yes** |
| Block reasons | `BlockReason` enum (spam, abuse, harassment, etc.) | **✅ Yes** |
| Block/unblock action | `BlockEntityAction` | **✅ Yes** |
| Moderation audit trail | `ModerationAction` model + `HasModerationActions` trait | **✅ Yes** |
| Ban management UI | None | ❌ Custom (~50 lines) |

**Verdict: ~90% coverage.** The `aiarmada/moderation` package provides a dedicated `Block` model with polymorphic `blockable`, lifecycle enums, expiration, and a full `ModerationAction` audit trail. Remaining gap: admin management UI (~50 lines).

### 2.11 Tags on Non-Event Entities (~500 lines)

| Feature | Package Model | Can Cover? |
|---------|--------------|-----------|
| Taxonomy system | `EventTaxonomy` + `EventTerm` + `EventClassification` | **✅ Yes** — generic enough for any model |
| `HasEventClassifications` trait | Can be used on any model (not just events) | **✅ Yes** |

**Verdict: ~90% coverage.** The EventClassification model and HasEventClassifications trait are generic — the `classifiable` morph works on any model. The only issue is the dependency on the `events` package for non-event entity tagging, which is an acceptable architectural trade-off in a rewrite.

---

## 3. Revised Gap Register

### After Full Rewrite on All Relevant Packages

| Domain | Before (custom lines) | After (custom lines) | Reduction | Covered By |
|--------|----------------------|---------------------|-----------|------------|
| Events core + Islamic features | ~12,000 | **~2,000** | ~83% | events pkg + EventAttribute/Audience/TimeExpression |
| Institutions | ~4,500 | **~1,000** | ~78% | Organization model + plans workflow |
| Speakers | ~2,500 | **~400** | ~84% | Thin custom model + EventInvolvement |
| References | ~2,000 | **~300** | ~85% | references pkg (new) |
| Notifications engine | ~3,500 | **~300** | ~91% | communications pkg + inbox pkg |
| Contributions/Moderation | ~2,500 | **~1,000** | ~60% | EventSubmission (polymorphic) + CEMS |
| Donation Channels | ~500 | ~500 | 0% | No package |
| MCP Servers | ~2,000 | ~2,000 | 0% | No package |
| Public Livewire UI | ~2,500 | ~2,500 | 0% | No package (expected) |
| Bans/Blocks | ~300 | **~50** | ~83% | moderation pkg (new) |
| Tags (non-event) | ~500 | ~100 | 80% | EventClassification (generic morph) |
| Saved Searches | ~500 | ~100 | 80% | commerce-support |
| Reports | ~2,000 | ~500 | 75% | commerce-support Report |
| Engagement (follow/bookmark) | ~1,200 | ~200 | 83% | engagement pkg |
| Entity claims/membership (cross-cutting) | ~2,500 | **~500** | 80% | `aiarmada/membership` package (June 2026): `MembershipApplication`, `MembershipInvitation`, `HasMembers` trait, `MemberRole` with Spatie integration, 10 actions, 6 events |
| **Total** | **~38,000** | **~11,450** | **~70%** | |

**Custom code that stays: ~11,450 lines (30% of original)**

---

## 4. What Changes vs Original Analysis

| Original Gap | Was | Now | Because |
|-------------|-----|-----|---------|
| Events core (Islamic features) | ❌ No coverage | ✅ Full coverage | EventAttribute, EventAudience, EventTimeExpression, EventRole, EventClassification |
| Notifications engine | ❌ No coverage | ✅ ~80% coverage | communications package missed in original scan |
| Contributions (non-event) | ❌ Event-only | ✅ All entities | EventSubmission.target is polymorphic |
| Speakers | ❌ No model | ⚠️ Partial | No dedicated model but Organization + EventInvolvement covers most |
| Bans/Blocks | ❌ No coverage | ⚠️ Partial | Follow model has embedded block support |
| Tags on non-events | ❌ Event-only | ✅ Generic | EventClassification.classifiable is polymorphic |

---

## 5. Remaining Custom Code Breakdown

After full rewrite, these remain custom:

| Custom Domain | Lines | Why |
|--------------|-------|-----|
| Public Livewire pages (index, show, submit, dashboard) | ~2,500 | Packages provide Filament admin only |
| MCP servers (admin + member) | ~2,000 | App-specific AI tools |
| Membership management UI (cross-cutting) | ~500 | Backend provided by `aiarmada/membership`; remaining UI (member lists, invite/modals, role pickers) |
| **ContributionEntityMutationService** | ~1,000 | ~1,546 now, ~1,000 simplified — CRUD/mutation for all 5 entities |
| Donation Channels | ~500 | Islamic charity-specific |
| FCM + WhatsApp push channel implementations | ~400 | Provider channel drivers |
| Speaker entity model + UI | ~400 | Thin model wrapping package traits |
| Reference entity UI (index, show, detail) | ~300 | Package provides model + hierarchy |
| Digest scheduling | ~300 | Notification-center concern, not events; communications only has enums |
| Integration glue (signals hooks, share outcomes) | ~300 | App-specific wiring |
| Data migration scripts | ~200 | One-time ETL |
| SavedSearch filter normalizer | ~200 | Domain-specific (geography/prayer/tag filters) |
| Filament adapters (moderation, references) | ~200 | Packages exist but have no Filament admin resources |
| Ban/block management UI | ~50 | Package provides model + actions |
| Notification inbox app integration | ~50 | Package provides model + Livewire component (InboxIndex) |
| **Total** | **~8,950** | |

---

## 6. Key Architectural Decisions in a Rewrite

### 6.1 Core Event Model Stays from Package

The rewrite uses `AIArmada\Events\Models\Event` as the base, with:

| Islamic Feature | How It Maps |
|----------------|------------|
| Prayer timing | `EventTimeExpression` records per-event |
| Gender restriction | `EventAttribute("gender_restriction")` |
| Age group | `EventAudience("age_group", "children")` |
| Muslim only | `EventAttribute("is_muslim_only")` |
| Event type | `EventAttribute("event_type")` |
| Event structure | `EventAttribute("event_structure")` |
| Key people | `EventInvolvement` with seeded `EventRole` values |
| Tags/classification | `EventTaxonomy` for Domain/Discipline/Source/Issue |

### 6.2 Institution = Extended Organization

The `Organization` model provides the base. Extend with:
- Custom membership via `aiarmada/membership` package (`HasMembers` trait + `MembershipApplication`/`MembershipInvitation` models)
- Custom `HasDonationChannels` trait
- Custom dashboard Livewire

### 6.3 Speaker = Thin Custom Model

No package model needed — a ~100-line model implementing:
- `CanBeInvolvedInEvents`
- `HasContactMethods`
- `HasSocialProfiles`
- `InteractsWithMedia`
- `HasOwner`

Plus a seeded `EventRole` entry for `speaker`.

### 6.4 References Custom Model (Largest Remaining Gap)

The package has `EventReference` for event→reference linking, but no Reference entity model. A custom model needed with fields for title, author, year, publisher, ISBN/ISSN, citation format.

---

## 7. Revised Recommendation

### Adopt All These Packages (Rewrite):

| Package | Effort | Value |
|---------|--------|-------|
| **events** (full) | 8-12 wk | Replaces ~10,000 lines. Islamic features via EventAttribute/Audience/TimeExpression. Submissions, registrations, check-in, change management, notifications, taxonomy |
| **moderation** (new) | 1 wk | Replaces ~250 lines. Ban/Block + ModerationAction models with polymorphic support |
| **references** (new) | 1 wk | Replaces ~1,200 lines. Reference entity model with hierarchy, slug generation, status workflow |
| **engagement** | 2-3 wk | Replaces 3 custom pivots + adds subscriptions, reminders, reactions, 30 events |
| **contacting** | 2 wk | Unifies Contact+SocialMedia + verification |
| **communications** | 3-4 wk | **Critical.** Replaces ~91% of notification engine + includes inbox Livewire component |
| **commerce-support** | 1 wk | Reports, SavedSearches, NotificationPreferences |

### Don't Adopt:

| Package | Why |
|---------|-----|
| **addressing** | Geography int→UUID migration still disproportionate. Geocoding actions useful but can add without replacing geography system. |

## 8. Implementation Progress — Three New Packages (+1,650 generic, −1,870 custom)

Three new generic packages have been implemented alongside the EAV sync to close the largest remaining gaps (June 2026):

### 8.1 `aiarmada/moderation` — Ban/Block System (+600 generic, −250 custom)

| Component | What It Does | Location |
|-----------|-------------|----------|
| `Block` model | Polymorphic `blockable`, `BlockStatus` enum (active/expired/lifted), `expires_at`/`lifted_at` | `packages/moderation/src/Models/` |
| `HasBlocks` trait | `morphMany blocks`, `isBlocked()`, `activeBlocks()`, `block()`, `unblock()` | `packages/moderation/src/Traits/` |
| `BlockReason` enum | spam, abuse, harassment, violence, impersonation, misinformation, etc. | `packages/moderation/src/Enums/` |
| `BlockEntityAction` | Block/unblock an entity with reason + optional expiry | `packages/moderation/src/Actions/` |
| `ModerationAction` model | Polymorphic audit trail for all moderation actions | `packages/moderation/src/Models/` |
| `HasModerationActions` trait | `morphMany moderationActions` | `packages/moderation/src/Traits/` |
| 2 migrations | `moderation_blocks` + `moderation_actions` tables | `packages/moderation/database/migrations/` |
| Tests (48, 196 assertions) | Block/unblock lifecycle, expiration, moderation audit trail | `tests/src/Moderation/` |

### 8.2 `aiarmada/references` — Reference Entity (+500 generic, −1,200 custom)

| Component | What It Does | Location |
|-----------|-------------|----------|
| `Reference` model | title, slug, author, publisher, year, isbn, url, status, parent_id hierarchy | `packages/references/src/Models/` |
| `ReferenceType` enum | quran, hadith, book, article, fatwa, thesis, etc. | `packages/references/src/Enums/` |
| `ReferenceStatus` enum | draft, verified, rejected | `packages/references/src/Enums/` |
| `ReferencePartType` enum | jilid, juz, surah, chapter, verse, page | `packages/references/src/Enums/` |
| `HasReferenceParts` trait | Jilid/Juz/Surah hierarchy via `ref_part_type` + `ref_part_value` | `packages/references/src/Traits/` |
| `GenerateReferenceSlugAction` | Auto-generate slug from title + author | `packages/references/src/Actions/` |
| Migration | `ref_references` table | `packages/references/database/migrations/` |
| Tests (31, 162 assertions) | CRUD, slug generation, hierarchy, status workflow | `tests/src/References/` |

### 8.3 In-App Notification Inbox (+550 generic, −420 custom)

Added to existing `aiarmada/communications` package (no new package):

| Component | What It Does | Location |
|-----------|-------------|----------|
| `NotificationInbox` model | morphTo recipient, communication_id FK, family/priority/trigger enums, read_at/archived_at/scheduled_at | `packages/communications/src/Models/` |
| `HasInbox` trait | `morphMany inbox`, `unreadCount()`, `markAsRead()`, `markAllAsRead()`, `archiveRead()` | `packages/communications/src/Traits/` |
| `NotificationFamily` enum | event_change, registration_confirmation, system_announcement, moderation_action, etc. | `packages/communications/src/Enums/` |
| `NotificationPriority` enum | low, normal, high, urgent | `packages/communications/src/Enums/` |
| `NotificationTrigger` enum | ~30 values across Event/Registration/Social/System/Moderation/Digest | `packages/communications/src/Enums/` |
| `DispatchInboxNotificationAction` | Creates Communication + NotificationInbox in transaction | `packages/communications/src/Actions/` |
| `NotificationInboxService` | create, markAsRead, markAllAsRead, archive, prune | `packages/communications/src/Services/` |
| `InboxIndex` Livewire component | Paginated list, filter tabs (all/unread/archived), priority dots, archive/read controls | `packages/communications/src/Http/Livewire/` |
| `PruneNotificationInboxesCommand` | Scheduled pruning of archived/read items | `packages/communications/src/Console/Commands/` |
| Migration #16 | `communication_notification_inboxes` table | `packages/communications/database/migrations/` |
| Tests (52, 281 assertions) | Create, read/unread, archive, prune, Livewire component | `tests/src/Communications/` |

### 8.4 `aiarmada/membership` — Generic Membership/Claims System (+1,500 generic, −2,000 custom)

Extracted from `aiarmada/authz` into a standalone package with Spatie role integration:

| Component | What It Does | Location |
|-----------|-------------|----------|
| `MembershipApplication` model | Polymorphic `subject`, `applicant_id`, `status` (pending/approved/rejected/cancelled), `justification`, `meta` | `packages/membership/src/Models/` |
| `MembershipInvitation` model | Polymorphic `subject`, `email`, `token`, `role`, `expires_at`, `accepted_at`, `revoked_at` | `packages/membership/src/Models/` |
| `HasMembers` trait | `members()` (BelongsToMany with pivot `role`+`joined_at`), `applications()`, `invitations()`; overridable `membersTable()` defaults to `<model>_members` | `packages/membership/src/Traits/` |
| `MemberRole` enum | `Admin`, `Editor`, `Viewer` with custom `spatieRoleName()` mapping (team-scoped Spatie role assignment) | `packages/membership/src/Enums/` |
| `MembershipRoleSyncService` | `ensureExists()`, `assignToUser()` (team-scoped `setPermissionsTeamId`), `revokeFromUser()` | `packages/membership/src/Services/` |
| 10 Actions | Apply, Approve, Reject, Cancel applications; Invite, Accept, Revoke invitations; Add, Remove, Change member roles | `packages/membership/src/Actions/` |
| 6 Events | ApplicationSubmitted/Approved/Rejected/Cancelled, InvitationSent/Accepted | `packages/membership/src/Events/` |
| 2 Contracts | `MembershipApplicationNotifier` (submitted/approved/rejected), `MembershipHook` (member added/removed/role changed) | `packages/membership/src/Contracts/` |
| `membership:sync-roles` command | Idempotent sync of `MemberRole` cases into Spatie roles | `packages/membership/src/Console/Commands/` |
| `authz:make-pivot {model}` command | Generates `<model>_members` pivot migration from stub | `packages/membership/src/Console/Commands/` |
| 2 migrations | `membership_applications` + `membership_invitations` tables | `packages/membership/database/migrations/` |
| Config | `membership.php` — table names, pivot suffix, role_mapping, team-scoped feature flag | `packages/membership/config/` |
| Tests (78, 116 assertions) | Application/invitation CRUD, lifecycle, role sync, pivot table generation, Spatie integration | `tests/src/Membership/` |

**Impact**: The cross-cutting membership backend (~2,000 lines) is eliminated as custom code. Only ~500 lines of membership management UI (member lists, invite modals, role pickers) remain custom since no package provides Filament/Livewire admin for membership.

### 8.5 EAV Sync (from previous pass)

The EAV→metadata sync mechanism has been implemented as a **generic package feature** in `aiarmada/events`:

| Component | What It Does | Location |
|-----------|-------------|----------|
| `EventMetadataSyncService` | syncAttribute/syncAudience/syncTimeExpression/rebuild | `packages/events/src/Services/` |
| `EventAttributeObserver` | Auto-sync EventAttribute changes → Event.metadata | `packages/events/src/Observers/` |
| `EventAudienceObserver` | Auto-sync EventAudience changes → metadata + facets | `packages/events/src/Observers/` |
| `EventTimeExpressionObserver` | Auto-sync EventTimeExpression → metadata | `packages/events/src/Observers/` |
| `EventClassificationObserver` | Auto-sync EventClassification → facets | `packages/events/src/Observers/` |
| `EventSearchDocumentBuilder` | Implements EventSearchIndexer; builds/upserts docs | `packages/events/src/Services/` |
| `BuildEventSearchDocumentJob` | `ShouldBeUnique` (60s) queued search doc building | `packages/events/src/Jobs/` |
| Migration #78 | PostgreSQL GIN indexes on metadata/facets JSONB | `packages/events/database/migrations/` |
| `config/events.php` | `sync.*`, `attribute_sync.*`, `search.*` config keys | `packages/events/config/` |
| Tests (18, 57 assertions) | Sync service + search builder | `tests/src/Events/` |

**Impact**: The "Integration glue" item below is eliminated (moved into package as generic feature). Custom lines drop by ~300.

### Remaining Build Work (~8,950 lines):

1. **Public Livewire pages** (~2,500) — index, show, submit, dashboard
2. **MCP servers** (~2,000) — admin + member AI tools (built on `laravel/mcp`)
3. **Membership management UI** (~500) — cross-cutting member listing, invite modals, role pickers across 4 entity types; backend provided by `aiarmada/membership` package
4. **ContributionEntityMutationService** (~1,000) — ~1,546 lines now, simplified to ~1,000
5. **Donation channels** (~500) — Islamic charity-specific
6. **Speaker thin model + UI** (~400) — package has no entity
7. **FCM + WhatsApp push channels** (~400) — provider drivers
8. **Reference entity UI** (~300) — package provides model + hierarchy
9. **Digest scheduling** (~300) — notification-center concern, separate from events
10. **Integration glue** (~300) — signals hooks, share outcomes
11. **Data migration scripts** (~200) — one-time ETL
12. **SavedSearch filter normalizer** (~200) — domain-specific filters
13. **Filament adapters (moderation, references)** (~200) — packages need admin CRUD UIs
14. **Ban/block management UI** (~50) — package provides model + actions
15. **Notification inbox integration** (~50) — package provides component

### Final Verdict

**With four new generic packages (moderation, references, inbox, membership) and EAV sync in the events package, plus the `aiarmada/authz`→`aiarmada/membership` extraction, a full rewrite achieves ~70% code reduction (down to ~11,450 custom lines). The cross-cutting membership system (~2,500 lines, previously hidden inside the Institutions estimate) is now provided by `aiarmada/membership` with ~80% coverage — only ~500 lines of entity-specific membership UI remain custom.**

The original analysis said "don't adopt events core or addressing." With this re-assessment:
- **events core**: ✅ **Adopt** — Islamic features fit naturally via EventAttribute/Audience/TimeExpression; EAV sync is now package-provided
- **moderation**: ✅ **Adopt** — dedicated Ban/Block + ModerationAction models in new package
- **references**: ✅ **Adopt** — full Reference entity model in new package
- **membership**: ✅ **Adopt** — cross-cutting membership (applications, invitations, roles) for all entities via `aiarmada/membership`; replaces ~2,000 lines of custom claims code with generic Spatie-integrated package
- **communications**: ✅ **Critical adoption** — missed in original, covers notification engine + inbox
- **addressing**: ❌ **Still don't adopt** — geography ID mismatch remains
