# Filament Affiliates — Lifecycle Audit

## 1. Executive Summary

`filament-affiliates` owns zero database tables. It is a pure Filament UI layer with 14 Resources, 3 admin Pages, an affiliate self-service Portal, and 6 Widgets. All domain models, migrations, enums, and state machines live in `packages/affiliates`.

The audit below focuses on **Filament-layer lifecycle gaps**: form/table inconsistencies, missing filters, action gaps, widget query problems, and owner scoping holes.

---

## 2. Filament-Layer Owner Scoping Gaps

These `getEloquentQuery()` methods lack proper owner scoping:

| Resource | File | Issue |
|---|---|---|
| `AffiliateConversionResource` | Resources/AffiliateConversionResource.php | Returns unscoped `parent::getEloquentQuery()` — no owner scoping |
| `AffiliateLinkResource` | Resources/AffiliateLinkResource.php | Returns unscoped query — no owner scoping |
| `AffiliateRankHistoryResource` | Resources/AffiliateRankHistoryResource.php | Returns unscoped query — no owner scoping |
| `AffiliateSupportTicketResource` | Resources/AffiliateSupportTicketResource.php | Returns `$modelQuery` without `->forOwner()` when owner mode is enabled |
| `AffiliateTaxDocumentResource` | Resources/AffiliateTaxDocumentResource.php | Same gap — no `->forOwner()` when owner mode is enabled |
| `AffiliateFraudSignalResource` | Resources/AffiliateFraudSignalResource.php | Manually reimplements owner scoping via `whereHas('affiliate', ...)` instead of using `OwnerUiScope::apply()`; same pattern in `getNavigationBadge()` |
| `AffiliateNetworkResource` | Resources/AffiliateNetworkResource.php | No filters at all |

---

## 3. Form Field Inconsistencies

### 3.1 Toggle vs Select for lifecycle fields

The domain `affiliates` package uses `is_active` / `is_public` / `is_verified` booleans. The Filament layer must align form controls with the domain's post-refactor column types:

| Resource | Current Form Field | Domain Target | Filament Action |
|---|---|---|---|
| `AffiliateLinkResource` | `Toggle('is_active')` | `status` enum (active/inactive) | Replace with `Select('status')` + `BadgeColumn` in table |
| `AffiliateCommissionTemplateResource` | No status field | `status` enum (active/inactive) | Add `Select('status')` to form + `BadgeColumn` + `SelectFilter` to table |
| `AffiliateProgramResource` | `Toggle('is_public')` / `TernaryFilter` | `visibility` enum (public/private/invite_only) | Replace Toggle with `Select('visibility')`; replace TernaryFilter with `SelectFilter` |
| `AffiliatePayoutMethodResource` (relation manager) | `IconColumn('is_verified')` / `Toggle` | `verified_at` timestamp (non-null = true) | Replace `IconColumn` with computed check on `verified_at` |

### 3.2 Status Select allows arbitrary transitions

`AffiliateForm::status` and `AffiliateConversionForm::status` use `Select` with all state-machine options. There is no UI-level guard to limit choices to valid transitions from the current state. The domain state machine enforces this server-side, but the Filament form should ideally filter the options to valid transitions only.

### 3.3 Ad-hoc string statuses → no enum-backed Select

| Resource | Current | UI Impact |
|---|---|---|
| `AffiliateSupportTicketResource` | `status` as ad-hoc string values (`open`, `pending`, `resolved`, `closed`) | Form `Select` options, table `BadgeColumn`, and `SelectFilter` use hand-written string values. No `closed_at` display. |
| `AffiliateTaxDocumentResource` | `status` as ad-hoc string values (`pending_info`, `generated`, `sent`) | Same pattern. |

---

## 4. Table Filter Gaps

### 4.1 Missing lifecycle filters

| Resource | Gap |
|---|---|
| `AffiliateTouchpointResource` | No status filter; only "recent" filter exists |
| `AffiliateRankResource` | No status filter — ranks cannot be filtered by active/inactive |
| `AffiliateRankHistoryResource` | Read-only log, but no date-range filter on `qualified_at` |
| `AffiliateCommissionTemplateResource` | No status filter (no `is_active` equivalent) |
| `AffiliateNetworkResource` | No filters at all |

