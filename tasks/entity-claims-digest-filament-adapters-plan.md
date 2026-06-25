# Plan: Three Package Extractions

> **Goal:** Push the package coverage from ~70% to ~75% by extracting three features into generic, reusable packages.
> **Scope:** The three new packages ship in the `aiarmada` monorepo first; the ilmu360° app adopts them in a follow-up pass.

## 1. Summary

| Package | Custom lines absorbed | Type | Effort |
|---------|----------------------|------|--------|
| Split: `aiarmada/authz` (base) + slim `aiarmada/filament-authz` (adapter) | ~2,500 | Refactor + new base | 2–3 wk |
| Digest scheduling in `aiarmada/communications` | ~300 | Addition to existing package | 1 wk |
| `aiarmada/filament-moderation` + `aiarmada/filament-references` | ~200 × 2 = ~400 | New Filament adapters | 1 wk each |
| **Total absorbed** | **~3,200** | | **5–6 wk** |

**Net coverage move:** 70% → 75% (12,950 → 9,750 custom lines remaining in a full rewrite).

**Architectural change vs. original plan:** entity-claims is no longer a standalone package. It is a feature of a new base `aiarmada/authz` package, with `aiarmada/filament-authz` slimmed down to a Filament adapter on top. This:

- Removes the `EntityRoleResolver` / `EntityMemberGate` contract indirection — both packages ship together in practice
- Lets `aiarmada/authz` be usable in non-Filament apps (e.g. mobile API backends, headless services)
- Keeps `filament-authz`'s existing public API stable for the Filament-tied parts (plugin, resources, middleware, impersonation UI)

## 2. Why these three (and not the others)

The other remaining gaps were ruled out:

- **Public Livewire pages, MCP servers, donation channels, integration glue, data migrations, SavedSearch filter normalizer, ban UI, inbox integration** — all too app-specific or too small to justify package overhead.
- **Speaker thin model** — so thin that a generic package would be more boilerplate than value.
- **FCM/WhatsApp push channels** — each tiny and only valuable if multiple apps need them.

These three were picked because each has a clear generic shape, a clean application seam, and a measurable line-count win.

---

## 3. Package 1 (Refactored): Split `aiarmada/filament-authz` → `aiarmada/authz` (base) + slim `aiarmada/filament-authz` (adapter)

### 3.1 Why Split

The current `aiarmada/filament-authz` does two things:
1. **Generic Spatie Permission wrapper** — AuthzScope model, `Authz` facade, `PermissionRegistrar` config, team resolver, services, Octane safety. These are useful in any Laravel app.
2. **Filament-tied UI** — Filament plugin, resources, middleware, impersonation banner, middleware, forms, tables. These are Filament-specific.

Adding entity-claims (the per-instance authorization concept) to the same package as #1 makes sense conceptually. The single concern is "who is authorized to do what, scoped to which entity instance." But it shouldn't be tied to Filament, because mobile/headless apps need the base too.

**Resolution:** Extract #1 (with entity-claims baked in) into a new base `aiarmada/authz` package. Keep #2 in `aiarmada/filament-authz` as a thin Filament adapter on top.

### 3.2 New Package Structure

```
packages/authz/                                      # NEW base package
├── composer.json                                     # requires: commerce-support, spatie/laravel-permission
├── config/authz.php
├── database/migrations/
│   ├── 2000_01_01_000001_create_authz_scopes_table.php       # MOVED from filament-authz
│   ├── 2000_01_01_000002_create_permission_tables.php         # MOVED from filament-authz (Spatie teams)
│   ├── 2000_01_01_000003_create_entity_claims_table.php       # NEW
│   └── 2000_01_01_000004_create_entity_invitations_table.php # NEW
├── resources/views/
└── src/
    ├── AuthzServiceProvider.php
    ├── helpers.php
    ├── Actions/
    │   ├── SubmitEntityClaimAction.php
    │   ├── ApproveEntityClaimAction.php
    │   ├── RejectEntityClaimAction.php
    │   ├── CancelEntityClaimAction.php
    │   ├── InviteEntityMemberAction.php
    │   ├── AcceptEntityInvitationAction.php
    │   ├── RevokeEntityInvitationAction.php
    │   ├── AddEntityMemberAction.php
    │   ├── RemoveEntityMemberAction.php
    │   └── ChangeEntityMemberRoleAction.php
    ├── Authz.php                                    # MOVED from filament-authz (the facade class)
    ├── Concerns/                                    # MOVED
    │   └── (authz-related traits)
    ├── Console/                                     # MOVED
    │   ├── DiscoverCommand.php
    │   ├── GeneratePoliciesCommand.php
    │   ├── SeederCommand.php
    │   ├── SuperAdminCommand.php
    │   ├── SyncAuthzCommand.php
    │   └── (any non-Filament commands)
    ├── Contracts/
    │   ├── PostMembershipApprovalHook.php          # NEW (for app-specific side effects)
    │   └── EntityClaimNotifier.php                  # NEW (pluggable notification)
    ├── Events/
    │   ├── EntityClaimSubmitted.php
    │   ├── EntityClaimApproved.php
    │   ├── EntityClaimRejected.php
    │   ├── EntityMemberInvited.php
    │   └── EntityMemberAccepted.php
    ├── Facades/
    │   └── Authz.php                                # MOVED (the Spatie-friendly facade)
    ├── Guard/
    │   └── SessionGuard.php                         # MOVED
    ├── Models/
    │   ├── AuthzScope.php                           # MOVED (now usable standalone)
    │   ├── EntityClaim.php                          # NEW
    │   ├── EntityInvitation.php                     # NEW
    │   └── Concerns/UsesAuthzUuid.php               # MOVED
    ├── Services/
    │   ├── EntityDiscoveryService.php               # MOVED
    │   ├── PermissionKeyBuilder.php                 # MOVED
    │   ├── WildcardPermissionResolver.php           # MOVED
    │   ├── ImpersonateManager.php                   # MOVED (used by middleware)
    │   ├── DefaultEntityRoleResolver.php            # NEW (replaces contract with default impl)
    │   └── DefaultEntityMemberGate.php              # NEW
    ├── Support/
    │   ├── AuthzScopeContext.php                    # MOVED
    │   ├── AuthzScopeTeamResolver.php               # MOVED
    │   └── UserRoleChecker.php                      # MOVED
    └── Traits/
        └── HasMembers.php                           # NEW
```

