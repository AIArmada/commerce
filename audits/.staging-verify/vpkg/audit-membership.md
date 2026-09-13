### Prior-audit section
### membership
Bugs:
- `Models/MembershipApplication.php:38-50` HIGH — `status/granted_role/reviewer_*/reviewed_at/cancelled_at` fillable, no model default; direct `create()` bypasses Approve/Reject.
- `Actions/InviteMemberAction.php:34-45` MEDIUM — existing Pending returned as-is, `$token` stays null → no resend.
- DONE (2026-09-13, §8 item 1) — Missing composite unique `(subject,email,role,status)` MEDIUM — only `token` unique; `lockForUpdate` dedupe races. Fixed: composites folded into the applications/invitations creates; lock + 23000 rescue returns existing.
Security: clean — `OwnerWriteGuard` + `lockForUpdate` on Accept/Revoke/Cancel/Approve/Reject; `hash_equals` token; `token` hidden.
Performance: `AddMemberAction.php:65` LOW — `hasColumn` schema check inside txn per call (cache it).

### Prior-audit fix-first rows
| — | membership | `Models/MembershipApplication.php:38-50` | `status/granted_role/reviewer_*` fillable bypasses Approve/Reject | HIGH |

### Migration-batch rows (§8, code may already be fixed)
| 1 | membership missing composite unique (§1) | Composites folded into the applications/invitations creates; lock + 23000 rescue returns existing | `packages/membership/docs/04-usage.md` |
