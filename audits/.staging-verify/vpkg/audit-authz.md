### Prior-audit section
### authz
Bugs: `Models/Role.php:65-81` LOW — caller-supplied teams key wins (`! array_key_exists`); harden if ever request-adjacent. (`Permission.php` part removed — no teams code there.)
Security:
- `Services/ImpersonateManager.php:98-132` HIGH — `take()` has zero authorization; relies entirely on callers.
- `Models/Role.php:110-112` LOW — `findByParam` loops raw `$params` into `where($key,$value)`; allowlist if ever exposed (currently `protected static`).
Performance: clean. `AuthzScope deleting` 5× `DB::table` in one txn; volume bounded.

### Prior-audit fix-first rows
| — | authz | `Services/ImpersonateManager.php:98-132` | `take()` performs no authorization | HIGH |