```
packages/filament-authz/                            # THINNED to adapter only
├── composer.json                                    # requires: authz (new), filament, spatie/laravel-permission
├── config/filament-authz.php                        # only Filament-specific config
├── resources/views/
├── routes/                                          # (unchanged)
└── src/
    ├── FilamentAuthzServiceProvider.php             # refactored: delegates to AuthzServiceProvider
    ├── FilamentAuthzPlugin.php                      # unchanged
    ├── Forms/                                       # MOVED here (Filament forms)
    ├── Http/                                        # MOVED here (controllers + middleware)
    │   └── Middleware/ImpersonationBannerMiddleware.php
    ├── Resources/                                   # MOVED here (Filament resources)
    └── Tables/                                      # MOVED here (Filament tables)
```

### 3.3 Migration Strategy for the Split

The split is a non-trivial refactor because `filament-authz` currently mixes both. **No backward compatibility, no shims, no class_alias.** The old names stop existing in the same commit as the move.

Order:

1. **Create the new `aiarmada/authz` package** with everything that doesn't depend on Filament:
   - All non-Filament services (`Authz` class, `EntityDiscoveryService`, `PermissionKeyBuilder`, `WildcardPermissionResolver`, `ImpersonateManager`, `AuthzScopeContext`, `AuthzScopeTeamResolver`, `UserRoleChecker`)
   - All non-Filament console commands
   - `AuthzScope` model
   - `SessionGuard`
   - The Spatie teams migration
   - The authz_scopes migration
2. **Move namespaces from `AIArmada\FilamentAuthz\` to `AIArmada\Authz\`** for the moved code. **All in one commit.**
3. **Atomic grep-and-fix sweep across the entire monorepo** for any remaining imports of the moved classes under the old namespace:
   ```bash
   rg -l 'AIArmada\\FilamentAuthz\\(Authz|EntityDiscoveryService|PermissionKeyBuilder|WildcardPermissionResolver|ImpersonateManager|AuthzScopeContext|AuthzScopeTeamResolver|UserRoleChecker|SessionGuard|AuthzScope|Concerns|Console|Guard)' --type php
   ```
   Every match gets `AIArmada\FilamentAuthz\X` → `AIArmada\Authz\X` in the same commit.
4. **Update the `filament-authz` package** to:
   - Declare `"aiarmada/authz": "self.version"` as a require
   - Delete the moved code (it lives in `aiarmada/authz` now)
   - Keep only Filament-tied code (plugin, resources, forms, tables, middleware)
   - Update its own imports to use the new `AIArmada\Authz\` namespace
5. **Add entity-claims code** to the new `aiarmada/authz` package
6. **Update the monorepo's root composer.json** autoload for the new `AIArmada\Authz\` namespace
7. **Update `tests/Pest.php`** and `tests/src/TestCase.php` for the new package
8. **Run full monorepo tests** — any import the grep missed shows up as a fatal `Class not found` and gets fixed
9. **Tag the new package** and add to the split workflow matrix

### 3.4 Model Design (Polymorphic)

**EntityClaim** — public, claim-based join:

| Column | Type | Notes |
|--------|------|-------|
| `id` | UUID PK | |
| `subject_type` | string(191) | Polymorphic (Institution, Speaker, etc.) |
| `subject_id` | UUID | Polymorphic FK |
| `claimant_id` | UUID nullable | User FK |
| `status` | string | `pending`, `approved`, `rejected`, `cancelled` |
| `granted_role` | string nullable | Role slug assigned on approval |
| `justification` | text | Required |
| `reviewer_id` | UUID nullable | User FK |
| `reviewer_note` | text nullable | |
| `reviewed_at` | timestampTz nullable | |
| `cancelled_at` | timestampTz nullable | |
| `meta` | jsonb | Evidence URLs, custom data |
| | `timestampsTz()` | |

**EntityInvitation** — invitee-based join:

| Column | Type | Notes |
|--------|------|-------|
| `id` | UUID PK | |
| `subject_type` | string(191) | |
| `subject_id` | UUID | |
| `email` | string(255) | Lowercased |
| `role` | string | Role slug |
| `token` | string(64) | Hashed in storage, random per invite |
| `invited_by` | UUID | User FK |
| `expires_at` | timestampTz nullable | |
| `accepted_at` | timestampTz nullable | |
| `accepted_by` | UUID nullable | User FK |
| `revoked_at` | timestampTz nullable | |
| `revoked_by` | UUID nullable | User FK |
| | `timestampsTz()` | |

**HasMembers trait** — provides `members()` relation on any model:

```php
trait HasMembers
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            $this->entityMembersTable(),
        )->withPivot(['joined_at'])->withTimestamps();
    }
    
    public function memberInvitations(): MorphMany { ... }
    public function claims(): MorphMany { ... }
    
    private function entityMembersTable(): string
    {
        return Str::snake(class_basename($this)) . '_user';
    }
}
```

**Convention:** the pivot table name is `{snake_case_model_basename}_user`. The package ships **no migrations** for pivots — apps that want a `institution_user` pivot just create it (one UUID FK + one UUID FK + joined_at). This is the same convention the existing app uses.

### 3.5 The `PostMembershipApprovalHook` Contract

Unlike the original plan (which had multiple contracts), the only remaining contract is `PostMembershipApprovalHook` — the app binds it to fire after `AddEntityMember`, `RemoveEntityMember`, and `ChangeEntityMemberRole` actions:

```php
// In the package:
interface PostMembershipApprovalHook
{
    public function onMemberAdded(Model $subject, User $user): void;
    public function onMemberRemoved(Model $subject, User $user): void;
    public function onMemberRoleChanged(Model $subject, User $user, ?string $oldRole, ?string $newRole): void;
}

