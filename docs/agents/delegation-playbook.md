# Delegated Work Playbook

Standing clauses for every work prompt delegated to an external model on
this repo. Copy them in; do not paraphrase away the teeth.

## 1. Verify-first (non-negotiable)

- Audits and prior reports are HYPOTHESES, not orders. Re-derive every
  claim from source (`file:line` evidence) and your own repo-wide `rg`
  before implementing.
- Dead-code / zero-caller / zero-reader claims count only if YOUR grep
  returns empty (excluding docs/audits).
- Drop what's factually wrong, implement your corrected version where the
  fix is wrong, follow your own reading on migration necessity.
- Ambiguity: decide the safer option, log what would change your mind.
  Do not stall, do not ask — decide and document.

## 2. No backward compatibility

No union-type overloads kept, no `class_alias`, no deprecated stubs, no
old+new side by side, no "keep just in case." Delete the old shape, update
every internal consumer in the same commit. A consumer that cannot be
updated is a blocking finding — report it, never shim it. Hidden
legacies count too: a database column (or table) that exists only for
backward compatibility — renamed-but-kept columns, alias pairs where
one shadows the other, write-only leftovers — is removed like any other
dead shape. Verify zero readers with your own repo-wide `rg` first
(excluding docs/audits), then drop the column (dev-only: edit the
shipped create migration or add an alter migration per §5), delete its
accessors/fillable entries, and migrate its callers in the same pass.

## 3. Smart test execution (read this twice)

Test runs are the bottleneck of every track. Scope them by impact, never
by habit:

- **DEFAULT — targeted files only.** Run only the test file(s) directly
  covering the touched code. Map source to test explicitly before running
  (e.g. `packages/checkout/src/Services/CheckoutService.php` →
  `tests/src/Checkout/*CheckoutService*`, `*Checkout*Test.php`).
  Runner note: `./vendor/bin/pest --parallel <single-path>` — this runner
  rejects multiple paths in one invocation, so run files/dirs sequentially,
  one path per command.
- **ESCALATE to the Area suite** (`tests/src/<Area>`) ONLY when the change
  alters shared surface: interfaces/contracts, cross-package traits,
  DI bindings/providers, base/abstract classes, config defaults — or when
  a targeted run fails in a way suggesting wider blast radius. State the
  reason when escalating.
- **NEVER run the full monorepo suite.** Final integration = affected Area
  suites only, named explicitly.
- **FORBIDDEN:** package-wide or repo-wide runs "just to be safe" with no
  impact reason. That habit is what makes tracks take forever.
- **REPORT:** list exactly which suites/files ran and passed. If scope was
  escalated, give the one-line reason. Unrun suites are declared as such —
  never implied.

## 4. Scope discipline (parallel work)

- Each workstream owns a disjoint file set, stated in the prompt. Outside
  your set is read-only.
- Blocked by another stream's files? Do NOT touch them: log the
  dependency, finish the rest, report it.
- Shared interfaces/contracts: read-only unless the prompt names you owner.

## 5. Environment constraints

- No live database (local SQLite `:memory:`, prod unreachable). Every
  migration step individually guarded and re-runnable.
- Migrations are development-only: no production database exists, and
  dev databases are delete-and-rerun (see `migration-record.md`
  deployment gates). Both editing shipped migration files and adding
  new migration files (including for new tables) are allowed when the
  audit calls for them. Outcome-identical edits apply cleanly anywhere;
  anything else takes the delete-and-rerun path (drop the dev DB and
  re-migrate; never hand-patch a dev DB into shape). Record shipped
  edits and new-table migrations in `migration-record.md` as
  deviations. No backfills.
- No DB FK constraints/cascades. PHP 8.4. No soft deletes. Money is
  integer minor units.
- Tenant writes via `OwnerWriteGuard` / `ResolveOwnedModelOrFailAction` /
  `OwnerContext`; never assign `owner_type`/`owner_id` directly.

## 6. Final report (required)

Per item: VERDICT (implemented / corrected / dropped) with `file:line`
evidence from your own verification, files changed, tests added + exact
pass output. End with an **"Audit deviations"** section for everywhere the
input was wrong — that section is the most valuable part of the output.

## 7. Model authorization (code-write discipline)

- Writing code — source, migrations, tests, config, docs — is allowed
  ONLY for streams the prompt explicitly marks implementation-authorized.
  That marking is reserved for the approved implementation model
  (currently ChatGPT 5.6 Luna) and must name it; a bare "implement"
  without the designation authorizes nothing.
- Any stream without the marking is read-only: verify, review, report,
  and new proof-test files only. If the task needs a code change, log
  it as a dependency for an authorized stream — do not implement it.
- Main-agent integration edits (stale-test updates, audit conversions,
  record-keeping) follow the same rule: they are code writes and
  require the designation.
