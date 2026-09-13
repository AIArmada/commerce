### Prior-audit section
### docs
Bugs:
- `DefaultNumberStrategy:52` MEDIUM — `uniqid('',true)` time-based predictable numbers.
- `DocDownloadController:62-64` MEDIUM — sync Browsershot/Chromium in GET; enqueue + 202.
- `DocService:86-89` LOW — caller `pdfOptions` merge; mitigated (`DocRenderService:177-210` constrained keys).
Security: verified safe — share `random(48)`+SHA256, hash lookup + re-scope, uniform 404, `no-store/noindex`. `TiptapJsonRenderer:186-197` blocks `javascript:`/traversal. Residual LOW: allows `http://` embeds.
Performance: `SequenceManager`/`DocPaymentRecorder` locks GOOD; `loadMissing(template,docable)` GOOD. Sync Browsershot in GET is the perf cost (see bugs).

### Migration-batch rows (§8, code may already be fixed)
| 11 | tax zone matching (§2) | No migration by design: request-scoped owner-aware resolver cache + `scoped()` bindings | `packages/tax/docs/99-troubleshooting.md` |