// In the app's service provider:
$this->app->bind(PostMembershipApprovalHook::class, PublicSubmissionLockHook::class);
```

The `AuthzScope` and `Authz::userCanInScope` are now first-class (no contract), so the app's `MemberPermissionGate` collapses into a thin wrapper or is deleted entirely.

### 3.6 Action Design (Event-Driven)

All actions use `lorisleiva/laravel-actions` and dispatch domain events for observability:

| Action | Inputs | Side Effects | Events |
|--------|--------|--------------|--------|
| `SubmitEntityClaimAction` | `Model $subject, User $user, string $justification, array $meta` | Creates `EntityClaim` (status=pending) | `EntityClaimSubmitted` |
| `ApproveEntityClaimAction` | `EntityClaim, User $reviewer, string $role, ?string $note` | Updates claim, calls `AddEntityMemberAction`, fires hook | `EntityClaimApproved` |
| `RejectEntityClaimAction` | `EntityClaim, User $reviewer, ?string $note` | Updates claim | `EntityClaimRejected` |
| `CancelEntityClaimAction` | `EntityClaim, User $user` | Updates claim | (none — user already knows) |
| `InviteEntityMemberAction` | `Model $subject, string $email, string $role, User $inviter, ?CarbonInterface $expiresAt` | Creates `EntityInvitation`, fires notifier | `EntityMemberInvited` |
| `AcceptEntityInvitationAction` | `EntityInvitation, User $user` | Validates + calls `AddEntityMemberAction` | `EntityMemberAccepted` |
| `RevokeEntityInvitationAction` | `EntityInvitation, User $actor` | Updates invitation | (none) |
| `AddEntityMemberAction` | `Model $subject, User $user, ?string $role` | Syncs pivot, fires `PostMembershipApprovalHook::onMemberAdded()` | (internal) |
| `RemoveEntityMemberAction` | `Model $subject, User $user` | Detaches pivot, fires hook | (internal) |
| `ChangeEntityMemberRoleAction` | `Model $subject, User $user, ?string $role` | Updates pivot role, fires hook | (internal) |

### 3.7 Config

```php
// config/authz.php
return [
    'database' => [
        'table_prefix' => env('AUTHZ_TABLE_PREFIX', ''),
        'tables' => [
            'roles' => env('AUTHZ_TABLE_ROLES', 'roles'),
            'permissions' => env('AUTHZ_TABLE_PERMISSIONS', 'permissions'),
            'model_has_permissions' => env('AUTHZ_TABLE_MODEL_HAS_PERMISSIONS', 'model_has_permissions'),
            'model_has_roles' => env('AUTHZ_TABLE_MODEL_HAS_ROLES', 'model_has_roles'),
            'role_has_permissions' => env('AUTHZ_TABLE_ROLE_HAS_PERMISSIONS', 'role_has_permissions'),
            'scopes' => env('AUTHZ_TABLE_SCOPES', 'authz_scopes'),
            'entity_claims' => env('AUTHZ_TABLE_ENTITY_CLAIMS', 'entity_claims'),
            'entity_invitations' => env('AUTHZ_TABLE_ENTITY_INVITATIONS', 'entity_invitations'),
        ],
        'json_column_type' => env('AUTHZ_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'jsonb')),
    ],
    'invitations' => [
        'token_length' => 64,
        'hash_tokens' => true,
        'default_expiry_days' => 14,
    ],
    'claims' => [
        'claimable_subject_types' => [],          // e.g. ['institution', 'speaker']
        'evidence_collection' => 'evidence',
        'evidence_max_files' => 8,
        'evidence_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],
    'features' => [
        'entity_claims' => [
            'enabled' => env('AUTHZ_ENTITY_CLAIMS_ENABLED', true),
        ],
        'owner' => [
            'enabled' => env('AUTHZ_OWNER_ENABLED', true),
        ],
    ],
];
```

### 3.8 Tests (target ~60 tests, ~250 assertions)

- `EntityClaimTest` (10): CRUD, casts, relationships, factory
- `EntityInvitationTest` (8): CRUD, token generation, expiry math
- `SubmitEntityClaimActionTest` (6)
- `ApproveEntityClaimActionTest` (5)
- `RejectEntityClaimActionTest` (3)
- `CancelEntityClaimActionTest` (3)
- `InviteEntityMemberActionTest` (5)
- `AcceptEntityInvitationActionTest` (6)
- `RevokeEntityInvitationActionTest` (3)
- `AddEntityMemberActionTest` (4)
- `RemoveEntityMemberActionTest` (3)
- `ChangeEntityMemberRoleActionTest` (3)
- `HasMembersTraitTest` (4)
- `InstallationTest` (2)
- (Plus all the existing `filament-authz` tests that get ported: ~50)

### 3.9 Adoption in ilmu360°

When adopting, the app:
1. Adds `"aiarmada/authz": "dev-main"` to `composer.json`
2. Removes `"aiarmada/filament-authz"` direct deps if any; gets it transitively via `aiarmada/authz`
3. Publishes `config/authz.php` and tweaks `claimable_subject_types = ['institution', 'speaker']`
4. Binds the `PostMembershipApprovalHook`:
   ```php
   $this->app->bind(PostMembershipApprovalHook::class, PublicSubmissionLockHook::class);
   ```
5. Migrates `MembershipClaim` → `App\Models\MembershipClaim extends AIArmada\Authz\Models\EntityClaim` (or replaces with `EntityClaim` directly)
6. Migrates `MemberInvitation` → same pattern
7. Rewires the 4 `MembersRelationManager` Filament tables
8. Rewires the 4 `MemberInvitationsRelationManager` Filament tables
9. Rewires the API controllers and Livewire page
10. Rewires the 4 `members()` accessors on Institution/Speaker/Event/Reference to use the `HasMembers` trait
11. **Deletes**: `app/Support/Authz/MemberPermissionGate.php`, `app/Support/Authz/MemberRoleCatalog.php` (or replaces with `App\Models\RoleCatalog extends DefaultEntityRoleResolver`), `app/Support/Authz/MemberRoleScopes.php`, `app/Support/Authz/MemberInvitationGate.php`, `app/Support/Authz/ScopedMemberRoleSeeder.php`
12. Adds tests for the integration

Estimated deletion: ~2,500 lines (same as before).
Estimated addition in app: ~150 lines (config tweaks, hook implementation, model migration scripts). Slightly less than the original plan because the contracts are gone.

**Net custom line delta: −2,350 lines** (2,500 absorbed, 150 added for integration glue).

---

## 4. Package 2: Digest Scheduling (in `aiarmada/communications`)

### 4.1 Domain Description

Periodic aggregation of low-priority notifications into a single daily or weekly digest. The `communications` package already has:

- `NotificationFamily` enum with `DigestDaily` and `DigestWeekly` cases (the slot is reserved)
- `NotificationInbox` model and `NotificationInboxService` create path
- `DispatchInboxNotificationAction` (creates a Communication + NotificationInbox in a transaction)
- A `PruneCommunicationDataAction` analog (memory-safe batch with `each` + `DB::transaction`)

Missing pieces to add:
- `CommunicationCadence` enum (off, instant, daily, weekly) — used to mark the inbox row's delivery intent
- A `delivery_cadence` column on `notification_inboxes` (nullable string)
- A new `DispatchDigestNotificationsAction` (the 15-minute windowing + per-user timezone + grouping)
- A `MarkDigestSourcesDeliveredAction` (retroactively close the source rows when a digest is delivered)
- A `DigestWindowResolver` service (window math: only fire when the user is within `[scheduled, scheduled+15min)` in their local timezone)
- A `DispatchDigestsCommand` (manual trigger; the scheduler entry goes in the app's `routes/console.php`)
- Two new `NotificationFamily` cases: `DigestDaily`, `DigestWeekly` (already there, just wire to actions)
- An opt-in `notification_settings` migration (since the existing app has its own `notification_settings` table that is *different* from the package's, this becomes an app responsibility — package just expects the fields as columns or config)

### 4.2 Package Architecture (additions only)

```
packages/communications/src/                  # ADDITIONS
├── Enums/
│   └── CommunicationCadence.php              # NEW
├── Actions/
│   ├── DispatchDigestNotificationsAction.php # NEW
│   └── MarkDigestSourcesDeliveredAction.php   # NEW
├── Services/
│   ├── DigestWindowResolver.php              # NEW
│   └── DigestDispatcher.php                  # NEW (orchestrator)
├── Console/Commands/
│   ├── DispatchDigestsCommand.php            # NEW
│   └── DispatchDigestDailyCommand.php        # NEW (alt shortcut)
│   └── DispatchDigestWeeklyCommand.php       # NEW (alt shortcut)
├── Events/
│   ├── DigestDispatched.php                  # NEW
│   └── DigestSourceConsumed.php              # NEW
└── Models/NotificationInbox.php              # ADD `delivery_cadence` cast

packages/communications/database/migrations/
└── 2000_01_01_000017_add_delivery_cadence_to_notification_inboxes.php
    # NEW: $table->string('delivery_cadence')->nullable()->index()
```

### 4.3 `CommunicationCadence` Enum

```php
enum CommunicationCadence: string
{
    case Off = 'off';
    case Instant = 'instant';
    case Daily = 'daily';
    case Weekly = 'weekly';
    
    public function isDigest(): bool
    {
        return $this === self::Daily || $this === self::Weekly;
    }
    
    public function label(): string { /* match */ }
}
```

### 4.4 `DigestWindowResolver` Service

Pure window math. Takes a user, a cadence, and a base timestamp; returns `[start, end]` UTC or null if not in window.

```php
final readonly class DigestWindowResolver
{
    public function __construct(
        private CarbonImmutable $now,           // injected for testability
    ) {}
    
    public function resolve(
        string $timezone,
        string $deliveryTime,                   // '08:00:00'
        ?int $weeklyDay,                        // 1-7, ISO
        CommunicationCadence $cadence,
    ): ?array {
        if ($cadence === CommunicationCadence::Off || $cadence === CommunicationCadence::Instant) {
            return null;
        }
        
        $local = $this->now->setTimezone($timezone);
        $scheduled = $local->setTimeFromTimeString($deliveryTime ?: '08:00:00');
        
        if ($cadence === CommunicationCadence::Weekly
            && $local->dayOfWeekIso !== ($weeklyDay ?? 1)) {
            return null;
        }
        if ($local->lessThan($scheduled)) {
            return null;
        }
        if ($scheduled->diffInMinutes($local) >= 15) {
            return null;
        }
        
        $start = $cadence === CommunicationCadence::Weekly
            ? $scheduled->subWeek()
            : $scheduled->subDay();
        
        return ['start' => $start->utc(), 'end' => $scheduled->utc()];
    }
}
```

### 4.5 `DispatchDigestNotificationsAction`

```php
final readonly class DispatchDigestNotificationsAction
{
    public function __construct(
        private DigestWindowResolver $windowResolver,
        private DigestDispatcher $dispatcher,
        private DigestSourceQuery $sourceQuery,
        private CarbonImmutable $now,
    ) {}
    
    public function handle(CommunicationCadence $cadence): int
    {
        if (! $cadence->isDigest()) return 0;
        
        $count = 0;
        foreach ($this->iterableEligibleUsers($cadence) as $user) {
            $window = $this->windowResolver->resolve(
                timezone: $user->timezone ?? config('app.timezone'),
                deliveryTime: $user->digest_delivery_time ?? config('communications.defaults.digest_delivery_time', '08:00:00'),
                weeklyDay: $user->digest_weekly_day ?? null,
                cadence: $cadence,
            );
            
            if ($window === null) continue;
            
            foreach ($this->sourceQuery->groupBy($user, $cadence, $window) as $group) {
                $count += $this->dispatcher->dispatch(
                    user: $user,
                    sources: $group,
                    cadence: $cadence,
                    window: $window,
                );
            }
        }
        
        return $count;
    }
}
```

### 4.6 `MarkDigestSourcesDeliveredAction`

```php
final readonly class MarkDigestSourcesDeliveredAction
{
    public function handle(NotificationInbox $digest, CommunicationDelivery $delivery): void
    {
        $sourceIds = $digest->data['source_ids'] ?? [];
        if ($sourceIds === []) return;
        
        DB::transaction(function () use ($sourceIds, $delivery, $digest): void {
            foreach ($sourceIds as $sourceId) {
                $source = NotificationInbox::query()->find($sourceId);
                if ($source === null) continue;
                
                CommunicationDelivery::query()->firstOrCreate(
                    ['fingerprint' => sha1('digest-source|'.$source->id.'|'.$digest->id)],
                    [
                        'communication_id' => $digest->communication_id,
                        'recipient_type' => $source->recipient_type,
                        'recipient_id' => $source->recipient_id,
                        'family' => $source->family,
                        'trigger' => $source->trigger,
                        'channel' => $delivery->channel,
                        'status' => DeliveryStatus::Delivered,
                        'sent_at' => now(),
                        'delivered_at' => now(),
                    ],
                );
                
                $source->forceFill(['read_at' => now()])->save();
            }
        });
    }
}
```

### 4.7 Config Additions

```php
// config/communications.php (additions)
'digests' => [
    'enabled' => env('COMMUNICATIONS_DIGESTS_ENABLED', false),
    'default_delivery_time' => env('COMMUNICATIONS_DIGEST_DEFAULT_TIME', '08:00:00'),
    'default_weekly_day' => (int) env('COMMUNICATIONS_DIGEST_DEFAULT_WEEKLY_DAY', 1), // Mon
    'digest_families' => [
        // 'followed_content',
        // 'saved_search_matches',
    ],
    'window_minutes' => 15,
    'batch_size' => 1000,
],
```

### 4.8 Tests (target ~30 tests, ~120 assertions)

- `CommunicationCadenceTest` (4): enum cases, isDigest()
- `DigestWindowResolverTest` (12): all four cadences, timezone math, edge cases at boundaries
- `DispatchDigestNotificationsActionTest` (8): full flow, no-op when not in window, no-op when no sources
- `MarkDigestSourcesDeliveredActionTest` (4): source consumption
- `DispatchDigestsCommandTest` (2): --cadence flag, --dry-run

### 4.9 Adoption in ilmu360°

The existing app has its **own** notification pipeline (`notification_messages` + `NotificationSetting` + `NotificationEngine`). Adopting the package's digest feature means:

1. **Bridges the two pipelines** — the app's `DispatchNotificationDigests` job becomes a thin wrapper around `DispatchDigestNotificationsAction`. Or: the app deletes its own `DispatchNotificationDigests` and uses the package's.
2. **The `NotificationSetting` model** stays in the app (it has app-specific fields). The package's `DigestSourceQuery` reads from the package's `NotificationInbox` table, not the app's `notification_messages`. **Data migration** is needed: a one-time job that re-emits past `notification_messages` rows into the `notification_inboxes` table for the relevant families.
3. **The `communication_deliveries` tracking** replaces the app's `NotificationDelivery` table for the digest path. A bridge job (`markDigestSourcesDelivered` hook) keeps the app's `NotificationDelivery` rows in sync during the transition.

This is the **most invasive** of the three adoptions because it touches the app's notification center wholesale. Two options:

**Option A — Parallel tracks (low risk):** Keep the app's digest job, treat the package's digest as an opt-in alternative. Add a config flag to disable the package's job by default. The app migrates over time.

**Option B — Full cutover (higher risk, cleaner result):** Delete `app/Jobs/DispatchNotificationDigests.php` and replace with `DispatchDigestNotificationsAction`. Delete the app's `markDigestSourcesDelivered` and replace with the package's action. Update `NotificationDeliveryLogger` to call the package's `MarkDigestSourcesDeliveredAction` on digest deliveries. Migrate `notification_messages` rows for digest-eligible families into `notification_inboxes`.

**Recommendation:** Option A for first adoption, then Option B after the package has been in production for a month.

### 4.10 Net Custom Line Delta

**Option A** (parallel, gradual cutover): +200 lines (new config + 1 service provider binding + 1 console entry), 0 deleted.
**Option B** (full cutover): −500 lines deleted, +200 added, net **−300 lines**.

If going with Option A, this isn't a "line reduction" win in the gap register — it's a "package is now available for any new project" win. Worth doing for the package's existence alone, since the slot is already reserved in the enum.

---

## 5. Package 3: `aiarmada/filament-moderation` + `aiarmada/filament-references`

### 5.1 Domain Description

Two thin Filament adapter packages, each following the `aiarmada/filament-communications` pattern. They give admins a working CRUD UI for the existing `aiarmada/moderation` and `aiarmada/references` packages.

### 5.2 `aiarmada/filament-moderation`

```
packages/filament-moderation/
├── composer.json
├── config/filament-moderation.php
├── resources/views/
└── src/
    ├── FilamentModerationServiceProvider.php
    ├── FilamentModerationPlugin.php
    ├── Resources/
    │   ├── BlockResource.php                 # main view
    │   ├── ModerationActionResource.php      # audit trail
    │   └── RelationManagers/
    │       └── ModerationActionsRelationManager.php
    └── Pages/
        ├── ListBlocks.php
        ├── CreateBlock.php
        ├── EditBlock.php
        ├── ListModerationActions.php
        └── ViewModerationAction.php
```

**BlockResource** — `Table` with columns: blockable (polymorphic badge), blocker (via `hasBlocks` relation back to), reason, status badge, expires_at, lifted_at, created_at. Actions: lift (sets `lifted_at` + `status = lifted`), delete, view audit. Filters: status, reason, blockable_type, created_at range.

**ModerationActionResource** — read-only table of all moderation actions, with a filterable audit trail. Columns: action_type, subject (polymorphic), actor, reason, meta, created_at. No actions (records are immutable).

**FilamentModerationPlugin** (Filament v5 plugin pattern) — registers the two resources, controls default sort/visibility, exposes a `discoverScope()` config.

**Config:**
```php
return [
    'navigation' => [
        'group' => 'Moderation',
        'sort' => 10,
        'icon' => 'heroicon-o-shield-check',
    ],
    'resources' => [
        'blocks' => [
            'enabled' => true,
            'default_sort' => '-created_at',
            'paginate' => 25,
        ],
        'actions' => [
            'enabled' => true,
            'paginate' => 50,
        ],
    ],
];
```

**Tests:** ~12 tests, ~60 assertions (mostly rendering + filter assertions).

**Composer:**
```json
{
    "name": "aiarmada/filament-moderation",
    "require": {
        "php": "^8.4",
        "aiarmada/moderation": "self.version",
        "aiarmada/filament-authz": "self.version",
        "aiarmada/commerce-support": "self.version",
        "filament/filament": "^5.6.7"
    }
}
```

### 5.3 `aiarmada/filament-references`

```
packages/filament-references/
├── composer.json
├── config/filament-references.php
├── resources/views/
└── src/
    ├── FilamentReferencesServiceProvider.php
    ├── FilamentReferencesPlugin.php
    ├── Resources/
    │   └── ReferenceResource.php
    └── Pages/
        ├── ListReferences.php
        ├── CreateReference.php
        ├── EditReference.php
        └── ViewReference.php
```

**ReferenceResource** — `Table` with columns: title, slug, type, status, author, publisher, year, isbn, has_parts (badge), created_at. Actions: verify, reject, view hierarchy. Filters: type, status, year, has_parts. Form: standard reference fields + nested `HasReferenceParts` Repeater for Jilid/Juz/Surah/etc.

**Config:**
```php
return [
    'navigation' => [
        'group' => 'Catalog',
        'sort' => 20,
    ],
    'resources' => [
        'references' => [
            'enabled' => true,
            'default_sort' => '-created_at',
        ],
    ],
];
```

**Tests:** ~10 tests, ~50 assertions.

**Composer:**
```json
{
    "name": "aiarmada/filament-references",
    "require": {
        "php": "^8.4",
        "aiarmada/references": "self.version",
        "aiarmada/filament-authz": "self.version",
        "aiarmada/commerce-support": "self.version",
        "filament/filament": "^5.6.7"
    }
}
```

### 5.4 Adoption in ilmu360°

The app already has its own `Reference` and `Ban/Block` Filament resources. Adopting these adapter packages means:

1. **Add to composer.json** (path repos)
2. **Register the new `FilamentModerationServiceProvider` and `FilamentReferencesServiceProvider`** in `bootstrap/providers.php` (or in the existing `App\Providers\Filament\AdminPanelProvider`)
3. **Choose between old vs new resource**: the app can either keep its existing custom resources, swap to the package's, or extend them. Recommended: extend the package's `ReferenceResource` and `BlockResource` to add app-specific columns/forms.
4. **Add tests** for the integration.

**Net custom line delta:** ~−100 lines each (the app's existing custom `Reference` Filament resource is ~300 lines, the package's is similar; the win is mostly from the moderation one because the app has a much thinner `Block` UI at present).

### 5.5 Split Workflow

Add to `.github/workflows/monorepo-split.yml`:
```yaml
- entity-claims
- filament-moderation
- filament-references
```

---

## 6. Implementation Order

### Phase 1: Build the packages (5–6 wk, monorepo-only)

1. **Week 1–2:** Split `aiarmada/filament-authz` into `aiarmada/authz` (base) + thin `aiarmada/filament-authz` (adapter)
   - Create new `aiarmada/authz` composer package
   - Move all non-Filament code (services, models, commands, support, migrations)
   - Move namespaces from `AIArmada\FilamentAuthz\` → `AIArmada\Authz\` for moved code (no backward compat)
   - Update `filament-authz` to require `authz` and re-export key classes
   - Update root composer.json autoload, `tests/Pest.php`, `tests/src/TestCase.php`
   - Run full monorepo tests — must be a no-op refactor
   - Pint + PHPStan pass

2. **Week 2–3:** Add entity-claims to `aiarmada/authz`
   - Migrations (EntityClaim + EntityInvitation)
   - Models (EntityClaim, EntityInvitation, UsesAuthzUuid trait extension)
   - Enums (EntityClaimStatus, EntityInvitationStatus, EntityMemberRole)
   - Contracts (PostMembershipApprovalHook, EntityClaimNotifier) + default impls
   - HasMembers trait
   - 4 simple actions (Cancel, Reject, Revoke, Remove)
   - 25 tests pass

3. **Week 3:** Continue entity-claims
   - The 6 complex actions (Submit, Approve, Invite, Accept, Add, ChangeRole)
   - Domain events
   - Add remaining tests to 60 total

4. **Week 4:** Digest scheduling in `aiarmada/communications`
   - CommunicationCadence enum
   - Migration for delivery_cadence column on notification_inboxes
   - DigestWindowResolver
   - DigestDispatcher
   - DispatchDigestNotificationsAction
   - MarkDigestSourcesDeliveredAction
   - DispatchDigestsCommand
   - 30 tests pass

5. **Week 4–5:** `aiarmada/filament-moderation`
   - Plugin scaffold
   - BlockResource + ModerationActionResource
   - 12 tests

6. **Week 5:** `aiarmada/filament-references`
   - Plugin scaffold
   - ReferenceResource
   - 10 tests

7. **Week 5–6:** Audit pass
   - Full Pint + PHPStan + pest on the monorepo
   - Update `monorepo-split.yml` matrix (add `authz`)
   - Add CHANGELOG entries
   - Update split workflow
   - Tag releases

### Phase 2: Adopt in ilmu360° (2–3 wk, app-only, separate PRs)

7. **Week 6:** Adopt `aiarmada/authz` (refactored from `filament-authz` split) + entity-claims (Option A: parallel, no cutover)
   - Add `aiarmada/authz` to composer
   - Remove `aiarmada/filament-authz` direct require (it's now transitive via `authz`)
   - Update the app's `App\Models\AuthzScope` (if it has one) to extend `AIArmada\Authz\Models\AuthzScope`
   - Update the app's `app/Support/Authz/*` classes to either extend or replace with the new base classes
   - Bind `PostMembershipApprovalHook` to `PublicSubmissionLockHook`
   - Wire `MembershipClaim` → `EntityClaim` (keep app's model name, just have it extend the package's)
   - Wire `MemberInvitation` → `EntityInvitation` (same pattern)
   - Add app's role definitions to the new `MemberRoleCatalog` (which now extends `DefaultEntityRoleResolver`)
   - Run app tests — must be a no-op refactor for the authz split
   
8. **Week 6–7:** Adopt `filament-moderation` + `filament-references`
   - Add to composer
   - Replace app's `Block` resource with package's (extending for app-specific needs)
   - Replace app's `Reference` resource with package's (extending)
   - Run app tests

9. **Week 7:** Adopt digest scheduling (Option A)
   - Add config flag `communications.digests.enabled = false` by default
   - Add the scheduler entries pointing at the new command
   - Keep the old `DispatchNotificationDigests` job running until next iteration

### Phase 3: Cutover (separate sprint, 1–2 wk)

10. **Week 8–9:** Digest cutover (Option B)
    - Migrate `notification_messages` rows for digest families into `notification_inboxes`
    - Update `NotificationDeliveryLogger` to call the package's `MarkDigestSourcesDeliveredAction`
    - Delete `app/Jobs/DispatchNotificationDigests.php`
    - Delete `app/Services/Notifications/NotificationEngine::createDigestMessage()` and the digest branch of `dispatchToUser()`
    - Update app's scheduler entries
    - Run app tests, monitor for 1 week

11. **Week 9–10:** `entity-claims` cutover (remove the parallel app models)
    - Delete `app/Models/MembershipClaim.php` → replace with `App\Models\MembershipClaim extends AIArmada\EntityClaims\Models\EntityClaim`
    - Delete `app/Models/MemberInvitation.php` → same pattern
    - Delete `app/Actions/Membership/*` → replace with calls to package actions
    - Delete `app/Support/Authz/MemberPermissionGate.php` etc. → bind contracts
    - Delete the 4 `MembersRelationManager` files → replace with thin subclasses of `AIArmada\EntityClaims\Filament\MembersRelationManager` (if we add that) or keep them as thin Filament widgets over the package
    - Delete the API controllers and the Livewire page → replace with thin wrappers

---

## 7. Verification Plan (per phase)

### Phase 1 (monorepo)
- `composer validate --strict --no-check-publish` for each new package
- `vendor/bin/pest --parallel --compact` for each new package → all green
- `vendor/bin/pest --parallel --compact` for full monorepo (regression) → 700+ tests, all green
- `vendor/bin/pint --test --format=agent` → clean
- `vendor/bin/phpstan analyse --level=6 --ansi` → 0 errors
- `rg -n -- "constrained\(|cascadeOnDelete\(" packages/entity-claims/database packages/filament-moderation packages/filament-references` → no matches
- `rg -n -- "softDeletes\(\)|SoftDeletes" packages/entity-claims packages/filament-moderation packages/filament-references` → no matches
- `git diff --check` → clean
- Split workflow dry-run via a `v0.0.0-test` tag on a throwaway branch

### Phase 2 (ilmu360° adoption, Option A)
- `vendor/bin/pint --dirty --test --format=agent` → clean
- `vendor/bin/phpstan analyse --ansi --no-progress` → pass
- `vendor/bin/pest --parallel --compact` for affected app test files → all green
- Full Pest suite (regression) → 2,100+ tests, all green
- Browser smoke (admin panel) for new `Block` and `Reference` resources
- Browser smoke (member invitation accept page) — verify the post-hook still fires

### Phase 3 (cutover)
- Same as Phase 2 + targeted:
  - All `MembershipClaim` test scenarios still pass
  - All `MemberInvitation` test scenarios still pass
  - A test that creates a claim, approves it, and verifies `PublicSubmissionLockService` re-evaluates
  - A test that sends a digest via the package, verifies sources are consumed, and verifies the legacy `notification_messages` table is not double-dispatched
  - Migration test: pre-existing `notification_messages` rows for digest families are correctly migrated to `notification_inboxes` and the migration is idempotent

---

## 8. Risks and Mitigations

| Risk | Impact | Mitigation |
|------|--------|-----------|
| `filament-authz` split breaks existing monorepo consumers | High | **No shims. No aliases. No backward compatibility.** The split is a hard cut: `AIArmada\FilamentAuthz\Authz` → `AIArmada\Authz\Authz` and the old names stop existing in the same commit. Implementation step: after moving the files, run a `rg` sweep across the entire monorepo for any remaining `AIArmada\FilamentAuthz\` imports of the moved classes and update them atomically before committing. The monorepo's own test suite catches any miss on the first run. |
| App's `MemberRoleCatalog` is tightly coupled to `FilamentAuthz` | Medium | The new `AIArmada\Authz\Services\DefaultEntityRoleResolver` provides the base; the app's `MemberRoleCatalog` extends it and adds the 4 entity-type definitions |
| `HasMembers` trait collides with existing `members()` accessors on the 4 entities | Low | The trait only adds a `members()` relation if the model doesn't already define one. Or: rename the app's accessors and have the trait own the canonical method |
| Digest migration corrupts legacy data | High | Write a non-destructive migration: copy `notification_messages` rows for digest families into `notification_inboxes`, then mark the originals as `dispatched_at` so they don't re-fire. Run side-by-side for 2 weeks, then delete the originals |
| Filament resource extension is awkward (the app's `Reference` resource has a lot of custom form fields) | Low | Extend the package's `ReferenceResource` with the app's additional form components and columns. The package provides the base, the app layers on top |
| Split workflow matrix needs a new entry for `authz` | Low | Add `authz`, `filament-moderation`, `filament-references` to `monorepo-split.yml` matrix and re-test the split with a dummy tag |
| `CommunicationCadence` enum values conflict with app's `NotificationCadence` | Medium | The package uses different backing values (`off`/`instant`/`daily`/`weekly` align, but the app's `PendingNotification` already has a `delivery_cadence` string column). Adopt the package's enum, port the app's column to match |

---

## 9. Done Criteria

- All new packages live in the monorepo with passing tests + PHPStan + Pint:
  - `aiarmada/authz` (refactored split from `filament-authz` + new entity-claims)
  - `aiarmada/filament-authz` (slimmed to adapter only)
  - `aiarmada/communications` (existing, gets digest feature)
  - `aiarmada/filament-moderation` (new)
  - `aiarmada/filament-references` (new)
- `monorepo-split.yml` matrix includes the `authz` entry (in addition to existing entries)
- A v0.1.x release tag successfully splits all new packages to `AIArmada/<name>` GitHub repos and triggers Packagist update
- ilmu360° app:
  - Uses `aiarmada/authz` (with entity-claims feature) for all claim/invitation flows (Phase 3 complete)
  - Uses `filament-moderation` + `filament-references` for admin CRUD (Phase 2 complete)
  - Uses package's digest scheduling (Phase 3 complete)
  - All 2,100+ existing tests still pass
  - PHPStan + Pint clean
- Updated `docs/commerce-package-readiness-reassessment.md` (Section 8.5) reflects the new packages

## 10. Out of Scope (deliberately)

- **Speaker thin model** extraction
- **FCM/WhatsApp push channels** extraction
- **Public Livewire pages** — stays app-specific
- **MCP servers** — stays app-specific (built on `laravel/mcp`)
- **Donation channels** — Islamic charity-specific
- **SavedSearch filter normalizer** — too app-specific
- **Data migration scripts** — one-time, app-only
