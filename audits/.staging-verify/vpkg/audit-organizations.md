### Prior-audit section
### organizations (org IS owner, no HasOwner — correct)
Bugs:
- `Models/Organization.php:148-171` MEDIUM — `transitionToStatus/Visibility` never clears stale counterpart (`suspended_at/archived_at/published_at`).
- `Models/Organization.php:48-60` MEDIUM — `status/visibility/*_at/created_by` fillable bypasses transitions (only `created_by` guarded in `saving`).
Security: clean — `TransferOrganizationOwnershipAction` txn + lock + single-owner invariant.
Performance: clean, indexes present.
