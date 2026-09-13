### Prior-audit section
### moderation
Bugs:
- `Models/Block.php:48-54` MEDIUM — `status/lifted_*/expires_at` fillable bypasses `transitionTo()`.
- `Block.php:121-142` MEDIUM — `Active` keeps stale `expires_at`; `Lifted` never sets actor.
- Scope gap MEDIUM — past-due Active in neither `scopeActive` nor `scopeExpired` until sweeper runs.
- `BlockEntityAction.php:38-54` MEDIUM — no duplicate-Active guard; double-click stacks blocks.
Security: `validateOwnerScopedModel` skips non-`OwnerScopeConfigurable` LOW (was MEDIUM) — by-design per-tenant block of shared identity.
Performance: `ExpireModerationBlocksAction:32-47` LOW (was MEDIUM) — `chunkById(100)` + per-block `expire()->save()` preserves events; keep unless proven hot.

---
