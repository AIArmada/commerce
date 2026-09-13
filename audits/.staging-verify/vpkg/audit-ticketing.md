### Prior-audit section
### ticketing
Bugs:
- `Pass:85-94`, `TicketType:79-86` HIGH — no `deleting` cascades; orphans holders/transfers/allocations/components.
- `TicketType status` raw string MEDIUM — no enum cast (Pass uses `PassState`).
- `DefaultPassIssuer:131-132` LOW-MEDIUM (was MEDIUM) — retry only `pass_no`; `qr/barcode` collision negligible but unhandled.
- `DefaultPassTransferService:24-47` HIGH — txn but no `lockForUpdate`, no same-pass/owner check → double-transfer.
Security:
- `TransferPassToHolderAction:38-64`, `IssuePassesAction:53-70` HIGH — arbitrary `holder_type/id` morph, no allowlist/owner check.
- `TicketingOwnerGuard` skips non-`HasOwner` LOW-MEDIUM — by-design for global models.
Performance:
- `TicketType:294-303` MEDIUM (was HIGH) — `getTotalAvailable` hydrates `inventoryLevels()->get()->sum()`; `getTotalOnHand` already SQL. Fix with `SUM(GREATEST(...))`.
- Bulk transfer nested txns + 2 writes/pass MEDIUM — chunk or queue for 100s.

### Prior-audit fix-first rows
| 18 | ticketing | `Actions/TransferPassToHolderAction`, `IssuePassesAction` | Arbitrary `holder_type/id` morph, no owner check | HIGH |
| 19 | ticketing | `Services/DefaultPassTransferService.php:24-47` | No lock, no same-pass/owner check → double-spend | HIGH |
| — | ticketing | `Pass:85-94`, `TicketType:79-86` | No deleting cascades → orphans | HIGH |
