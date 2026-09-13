End-to-end review: packages/membership + packages/moderation. 23 findings (13 membership, 10 moderation), all from inspected bodies.

MEMBERSHIP

M1 | HIGH | bug | packages/membership/src/Actions/InviteMemberAction.php:39-45 + src/Models/MembershipInvitation.php:195-204
Expired-but-Pending invitations deadlock re-invite; no expiry sweeper exists. pendingInvitationQuery filters status=Pending only, ignoring expires_at; expireIfDue() has zero src callers (tests only) and no command/scheduler ships (unlike moderation:expire-blocks). After expiry, re-invite returns the dead row with no event/token, and accept fails isValid(). Docs (04-usage.md:132) promise a new invite after terminal status, but nothing transitions it.
Evidence: `->where('status', InvitationStatus::Pending)` … `if ($existing instanceof MembershipInvitation) { return $existing; }`
Recommend: expire-if-due the existing row (or exclude past-expires_at) in InviteMemberAction; ship membership:expire-invitations + scheduler docs. Confidence: high.

M2 | MEDIUM | security | packages/membership/src/Actions/AcceptInvitationAction.php:21-44
Accept performs no token proof; matchesToken() has zero src callers. Safety depends entirely on the host resolving the invitation via token-hash lookup as docs prescribe (04-usage.md:98-102). Any host resolving by id/UUID (e.g. route-model binding) gets accept gated only by email match.
Evidence: `handle(MembershipInvitation $invitation, Model $user)` — no token param; email check only.
Recommend: add optional token param verified with hash_equals, or an AcceptInvitationByTokenAction. Confidence: high.

M3 | MEDIUM | bug | ApproveMembershipApplicationAction.php:49-53 + :64-70; ChangeMemberRoleAction.php:37-46
Duplicate/spurious MembershipHook events. Approve fires onMemberAdded inside AddMemberAction then again directly (2x). Role change fires onMemberAdded (via handleResolvedMember) plus onMemberRoleChanged — a change looks like an add. Host notifications/billing double-fire.
Recommend: add suppress flag on the role-change path; remove duplicate dispatch in Approve. Confidence: high.

M4 | MEDIUM | bug | ApproveMembershipApplicationAction.php:49-53; migration 000001:22
Approve TypeErrors when applicant or subject is gone. applicant_id is nullable and repo rules forbid FK cascades, so deleted users leave orphans; `$lockedApplication->applicant`/`->subject` null then violates `Model` typehints in AddMemberAction/MembershipSubjectGuard → 500.
Recommend: explicit null checks throwing a domain exception. Confidence: high.

M5 | MEDIUM | bug | MembershipRoleSyncService.php:118-124 via RemoveMemberAction.php:42-44, AddMemberAction.php:79-81
revokeFromUser throws RoleDoesNotExist when the mapped Spatie role row is missing in that team (verified: vendor HasRoles removeRole→collectRoles→getStoredRole→findByName throws). Runs inside the caller's transaction, so member removal / role change rolls back entirely for legacy members, team-scope toggles, or pruned roles.
Recommend: guard with hasRole() or catch RoleDoesNotExist (detach is already a no-op when unassigned). Confidence: high.

M6 | MEDIUM | security | CancelMembershipApplicationAction.php:20 (+ Invite/Approve/Reject/Revoke actions)
No authorization; Cancel takes no actor at all — anyone can cancel anyone's pending application, unaudited (no cancelled_by). Inviter/reviewer/actor args are never checked for membership or capability; package ships no policies.
Recommend: document host-must-gate prominently; add actor==applicant-or-admin check + cancelled_by audit column. Confidence: high.

M7 | MEDIUM | bug/security | InviteMemberAction.php:27-32; ApplyForMembershipAction.php:29-31
Input validation gaps: email never format/length-checked ('' allowed; >255 → DB 500); justification rejects only `=== ''` (whitespace passes, unbounded text); past expiresAt accepted; meta array and reviewer notes unbounded.
Recommend: email format + max:255, trim + non-empty + max justification, reject past expiresAt, cap meta/notes sizes. Confidence: high.

M8 | MEDIUM | bug | ChangeMemberRoleAction.php:23-37
Check-then-act race can resurrect a concurrently removed member: membership read outside txn, guard evaluated on stale snapshot, then syncWithoutDetaching re-inserts inside persist's transaction.
Recommend: re-check existence under lock inside the write transaction. Confidence: med-high.

M9 | LOW-MED | bug | AddMemberAction.php:58-73
Re-add/role-change resets joined_at: pivotData always sets `joined_at: now()` and syncWithoutDetaching updates existing pivot rows.
Recommend: set joined_at on insert only. Confidence: high.

M10 | LOW-MED | performance | AddMemberAction.php:63-68
Schema `hasColumn()` probe on every membership write, inside the transaction.
Recommend: cache per-table. Confidence: high.

M11 | LOW | bug | Traits/HasMembers.php:44-66
Deleting hook mass-updates bypass model events/notifiers and set revoked_at with revoked_by=null (transitionStatus normally requires an actor); applications cancelled silently. Cross-scope wipe is documented/intended.
Recommend: document event bypass; consider setting revoked_by. Confidence: high.

