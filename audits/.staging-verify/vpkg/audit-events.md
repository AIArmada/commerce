### Prior-audit section
### events (60+ models)
Bugs:
- `Event:162-172`, `EventSeries:43-49`, `EventTemplate:54-63`, `EventItinerary:40-46` HIGH — `owner_*/created_by_*/*_at/status` fillable (correction: children ALSO include owner).
- `Event` no cascade CRITICAL — no `booted/deleting`; orphans entire subtree.
- `RegistrationService:51-123,291-312` HIGH — `fill(Arr::except)` no Validator; `total_amount/currency/registrant_*/external_order_*/parent/pass_entitlements` mass-assignable; `createFromOrderItem` copies totals with `?? 0/'USD'`, no normalizer.
- `EventRegistrationItem:46-54` HIGH — `ticket_type_id` fillable, no same-event/owner validation.
Security:
- `ScopesByEventOwner:85-92` MEDIUM-verify (was HIGH blanket) — `whereNull(eventId) OR whereIn(subselect)`; leak depends on per-model matrix (models with `whereHas(event)` reject nulls).
- `EventSearchDocumentBuilder:188` LOW/MEDIUM-verify — `withoutGlobalScope('event_owner')` is indexer building cross-owner docs; needs ACL review, not auto HIGH.
- Filament occurrence/session/attendance/changelog inherit null-matrix above; same MEDIUM-verify.
Performance:
- Per-participant/answer/item `create()` loop in one txn MEDIUM — chunk or queue for 100+.
- No `paginate()` in `src` MEDIUM-verify — confirm cursor for list/search or large listings OOM. Check-in `lockForUpdate` GOOD.

---

### Prior-audit fix-first rows
| 23 | events | `Models/Event.php` (no booted cascade) | Delete orphans entire subtree | CRITICAL |
| 25 | events | `Services/RegistrationService.php:51-123,291-312` | Forged `total_amount/currency`, unvalidated `ticket_type_id`, no Validator | HIGH |
| — | events | `EventRegistrationItem:46-54` | `ticket_type_id` never validated same-event/owner | HIGH |

### Migration-batch rows (§8, code may already be fixed)
| 10 | orders cached totals + `recalculateTotals`/`getBalanceDue` (§4) | Columns folded into the orders create; model events are the single sync; ex-tax subtotal formula | `packages/orders/docs/04-usage.md` |
