### Prior-audit section
### vouchers
Bugs:
- `VoucherWallet:81-101` HIGH — `claim/markAsRedeemed` check-then-set, no txn/lock/`whereNull` → double redeem.
- `VoucherService:361-366` MEDIUM — percentage redeem records 0-value usage consuming `usage_limit`.
- `RecordVoucherUsage:78-90` MEDIUM — currency never validated vs voucher currency.
- `reserve()/release():257-338` MEDIUM — advisory cache dead code (zero readers).
- Idempotency gap MEDIUM — null key when neither `metadata.idempotency_key` nor `order_id` → plain `create()`, NULLs don't dedup.
- `VoucherValidator:153-171` MEDIUM — unknown cart shape totals 0 (fragile fail-closed).
- `AddVoucherToWallet` CORRECTED — unique `voucher_wallets_one_active_per_holder WHERE redeemed_at IS NULL` EXISTS; residual is concurrent 500s (no lock/23000 rescue), not silent duplicates. `VoucherService:216-232 addToWallet` skips even the `first` check.
Security:
- `UpdateVoucher:25-27` CRITICAL — `VoucherModel::where(code)->firstOrFail()` unscoped; `Voucher booted:596-638` has zero owner checks.
- `resolveRedeemedByOrder:405-407` MEDIUM — `Order::select()->find()` no `forOwner`.
- `removeFromWallet:244-248` MEDIUM — `VoucherWallet::where(voucher,holder)->delete()` no owner predicate.
- Per-user limit unscoped + guests skipped MEDIUM — `RecordVoucherUsage:67-76` counts with no owner scope; `Validator:88-102` only `Auth::user()`.
- `ExpireVouchersCommand withoutOwnerScope` LOW — needs explicit global context.
Performance:
- `getTimesUsedAttribute:393-406` N+1 MEDIUM — `usages()->count()` per voucher unless `withCount`.
- `scopeLive:254-274` correlated subquery per row MEDIUM — prefer `withCount+having`/join.
- `getUsageHistory:204-206` unbounded MEDIUM.
- `include_global=true` bypasses `VoucherLookupCache:36-38` LOW.
Good: `RecordVoucherUsage:43-50` txn+lock+recheck+unique; `ExpireVoucher` locks; targeting fails closed.

### Prior-audit fix-first rows
| 1 | vouchers | `Actions/UpdateVoucher.php:25-27` | Unscoped `where(code)` write — any tenant can rewrite another's voucher | CRITICAL |
| 29 | vouchers | `Models/VoucherWallet.php:81-101` | `claim()` check-then-set race → double redeem | HIGH |