### 4.2 Filter type mismatch

| Resource | Field | Current Filter | Should Be |
|---|---|---|---|
| `AffiliateProgramResource` | `is_public` visibility | `TernaryFilter` on boolean | `SelectFilter` on `visibility` (public/private/invite_only) |
| `AffiliateLinkResource` | `is_active` | `TernaryFilter` on boolean | `SelectFilter` on `status` enum |

---

## 5. Action Gaps

### 5.1 Missing state transition actions

| Resource | Gap |
|---|---|
| `AffiliateConversionResource` | Row-level Approve/Reject actions exist but no Paid transition action is visible in the table |
| `AffiliateSupportTicketResource` | No row-level Close action — tickets must be opened and status changed via form |
| `AffiliateRankResource` | No Activate/Deactivate actions — ranks cannot be enabled/disabled from the table |

### 5.2 Action visibility uses boolean checks

| Resource | Action | Current Visible Gate | Should Be |
|---|---|---|---|
| `AffiliatePayoutMethodResource` (RM) | Mark Verified | `! $record->is_verified` | `$record->verified_at === null` |
| `AffiliateLinkResource` | Activate/Deactivate | `$record->is_active` | `$record->status === Active` |

---

## 6. Widget Query Inconsistencies

### 6.1 Navigation badges use unscoped queries

`AffiliateFraudSignalResource::getNavigationBadge()` manually reimplements owner scoping via `whereHas('affiliate', ...)` instead of consistent `OwnerUiScope::apply()` pattern.

### 6.2 Navigation badge caching inconsistency

Only some resources use `Cache::remember()` for navigation badges. Others run uncached counts on every page load. Verify caching strategy is consistent across all 14 resources.

---

## 7. Filament-Specific Changes After Domain Refactor

When the domain `affiliates` package completes its lifecycle column refactors, these Filament surfaces need updates:

| Domain Change | Filament Surfaces to Update |
|---|---|
| `affiliate_links.is_active` → `status` enum | `AffiliateLinkResource`: form Toggle→Select, table IconColumn→BadgeColumn, TernaryFilter→SelectFilter |
| `affiliate_commission_rules.is_active` → `status` enum | RM table: IconColumn→BadgeColumn, filter→SelectFilter |
| `affiliate_commission_templates.is_active` → `status` enum | `AffiliateCommissionTemplateResource`: add form Select, add table BadgeColumn + SelectFilter |
| `affiliate_training_modules.is_active` → `status` enum | RM table: same pattern |
| `affiliate_programs.is_public` → `visibility` enum | `AffiliateProgramResource`: form Toggle→Select, table + filters |
| `affiliate_payout_methods.is_verified` dropped | RM: replace IconColumn + action visibility with `verified_at` checks |
| `affiliate_support_tickets` gains `closed_at` | `AffiliateSupportTicketResource`: add `closed_at` column to table + infolist |
| `affiliate_conversions` gains `paid_at`/`rejected_at` | `AffiliateConversionResource`: add columns + display in timeline |
| `affiliate_fraud_signals` gains `owner` morphs | `AffiliateFraudSignalResource`: replace manual `whereHas` scoping with `OwnerUiScope::apply()` |

---

## 8. Verification Commands

```bash
# 1. Verify Filament resources all apply owner scoping
rg -n "getEloquentQuery" packages/filament-affiliates/src/Resources/

# 2. Verify no Filament surface references deprecated is_* booleans
rg -n "is_(active|public|verified|default)" packages/filament-affiliates/src/

# 3. PHPStan on filament package
./vendor/bin/phpstan analyse packages/filament-affiliates/src --level=6

# 4. Run filament affiliate tests
./vendor/bin/pest --parallel packages/filament-affiliates/tests/

# 5. Pint formatting on changed files only
./vendor/bin/pint packages/filament-affiliates/src --test
```
