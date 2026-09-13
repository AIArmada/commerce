### Prior-audit section
### contacting
Bugs:
- `Models/ContactMethod.php:56-74`, `SocialProfile.php:59-78` MEDIUM — `is_verified/verified_at/normalized_*` fillable, no verification guard in `saving`; `CreateContactMethodAction:42` sets `is_verified` with no visible authz.
- Null-parent `is_primary` orphans + global/owned primary split LOW — by-design, `syncSiblingPrimaryFlags` early-returns on null parent.
Security: clean — `saving` → `OwnerWriteGuard` on all 3 models; no `owner_*` in fillable.
Performance: clean — primary swap `lockForUpdate` + txn + partial unique.
