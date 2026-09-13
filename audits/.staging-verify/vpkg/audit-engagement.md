### Prior-audit section
### engagement
Bugs:
- DONE (2026-09-13, §8 item 2) — follow/bookmark/react/respond HIGH — `first→create`, no lock, no composite unique (only counters unique) → twins skew counters. Fixed: actor+subject composites folded into creates; lock + 23000 rescue returns existing.
- DONE (2026-09-13, §8 item 2) — Reminder double-send HIGH — `markSent/Failed:111-127` no status precondition; `SendDueRemindersCommand:56-73` re-check non-atomic, no lease. Fixed: status preconditions + row locks + send lease.
- DONE (2026-09-13, §8 item 2) — `share_token Str::random(16)` indexed not unique MEDIUM → collisions. Fixed: unique folded into the shares create.
- Unbounded reminder/subscription creation MEDIUM — no dedup/throttle.
- `BookmarkCollectionItem` dedup unverified — `firstOrCreate` without confirmed unique; verify migration.
Security:
- Actor/subject owner-equality MEDIUM/HIGH-verify — `DefaultEngagementPolicyResolver` all `true`; creates rely on ambient context. Confirm resolver.
- State/counter reads rely on global scope LOW — prefer explicit `forOwner`.
Performance:
- Per-type counter fan-out MEDIUM — `recalculateResponses/Reactions:228-275` per-type `count` + `updateOrCreate`.
- GOOD: `aggregateCounters:141-177` single round-trip; `dueReminders cursor()`; counters unique.

### Prior-audit fix-first rows
| 30 | engagement | `Services/DefaultEngagementManager` follow/bookmark/react/respond | No lock + no unique → duplicates skew counters | HIGH |
| — | engagement | Reminders `markSent/Failed:111-127` | No status precondition, no lease → double-send | HIGH |

### Migration-batch rows (§8, code may already be fixed)
| 2 | engagement dup uniques + `share_token` + reminder lease (§3) | Actor+subject composites + `share_token` unique folded into creates; lock + rescue; status preconditions + send lease | `packages/engagement/docs/01-overview.md`, `99-troubleshooting.md` |
