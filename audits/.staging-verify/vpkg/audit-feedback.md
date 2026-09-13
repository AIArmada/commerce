### Prior-audit section
### feedback
Bugs:
- DONE (2026-09-13, §8 item 3) — `SubmitFeedbackResponseAction:139-149` HIGH — `exists()` then insert, form lock only, no `(form,respondent)` unique → duplicates. Fixed: submitted-only partial unique folded into the responses create; idempotent Start (reuses drafts); full-transition rescue returns the winner; flag-off multi-submit still allowed.
- Invitation-expiry rollback MEDIUM — `assertInvitationValid:170-173` marks `Expired` then throws inside same txn → rolled back.
- `StartFeedbackResponseAction:42-59` MEDIUM — unlimited drafts, flips invitation to `Started` with no status check.
- `DeleteFeedbackFormAction:20-46` MEDIUM — `get()->each->delete()` N+1.
- No sweeper LOW; raw token in path LOW (`InvitationUrlGenerator` emits `/feedback/invitations/{rawToken}`; mitigated by `token_hash` unique + rate-limit).
Security:
- Anonymous vs guard HIGH-verify — `SubmitFeedbackResponseAction:34-52` requires `OwnerWriteGuard`; link-only anonymous with owner-mode on 403s absent explicit-global wiring, or leaks via enumeration if bypassed. Needs explicit test.
- `ResolveFeedbackInvitationTokenAction` returns full unscoped model MEDIUM — `email/phone/metadata` + `token_hash` (no `hidden`); audit callers for serialization.
Performance: recalc fan-out MEDIUM — `FeedbackAnalyticsService:95-156` per-view queries on nearly every submit; no debounce. Submit eager-loads well (`with(questions.options):35-38`) GOOD.

---

### Prior-audit fix-first rows
| 31 | feedback | `Actions/SubmitFeedbackResponseAction.php:139-149` | One-response check without lock/unique → duplicates | HIGH |

### Migration-batch rows (§8, code may already be fixed)
| 3 | feedback one-response unique (§3) | Submitted-only partial unique folded into the responses create (pgsql/sqlite; code-level on MySQL); idempotent Start; full-transition rescue; flag-off multi-submit kept | `packages/feedback/docs/04-usage.md`, `99-troubleshooting.md` |
