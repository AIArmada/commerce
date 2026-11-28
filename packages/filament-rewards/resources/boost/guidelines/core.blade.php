# FilamentRewards

Filament Rewards is the administrative entry point for Rewards. Read program-scoped records through `AIArmada\FilamentRewards\Queries\RewardProgramScopeQuery`; delegate durable mutations to Rewards Actions. A staff role or explicit `reward-staff` Gate for users without role methods is required on all resource list and record pages, in addition to normal Filament authorization. Use `RewardAdminPresenter::staffActor()` to require a valid authentication identifier and `getAuthIdentifier()` for staff attribution, rather than assuming the actor is an Eloquent model. Fraud stewardship requires both staff access and the `reward-fraud-steward` ability. Recheck staff access and applicable operation-specific abilities inside handlers, including read-only qualification previews, not only in button visibility. When verifying a pending payee, pass its registration identity in `verified_from_registration_id`; stale or mismatched registrations must be refused. Never widen tenant scope for a record mutation. Do not add compatibility aliases.

Read budget and benefit stock balances through `AIArmada\Rewards\Features\Finance\Api\RewardBudgetReadApi` and `AIArmada\Rewards\Features\Benefits\Api\RewardBenefitStockReadApi`; both validate the current program owner scope before reading the ledger.

Use `RewardPayeeSelectionApi` for award-bound verified payout destination choices. It exposes masked labels and processor names only; the Finance request action revalidates the selected destination under lock.

Payout handlers require the corresponding `approve`, `submit`, `confirm`, `reconcile`, or `cancel` Gate ability on the payout record; the Finance action independently validates ownership and lifecycle state. Approval reasons are retained in the payout evidence, including each reapproval.

Payee verification and rejection require `verify` and `reject` Gate abilities on the record (or the payee model class for register-and-verify). Benefit grant handlers require `fulfill`, `consume`, `cancel`, `recordRecall`, `revoke`, or `expire` on the grant, and catalog retirement requires `retire` on the item. Host applications must explicitly authorize these operations; staff membership alone is insufficient.

Program header actions require `publishVersion`, `transitionStatus`, and `previewQualification` on the current scoped program. Funding source recording requires `record` on the funding source class; funding allocation requires `record` on the funding entry class with the target program ID. Catalog creation requires `create` on the benefit item class with the target program ID. These checks run before any action or ledger mutation.

Use the program directory's bounded `options($search)` for server-side program selectors and `title($id)` for the selected label. Both queries respect the current owner scope; search wildcards are treated literally.