M12 | LOW | security | Models/MembershipApplication.php:38-50 (also moderation Block.php:48-54)
Overly broad $fillable (status, granted_role, reviewer_*, reviewed_at, cancelled_at). Package writes are explicit, but host mass-assignment could escalate lifecycle state. owner_* correctly excluded.
Recommend: narrow fillable + forceFill internally. Confidence: med.

M13 | MEDIUM | security | InviteMemberAction + ApplyForMembershipAction (general)
No abuse controls: unique indexes dedup identical keys only; varying emails/justifications create unbounded rows, each invite emitting a mail-driving event (mail-bomb/row-spam vector for exposed endpoints).
Recommend: document host rate-limiting; consider per-subject quotas. Confidence: med.

MODERATION

D1 | MEDIUM | bug | Traits/HasBlocks.php:64-83 vs Contracts/BlocksEntity.php:19, Actions/BlockEntityAction.php:24
Type mismatch: block() accepts ?CarbonInterface and forwards to execute(?CarbonImmutable). A mutable Carbon date → TypeError; also a PHPStan L6 violation.
Evidence: `?CarbonInterface $expiresAt = null` … `expiresAt: $expiresAt` into `?CarbonImmutable $expiresAt`.
Recommend: type as CarbonImmutable or convert via CarbonImmutable::createFromInterface. Confidence: high.

D2 | MEDIUM | bug | Traits/HasBlocks.php:79 + Actions/BlockEntityAction.php:27
Invalid reason strings silently coerced to Other: `BlockReason::tryFrom($reason)` null → `??= Other`. Typos misrecord moderation history.
Recommend: throw InvalidArgumentException on unknown reason. Confidence: high.

D3 | MEDIUM | security | Traits/HasBlocks.php:85-99; Traits/HasModerationActions.php:46-60
Arbitrary model-class instantiation from string: `is_a($type, Model::class, true)` + `(new $type)->newQuery()->find($id)`. Safe when host hardcodes class (as docs show), but hosts forwarding request input get a model-probing/IDOR gadget.
Recommend: restrict to morph-map allowlist / expected actor classes. Confidence: med.

D4 | LOW-MED | bug | migration 000002:22; Models/ModerationAction.php:39-43; Contracts/RecordsModerationAction.php:13-19
notes column unreachable via public API: schema + fillable have it, but neither the contract, action, nor trait accepts/passes notes. Schema/model/API drift.
Recommend: add ?string $notes through the chain or drop the column. Confidence: high.

D5 | LOW-MED | bug | Models/Block.php:52 (+ migration 000001:24)
lifted_by_type/id never written anywhere in src; transitionTo(Lifted) sets lifted_at only. Lift audit trail permanently null.
Recommend: accept actor in lift path or remove columns. Confidence: high.

D6 | MEDIUM | performance | Traits/HasBlocks.php:20-30
Deleting hook loads ALL active blocks (get()->each) and saves one-by-one with no transaction: N+1 UPDATEs + unbounded memory on heavily-blocked models.
Recommend: chunk + mass update inside a transaction (when events unneeded). Confidence: high.

D7 | LOW | bug | Traits/HasBlocks.php:20-30
Deleting hook is owner-scope-bound, so other-scope active blocks survive pointing at a deleted model — inconsistent with HasMembers' deliberate cross-scope cleanup.
Recommend: withoutGlobalScope(OwnerScope::class) like HasMembers, or document. Confidence: med-high.

D8 | LOW-MED | performance | Actions/ExpireModerationBlocksAction.php:32-47
One UPDATE per block in chunk loop. Correct (model events fire) but slow at scale.
Recommend: optional bulk mass-update path. Confidence: high.

D9 | LOW | bug | Actions/BlockEntityAction.php:38-54
Duplicate active blocks allowed — always inserts, no dedup/unique index. isBlocked() stays correct; table accumulates dupes.
Recommend: idempotent re-block (extend expiry) or partial unique index. Confidence: high.

D10 | LOW | bug | Actions/RecordModerationAction.php:17-23
Empty reason allowed; reason/metadata unbounded.
Recommend: non-empty + length caps. Confidence: high.

POSITIVES (brief): owner scoping consistent (HasOwner on all 4 models, OwnerWriteGuard on writes, OwnerBatchRunner in expire command, MembershipSubjectGuard); UUID PKs; no FK constraints/cascades; invitation tokens 64-char random, sha256 at rest, hidden, hash_equals, single-use terminal transitions with row locks; invite/apply race-safe (txn + lockForUpdate + unique index + QueryException fallback); events dispatched post-commit; no XSS/injection/SSRF/traversal/deserialization surface (no routes/controllers/views, no DB::raw, no unserialize, no file/network I/O); migrations well indexed; stateless singletons, no static mutable state, no cache (Octane-safe, no stampede); solid Pest coverage incl. owner-isolation tests; no SoftDeletes.