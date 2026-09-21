# Test Cleanup Candidates — Decision Document

**Date:** 2026-09-15

**Method note.** This document compiles 12 read-only audit batches into a single decision list. Each candidate was classified by category (REDUNDANT, TRIVIAL, LOW-VALUE-DUPLICATION, PERMANENTLY-SKIPPED, IRRELEVANT, STALE), assigned an action (DELETE, COLLAPSE, HUMAN-CALL), and given a confidence level (high / medium-high / medium / low-medium / low, with M/MH/LM/L shorthands preserved from source batches). The 12 prior child results carried no inspectable bodies (summaries only), so this compilation is built from the compact candidate lines supplied with the request; every whole-file removal below was additionally spot-checked by opening the file, and its covering test file was verified to exist with superseding coverage. No test files were modified during the audit.

**Path convention.** All paths are relative to `tests/src/` unless prefixed otherwise (`demo/…`, `tests/Feature/…`, `tests/src/…` written in full where the source gave a full path).

## Execution Status (2026-09-15)

- DELETE D1–D150: APPLIED and per-wave verified, except D66/D67 (Checkout MultiStep paths — not found anywhere, no-op) and D118/D140 (already deleted in an earlier wave).
- COLLAPSE C1–C23: APPLIED and verified (unique assertions folded into keepers). C24/C26/C27/C28/C29: targets not found (already removed or audit paths stale) — no-op.
- Pre-existing `tests/Feature` failures (confirmed on pristine HEAD), now fixed: `PackageDiscoverabilityTest.php` (stale `stock-config` tag → `inventory-config` + `InventoryTestCase`) and `CartAdminEndToEndTest.php` (DELETED — referenced RecoveryTemplate/RecoveryCampaign/AlertRule models + Filament resources that do not exist anywhere in the codebase).
- HUMAN-CALL items: untouched, pending user review.
- Verification: per-wave Pest runs (all green); full-suite run skipped per user instruction.

## Execution Status (2026-09-21 re-audit)

- Fresh read-only re-audit of the current tree (7 auditors, ~6.4k tests judged) found further
  ONETIME/TRIVIAL/TAUTOLOGY/DUPLICATE tests in Cart, Cashier, CashierChip, Checkout, Chip,
  CommerceSupport, Communications, Contacting, Customers, Docs, References, Seating, Shipping,
  Signals, Tax, and one demo test. All verified verdicts applied (waves 1–9) with per-scope
  Pest runs green. One auditor verdict was partially rejected on keeper inspection (the
  `pay()`-only test has no covering keeper, so it stays). Separately, a few carried-over
  candidate paths (`LocationModelTest`, `OrderCreateScreenTest`, a 210-line `CartTest`) match
  no file anywhere in the tree and were closed as no-ops.
- Pre-existing failures noted (not caused by removals; each fails on pristine HEAD):
  `demo/tests/Feature/BillingOwnerScopingTest.php` — `signal_sessions` has no `country`
  column (seeder/migration drift); `tests/src/Feedback/FeedbackSubmissionTest.php:250`
  (expects 2 successful submits, gets 1);
  `tests/src/Inventory/Feature/CheckoutReservationConcurrencyTest.php` (2 SQLite
  savepoint errors under `--parallel`).

## Summary counts

Total candidates: **231**

By action:

| Action | Count |
|---|---|
| DELETE | 150 |
| COLLAPSE | 29 |
| HUMAN-CALL | 52 |

By category:

| Category | Count |
|---|---|
| REDUNDANT | 140 |
| TRIVIAL | 52 |
| LOW-VALUE-DUPLICATION | 25 |
| PERMANENTLY-SKIPPED | 8 |
| IRRELEVANT | 4 |
| STALE | 2 |

Whole-file removals: **9** (5 DELETE outright + 3 COLLAPSE-merge-then-delete, all spot-checked; 1 further file deletion pending a HUMAN-CALL fold).

Dedup note: no exact-duplicate candidates were found. Near-duplicate pairs (e.g. `CartClearedEventTest:53` / `CartDestroyedEventTest:88`) target distinct files and are each listed once.

## Whole-file removals (spot-checked)

DELETE outright (covering file verified to exist with superseding coverage):

| File | Tests | Category | Conf | Justification |
|---|---|---|---|---|
| `tests/src/Inventory/Unit/StockThresholdServiceTest.php` | 7 | REDUNDANT | high | All 7 tests covered by `tests/src/Inventory/Unit/Services/StockThresholdServiceTest.php` (26 tests). Opened: single `describe('StockThresholdService')` block, status/attention assertions only. |
| `tests/src/Chip/Unit/Commands/CommandsTest.php` | 6 | REDUNDANT+SKIPPED | high | Signature/description tests == `CommandExecutionTest`; the 2 skipped execution tests are covered by running `Chip/Feature/CommandsFeatureTest` L14, L144. Opened: confirmed 4 instantiation + 2 skipped tests. |
| `tests/src/Chip/Unit/Gateways/ChipGatewayTest.php` | 13 | REDUNDANT | high | All duped in `ChipGatewayExtendedTest` (27 tests). Opened: interface/name/supports-matrix/handler tests only. |
| `tests/src/FilamentPromotions/PromotionTypeEnumTest.php` | 9 | REDUNDANT | high | Covered by `Promotions/PromotionTypeEnumTest.php` (11 tests: exact label/color/values/from); only unique assertion is a trivial count. Opened: confirmed. |
| `demo/tests/Unit/ExampleTest.php` | 1 | TRIVIAL | high | Pest scaffold tautology (`that true is true`), no project code. Opened: confirmed. |

COLLAPSE-merge, then delete the duplicate file (covering `Enums/` file is a strict superset — 56 vs 29, 22 vs 8, 23 vs 8 tests):

| File | Merge into | Conf | Justification |
|---|---|---|---|
| `tests/src/Products/AttributeTypeTest.php` | `Products/Enums/AttributeTypeTest.php` | high | Same assertions; keep `Enums` `from()`/values plus the exact label keys before deleting. |
| `tests/src/Products/ProductStatusTest.php` | `Products/Enums/ProductStatusTest.php` | high | Same assertions; keep `Enums` `from()`/values + exact labels before deleting. |
| `tests/src/Products/ProductVisibilityTest.php` | `Products/Enums/ProductVisibilityTest.php` | high | Same assertions; keep `Enums` values/`from`/color + exact labels before deleting. |

Pending HUMAN-CALL (delete file only after the fold): `Vouchers/Unit/Compound/AbstractProductMatcherTest.php` — fold 2 variant inputs into `Matchers/AbstractProductMatcherTest`, then delete (see H21).

## DELETE (150)

| ID | File | Line(s) | Test(s) | Category | Conf | Reason / covering test |
|---|---|---|---|---|---|---|
| D1 | `Inventory/Unit/StockThresholdServiceTest.php` | whole file | 7 tests | REDUNDANT | high | All covered by `Services/StockThresholdServiceTest.php`. See whole-file table. |
| D2 | `Tax/TaxModelsTest.php` | 60,79,95,133,191,203,216 | 7 tests | REDUNDANT | high | Covered by TaxZone/Class/Rate/ExemptionTest create+scope+calc tests. |
| D3 | `Tax/TaxModelsTest.php` | 151,233 | compound create, expiration | REDUNDANT | med | Covered by TaxRateTest casts + TaxExemptionTest create/expired/casts. |
| D4 | `Tax/TaxModelsTest.php` | 112,173 | orderBy tests | IRRELEVANT | low | Raw orderBy tests Eloquent; class path covered by ordered scope. |
| D5 | `Tax/Unit/Models/TaxZoneTest.php` | 255 | deleting zone deletes rates | REDUNDANT | high | Covered by TaxZoneDeletionTest cascades test (2 rates + message; owner off default). |
| D6 | `Tax/Feature/CrossTenantIsolationTest.php` | 103 | block global-zone delete | REDUNDANT | med | Covered by TaxZoneDeletionTest owned-rates test (exact message). |
| D7 | `Tax/Unit/Services/TaxCalculatorEdgeCasesTest.php` | 488 | unknown zone error | REDUNDANT | med | Dup of TaxCalculatorTest:359; extra config no-op w/o context. |
| D8 | `Inventory/Unit/Models/InventoryLocationTest.php` | 30,44,122,128,135,141,177,189,199 | 9 hierarchy tests | REDUNDANT | high | Identical to HasLocationHierarchyTest equivalents (same trait + model). |
| D9 | `Inventory/Unit/Traits/HasLocationHierarchyTest.php` | 301,311,321 | moves block | REDUNDANT | high | Service tests in trait file; covered by LocationTreeServiceTest. |
| D10 | `Inventory/Unit/FefoStrategyTest.php` | 106,114,121 | context tests | TRIVIAL | med | L106 subset of AllocationContextTest; L114, L121 set-readback tautologies. |
| D11 | `Inventory/Unit/Services/ExpiryMonitorServiceTest.php` | 172 | processes expired | REDUNDANT | med | Pure delegation + vacuous `>=0` assert; covered by BatchServiceTest. |
| D12 | `Inventory/Unit/Models/InventorySerialHistoryTest.php` | 79 | event type as enum | REDUNDANT | high | Subsumed by same-file loop test L89. |
| D13 | `Tax/Unit/Settings/TaxZoneSettingsTest.php` | 50,57,76 | instantiate / access / null | TRIVIAL | med | Reflection + assign-readback tautologies. |
| D14 | `Affiliates/Unit/CohortAnalyzerTest.php` | 98,102,106,110,114 (+140) | 5 skipped + 1 redundant | PERM-SKIPPED / REDUNDANT | high / med | Covered by `Services/CohortAnalyzerTest` SQLite tests; L140 dups L36. |
| D15 | `Affiliates/Unit/LowCoverageBoostTest.php` | 23,45,50,55,104,140,146,176,182,187,205,226,234,251,269,286,296,301,318,336,355,361,366,390,395 | 25 tests | REDUNDANT | high | Covered by RankHist / PayoutEv / SupMsg / RankQual / Upline / ProgSvc / Fraud / LinkGen dedicated tests. |
| D16 | `Affiliates/Unit/AdditionalModelsTest.php` | 19,43,49,58,67,76,101,126,151,175,183,191,200,221,227,233,257,281,290,299,308,318,356,366,374,383,392,401,410,422,447,475,506 | 33 tests | REDUNDANT | high (L475 med) | Covered by Balance / Link / Program / Membership / Tier ModelTests. |
| D17 | `Affiliates/Unit/AdditionalModels2Test.php` | 261,291,297,305,451,471 | 6 tests | REDUNDANT | high | Covered by DailyStatModelTest, PayoutHoldModelTest. |
| D18 | `Affiliates/Unit/AdditionalModels3Test.php` | 166,193,201,208,223,238,250,271,294,310,333,339,348,357,366,375,384,404,411,437,444,467,474,527 | 24 tests | REDUNDANT | high | Covered by Template / VolumeTier / Ticket / Msg / TaxDoc / Training / Promo ModelTests. |
| D19 | `Affiliates/Unit/AdditionalModels4Test.php` | 23,49,56,63,71,79,100,107,119,125,134,142,154,170 | 14 tests | REDUNDANT | high (L23 med) | Covered by Conversion(T)ModelT, FraudSigM, UplineM, PayoutM, TierM, RankM, TouchT, TaxCohort. |
| D20 | `Affiliates/Unit/AffiliateSupportTicketTest.php` | 22,51,107,128 | 4 tests | REDUNDANT | high | Covered by SupportTicketModelTest. |
| D21 | `Affiliates/Unit/AffiliatePayoutEventTest.php` | 8,24 | 2 tests | REDUNDANT | high | Covered by PayoutEventModelTest. |
| D22 | `Affiliates/Unit/AffiliatePayoutHoldTest.php` | 22,47,57,68,79,90,106,130 | 8 tests | REDUNDANT | high (L130 med) | Covered by PayoutHoldModelTest. |
| D23 | `Affiliates/Unit/AffiliateFraudSignalTest.php` | 28,123,150,215,234,252,294 | 7 tests | REDUNDANT | high | Covered by FraudSignalModelTest. |
| D24 | `Affiliates/Unit/AffiliateFraudSignalModelTest.php` | 141 | 1 test | REDUNDANT | high | FraudSignalTest:177 is stronger. |
| D25 | `Affiliates/Unit/AffiliateCommissionPromotionTest.php` | 36,67,79,91,103,117,130,143,177,193,207,220 | 12 tests | REDUNDANT | high | Covered by CommissionPromotionModelTest. |
| D26 | `Affiliates/Unit/AffiliatePayoutTest.php` | 45,60,65,135,150,164,179,193,245,267 | 10 tests | REDUNDANT | high | Covered by PayoutModelTest. |
| D27 | `Affiliates/Unit/AffiliatePayoutModelTest.php` | 166,182 | 2 tests | REDUNDANT | high | PayoutTest:213,229 are stronger. |
| D28 | `Affiliates/Unit/AffiliateProgramTierTest.php` | 31,92,106,274,288,302,316 | 7 tests | REDUNDANT | high | Covered by ProgramTierModelTest. |
| D29 | `Affiliates/Unit/AffiliateModelExtendedTest.php` | 32,38,44,50,62,68,74,80,86,92,98,104,126,142,159,165,171,177,186,192 | 20 tests (L142 trivial) | REDUNDANT | high | Covered by AffiliateModelTest, BalanceM:67; L142 tests Eloquent, never `findByCode`. |
| D30 | `Affiliates/Unit/ServicesAndDtosTest.php` | 29,46,71,84,105,120,153 | 7 tests | REDUNDANT | high | Covered by CalcTest, UplineT, LinkGenT, DataT, ConvDataT, ProgramM. |
| D31 | `Affiliates/Unit/ServicesTest.php` | 71,233,239,364,426 | 5 tests | REDUNDANT | high | Covered by GenCodeT, ProgramServiceT (L426 never calls `getAffiliatePrograms`). |
| D32 | `Affiliates/Unit/SupportAndIntegrationsTest.php` | 32,51,60,86,152,171,177,209 | 8 tests (L32, L209 trivial) | REDUNDANT | high | Covered by ProgramSvcT, WebhookT, ReportSvcT, ZeroCovT; L209 tests Carbon lib. |
| D33 | `Affiliates/Unit/ZeroCoverageTest.php` | 100,124,130,310,316,324,332 | 7 tests | REDUNDANT | high | Covered by TrainingModuleM, PerfBonusSvcT. |
| D34 | `Affiliates/Unit/AffiliateAttributionTest.php` | 13,19,25,31 | 4 tests | REDUNDANT | high | Covered by AttributionModelTest. |
| D35 | `Affiliates/Unit/AffiliateDataTest.php` | 11,35 | 2 tests | REDUNDANT | high | Covered by DataDtoTest. |
| D36 | `Affiliates/Unit/AffiliateDataDtoTest.php` | 64 | 1 test | REDUNDANT | high | AttributionDataT:55 is stronger. |
| D37 | `FilamentAffiliates/Unit/ResourcesTest.php` | 271,316,405 | 3 tests | REDUNDANT | high | Covered by PayoutCreateT:39,22; LinkPageT:65. |
| D38 | `FilamentAffiliates/Unit/ResourceCoverageTest.php` | 258 | 1 test | REDUNDANT | high | Covered by same-file L116 + L126. |
| D39 | `FilamentAffiliates/Unit/SchemasAndTablesTest.php` | 27,83 | 2 tests | REDUNDANT | high | Covered by ResourceCoverageT:203,69. |
| D40 | `FilamentAffiliates/Unit/PortalCoverageTest.php` | 532,542 | 2 tests | REDUNDANT | high | Covered by PortalRegistrationT:126,17. |
| D41 | `FilamentAffiliates/Unit/PayoutExportServiceTest.php` | 93 | 1 test | REDUNDANT | high | Same-file L71; named `download()` DNE in src. |
| D42 | `Chip/Unit/Commands/CommandsTest.php` | 10–45 | 6 tests, whole file | REDUNDANT + SKIPPED | high | == `CommandExecutionTest`; skips covered by `CommandsFeatureTest` L14, L144. See whole-file table. |
| D43 | `Chip/Unit/Webhooks/RemainingWebhooksTest.php` + `Chip/Feature/WebhookMonitorTest.php` | 9,16; 10 | 3 tests | REDUNDANT | high | == `ProcessChipWebhookTest` L11; `MonitorTest` L9. |
| D44 | `Chip/Feature/WebhookHandlersIntegrationTest.php` | 73–174 | 12 struct/skip/refl/DTO tests | REDUNDANT | high | Covered by HandlersTest, WebhookHandlersTest, AdditionalDataObjectsTest. |
| D45 | `Chip/Unit/Webhooks/HandlersTest.php` + `HandlersExtendedTest.php` | 14–141 | 16 instantiation + skip tests | REDUNDANT | high | Dupes of WebhookHandlersTest interface/instantiation; HandlersTest skip is strongest. |
| D46 | `Chip/Unit/Webhooks/WebhookHandlersTest.php` + `MonitorTest.php` + `ProcessChipWebhookTest.php` | 69–156; 53–96; 22–30 | DTO/shape x13 | REDUNDANT | med | Stronger copies in AdditionalDataObjectsTest, WebhookEventDispatcherTest. |
| D47 | `Chip/Unit/Gateways/ChipGatewayTest.php` | 12–82 | 13 tests, whole file | REDUNDANT | high | All duped in `ChipGatewayExtendedTest`. See whole-file table. |
| D48 | `Chip/FacadesAndGatewaysTest.php` | 410–602 | gateway x16 | REDUNDANT | high | == `ChipGatewayExtendedTest`; move 3 status asserts first (HUMAN-CALL). |
| D49 | `Chip/FacadesAndGatewaysTest.php` | 293–395 | intent x13 | REDUNDANT | high | Stronger in `ChipPaymentIntentTest`. |
| D50 | `Chip/FacadesAndGatewaysTest.php` | 140–223 | handler x7 | REDUNDANT | high | == `ChipWebhookHandlerTest`. |
| D51 | `CashierChip/CashierChipTest.php` | 13–49 | config x6 | REDUNDANT | high | Exact dup of CashierTest. |
| D52 | `CashierChip/Feature/BillableTest.php` + `ManagesCustomerTest.php` + `PerformsChargesTest.php` | 86,134,185; 42; 17,35 | 6 tests | REDUNDANT | med | Covered by unit counterparts. |
| D53 | `CashierChip/SubscriptionExtendedTest.php` | 45–441 | 10 tests | REDUNDANT | med-high | All subsumed by SubscriptionTest. |
| D54 | `CashierChip/ConsoleCommandsTest.php` | 10 | runs successfully | REDUNDANT | med-high | Subsumed by same-file L15, L21, L27. |
| D55 | `Chip/DataAndModelsTest.php` | 32–342 | send-svc x12 + tables x2 | REDUNDANT | med-high | Stronger in `ChipSendServiceTest`; tables in ModelsTest L116. |
| D56 | `Chip/Models/ModelsTest.php` | 258,372,427 | table x3 | REDUNDANT | high | Within-file dups of L28 / L116. |
| D57 | `Chip/ListenersIntegrationTest.php` + `WebhookProcessingTest.php` | 24,264; 648 | skipped x2 + instant | PERM-SKIPPED | high | Covered by running same-file L98; MonitorTest L15; RegressionTest L375. |
| D58 | `Chip/MoreWebhooksTest.php` + `WebhookCoreProcessingTest.php` + `WebhookUtilitiesTest.php` | 120,159; 118,141,161; 76 | retry x5 + dup | REDUNDANT | med | Covered by WebhookRetryManagerTest; L76 == L67. |
| D59 | `Cart/Unit/Events/AllEventsTest.php` | 28,66,107,149,181,208,297,328,352 | can be instantiated x9 | REDUNDANT | M | Each subsumed by focused creates-event-with test in CartLifecycleEventsTest / ItemEventsTest (instanceof + props). |
| D60 | `Cart/Unit/Collections/CartCollectionTest.php` | 30,51,127,139,152,165,181,195,206,215,230,242,255,265,283,296,309,322,339,347,355,374 | 22 inherited-only tests | IRRELEVANT | M | Assert only Illuminate Collection methods (sum/filter/sortBy/pluck/map/groupBy/chunk/min/max/avg/reduce/toArray); CartCollection overrides none. |
| D61 | `Cart/Feature/Events/CartClearedEventTest.php` | 53 | dispatches when events are enabled | REDUNDANT | MH | No config toggle; strict subset of same-file L15. |
| D62 | `Cart/Feature/Events/CartDestroyedEventTest.php` | 88 | dispatches when events are enabled | REDUNDANT | MH | No config toggle; strict subset of same-file L15. |
| D63 | `Cart/Feature/Conditions/ShippingConditionsTest.php` | 71 | works with Cart facade | REDUNDANT | MH | Strict subset of same-file L12; whole file uses facade. |
| D64 | `Cart/Feature/Events/ConditionEventsTest.php` | 40 | calculates correct impact for condition added | REDUNDANT | MH | Checks no impact; subset of same-file L19. |
| D65 | `Cart/Feature/Migration/MigrationTest.php` | 530,543 | instance name for auth user / guest | REDUNDANT | M | No auth tested, only setInstance echo; covered by CartInstancesTest 'returns current instance name'. |
| D66 | `Cart/Unit/Models/CartConditionTest.php` | 155,173 | validates properties / create from array | REDUNDANT | M | Subsets of same-file L304 and L429 respectively. |
| D67 | `Cart/Unit/Models/CartItemTest.php` | 216,800 | add-remove attributes / CartConditionCollection | REDUNDANT | M | Covered by same-file L603 + L619 and L891 respectively. |
| D68 | `Cart/Unit/Models/CartTest.php` | 551,901,910 | item counts / getCurrentInstance / store data | REDUNDANT | M | Covered by same-file L424, L885, L144 (no public `store()` exists; save is private). |
| D69 | `Cart/Unit/Exceptions/UnknownModelExceptionTest.php` | 36 | extends exception class | REDUNDANT | LM | Dup of instanceof in same-file L7; class has no logic. |
| D70 | `FilamentCart/Feature/Widgets/CartStatsWidgetTest.php` | 23 | can be instantiated | REDUNDANT | LM | instanceof-self; subsumed by WidgetsTest 'can instantiate CartStatsWidget'. |
| D71 | `tests/src/Shipping/Feature/Actions/CreateShipmentTest.php` | 90 | defaults to draft status | REDUNDANT | high | Dup of L74 same file, identical setup + assert. |
| D72 | `tests/src/Shipping/Feature/Actions/ShipShipmentTest.php` | 75 | generates label when driver supports it | REDUNDANT | high | Subset of L19 same file; supports=false, no label assert, unused `$labelData`. |
| D73 | `tests/src/Shipping/Unit/Services/FreeShippingEvaluatorTest.php` | 116 | creates result with all properties | REDUNDANT | high | Dup of FreeShippingResultTest L8 + L22. |
| D74 | `tests/src/Shipping/Unit/Services/FreeShippingEvaluatorTest.php` | 130 | formats remaining amount as currency | REDUNDANT | high | Byte-identical to FreeShippingResultTest L31. |
| D75 | `tests/src/Shipping/Unit/Services/FreeShippingEvaluatorTest.php` | 139 | returns null formatted remaining | REDUNDANT | high | Identical to FreeShippingResultTest L40. |
| D76 | `tests/src/Jnt/Unit/OrderBuilderTest.php` | 73 | throws when orderId missing | REDUNDANT | high | Identical to OrderBuilderValidationTest L13. |
| D77 | `tests/src/Jnt/Unit/OrderBuilderTest.php` | 78 | throws when sender missing | REDUNDANT | high | Identical to OrderBuilderValidationTest L20. |
| D78 | `tests/src/Jnt/Unit/OrderBuilderTest.php` | 83 | throws when items empty | REDUNDANT | high | Identical to OrderBuilderValidationTest L42. |
| D79 | `tests/src/Jnt/Unit/SignatureTest.php` | 28 | digest does not match random strings | REDUNDANT | high | Subsumed by L7 same file (pins exact digest). |
| D80 | `tests/src/Jnt/Unit/Services/WebhookServiceTest.php` | 61 | uses timing-safe comparison | REDUNDANT | high | Same assert as L18 same file; timing unobservable. |
| D81 | `tests/src/Jnt/Unit/Services/BatchOperationsTest.php` | 114 | includes exception details | REDUNDANT | high | Subsumed by L364 same file (superset). |
| D82 | `tests/src/FilamentShipping/Unit/Services/CartBridgeTest.php` | 201 | returns boolean | REDUNDANT | high | Subsumed by L207 same file (true implies bool). |
| D83 | `tests/src/Shipping/Unit/Services/TrackingAggregatorTest.php` | 79 | syncs tracking and updates shipment | PERM-SKIPPED | high | Empty body + skip; covered by TrackingEventBatchDedupTest. |
| D84 | `Contacting/ContactMethodTest.php` | 16 | model class exists | TRIVIAL | med | `class_exists` + `expect(true)->toBeTrue()` leftover; model used by all other tests in file. |
| D85 | `Contacting/SocialProfileTest.php` | 15 | model class exists | TRIVIAL | med | Same `class_exists` + tautology placeholder pattern. |
| D86 | `Contacting/ContactSnapshotTest.php` | 11 | action can be instantiated | TRIVIAL | med | new-X-instanceof-X; covered by persists/throws tests same file. |
| D87 | `FilamentDocs/Unit/PagesTest.php` | 17 | create page instantiated | TRIVIAL | med-high | new CreateDoc instanceof CreateDoc; wiring in CoverageBoostTest. |
| D88 | `FilamentDocs/Unit/PagesTest.php` | 23 | edit page instantiated | TRIVIAL | med-high | Same tautology for EditDoc. |
| D89 | `FilamentDocs/Unit/PagesTest.php` | 29 | view page instantiated | TRIVIAL | med-high | Same tautology for ViewDoc. |
| D90 | `FilamentContacting/PackageTest.php` | 98 | table classes exist | PERM-SKIPPED | med-high | Unconditional skip, bogus needs-app reason; file uses app at L30–49. |
| D91 | `FilamentContacting/PackageTest.php` | 104 | resource classes exist | PERM-SKIPPED | med-high | Same; classes referenced at L47–49. |
| D92 | `FilamentContacting/PackageTest.php` | 110 | relation managers exist | PERM-SKIPPED | med-high | Same; managers used in RegressionTest. |
| D93 | `FilamentContacting/PackageTest.php` | 115 | export/import exist | PERM-SKIPPED | med-high | Same; importers used in RegressionTest. |
| D94 | `FilamentCommunications/ResourceTest.php` | 45 | nav group not null | REDUNDANT | med | Subsumed by L33 exact-value test, same dataset. |
| D95 | `Vouchers/Unit/Compound/AbstractProductMatcherTest.php` | 113–144 | 7 skipped filter/getMatchingItems tests | PERM-SKIPPED | high | Tautology bodies; covered w/ real CartItems by Matchers/AbstractProductMatcherTest L130–230. |
| D96 | `Vouchers/Unit/ItemSelectionStrategyEnumTest.php` + `ProductMatcherTypeEnumTest.php` + `VoucherEnumsTest.php:33` | — / — / 33 | 7 enum value/label tests | REDUNDANT | high | All assertions dup in CompoundEnumsTest and VoucherTypeEnumTest L9–22. |
| D97 | `Vouchers/Unit/StackingPoliciesTest.php` | 14–55, 90–122 | 8 enum + policy tests | REDUNDANT | med-high | Dup of StackingEnumsTest and Stacking/StackingPolicyTest L55–71, L111–128. |
| D98 | `Vouchers/Unit/VoucherUsageTest.php` + `VoucherUsageModelTest.php` + `Models/VoucherUsageModelTest.php:213` | — / — / 213 | 6 relations/manual/constants/timestamps/Attribute tests | REDUNDANT | med-high | All dup by Models/VoucherUsageModelTest relations/isManual/constants/Accessor tests. |
| D99 | `Vouchers/Unit/Services/VoucherValidatorTest.php` | 194–215 | VVR integration x3 | REDUNDANT | high | Dup of Data/VoucherValidationResultTest L31–56. |
| D100 | `Orders/OrderStateCoverageTest.php` | 20–134 | 8 single-step transition tests | REDUNDANT | high | All 8 edges in OrderTransitionMatrixTest L32–53, same mechanism. |
| D101 | `Orders/OrdersServiceProviderTest.php:18` + `GenerateInvoiceTest.php` + `GenerateReceiptTest.php:13` + `OrderProcessingCheckTest.php:14` + `AffiliateRegistrar:87` | — | 10 instantiation/method-existence smoke tests | REDUNDANT | med | Subsumed by same-file invoking siblings. |
| D102 | `Vouchers/Unit/Support/AffiliateIntegrationRegistrarTest.php` | 165–190 | 3 code-format tests | TRIVIAL | high | Inline PHP string math, no project code; real coverage L194–253. |
| D103 | `Vouchers/Unit/Support/AffiliateIntegrationRegistrarTest.php` | 104–123 | has-voucher exists query | REDUNDANT | med | Raw Eloquent `exists()`; covered by same-file L125–163, L276–304. |
| D104 | `Vouchers/Unit/VoucherTest.php:91` + `Orders/OrderModelTest.php:54` | 91 / 54 | remaining-uses + default-created | REDUNDANT | med-high | Dup of Scopes L124–146 / Models L303–324 and OrderDefaultStateTest L10–19. |
| D105 | `Vouchers/Unit/VoucherModelScopesTest.php` | 72–106, 244–295 | 7 method/stat tests | REDUNDANT | med-high | Dup of Models/VoucherModelTest matrices + AppliedCountTest stats. |
| D106 | `Vouchers/Unit/ProductMatchersTest.php` + `Compound/Conditions/CompoundVoucherConditionTest.php:241` | — / 241 | 20 tests + 4 sections matcher/condition behavior | REDUNDANT | med-high | Dup of dedicated Matcher/Condition files w/ weaker assertions. |
| D107 | `Customers/SetDefaultCustomerAddressTest.php` | 18 | default-address-changes-only-requested-type | REDUNDANT | high | Dup of AddressModelTest:71 same setup + asserts. |
| D108 | `Customers/SetDefaultCustomerAddressTest.php` | 50 | attaches-persisted-address | REDUNDANT | high | Dup of AddressModelTest:103. |
| D109 | `Customers/SetDefaultCustomerAddressTest.php` | 63 | rejects-unsaved-addresses | REDUNDANT | high | Dup of AddressModelTest:116 incl message. |
| D110 | `Customers/CustomerExtendedTest.php` | 121 | returns-combined-first-last-name | REDUNDANT | high | Dup of CustomerModelTest:47 full_name. |
| D111 | `Customers/SegmentModelTest.php` | 370 | skips-conditions-without-field | REDUNDANT | high | Type-only assert; behavior in RegressionTest:312. |
| D112 | `Customers/SegmentModelTest.php` | 386 | skips-conditions-without-value | REDUNDANT | high | Type-only assert; behavior in RegressionTest:312. |
| D113 | `Customers/VerifierCustomerEmailUniquenessTest.php` | 88 | enforces-uniqueness-at-db-level | REDUNDANT | high | Subset of same-file:12. |
| D114 | `Customers/RebuildSegmentsCommandTest.php` | 11 | can-be-instantiated | TRIVIAL | high | new-X-instanceof-X, 11 siblings construct. |
| D115 | `Customers/SegmentationServiceTest.php` | 48 | can-be-instantiated | TRIVIAL | high | beforeEach service instanceof itself. |
| D116 | `Customers/ServiceProviderTest.php` | 38 | can-boot-without-errors | TRIVIAL | med | `expect(true)` tautology, 4 siblings boot. |
| D117 | `Customers/AddressAndGroupsTest.php` | 173 | can-filter-active-segments | REDUNDANT | med | Weaker than SegmentModelTest:249. |
| D118 | `FilamentPromotions/PromotionTypeEnumTest.php` | whole file | 9 tests | REDUNDANT | high | Covered by `Promotions/PromotionTypeEnumTest.php` (exact label/color/values/from); only unique is trivial count. See whole-file table. |
| D119 | `Pricing/PricingServiceProviderTest.php` | 28,34,53 | has register/boot/calculate method | TRIVIAL | high | `method_exists` checks; register/boot run in beforeEach; calculate covered by PriceCalculatorTest. |
| D120 | `Pricing/PricingSettingsTest.php` | 36,40 | has getCurrencySymbol/formatAmount method | REDUNDANT | high | Implied by same-file tests invoking them via reflection. |
| D121 | `FilamentProducts/Integration/WidgetsAndPagesTest.php` | 186 | products table builds correctly | IRRELEVANT | high | Tests local helper `makeProductsTable()` + Filament, not project code. |
| D122 | `Products/Actions/ProductActionsTest.php` | 38 | dispatches ProductCreated event | REDUNDANT | med | Covered by `Products/ProductEventDispatchTest.php` exactly-once test (same action + event). |
| D123 | `Promotions/PromotionAndPricingModelsTest.php` | 43–91 | PriceList scheduling x4 | REDUNDANT | med-high | isActive scheduling covered by `Pricing/PriceListModelExtendedTest.php`. |
| D124 | `Cashier/Unit/ServiceProviderAdditionalTest.php` | 13,20,27 | merges config / singleton / alias | REDUNDANT | high | Identical to Feature/ServiceProviderTest L27, L14, L21. |
| D125 | `Cashier/Unit/CashierAdditionalTest.php` | 33,41,50,95,103,111 | manager / alias / formatter / syncs / customerModel | REDUNDANT | high | Covered by CashierTest L18,49,57,43 and StaticMethodsTest L125,150. |
| D126 | `Cashier/Unit/CashierStaticMethodsTest.php` | 25,31,38,54,61,68,138 | false-setters / syncs / customerModel | REDUNDANT | high | Covered by CashierTest L49,57,43; syncs comments admit no sync verified. |
| D127 | `Cashier/Unit/GatewayManagerAdditionalTest.php` | 25,33,41,51,62,120 | default / null / chip / list / supports / extend | REDUNDANT | high | Covered by GatewayManagerTest L33,13,26,39,47,65; `gateway(null) === gateway()`. |
| D128 | `Cashier/Unit/GatewaysTest.php` | 26,33,40,48,60,66,111,129 | abstract / name / currency / contract x2 gateways | REDUNDANT | high | Covered by AbstractGatewayTest L78,84,88,35,39 and GatewayManagerTest L13,19,26. |
| D129 | `Cashier/Unit/AbstractGatewayProtectedMethodsTest.php` | 363 | isTestMode false by default | REDUNDANT | med | Same as AbstractGatewayTest L66. |
| D130 | `FilamentCashier/Unit/FilamentCashierPluginTest.php` | 83,89,96 | detector instantiate / collection / options | REDUNDANT | high | Identical to GatewayDetectorTest L25,31,93. |
| D131 | `FilamentCashier/Unit/InvoiceStatusTest.php` + `SubscriptionStatusTest.php` | 30 / 36 | color dataset tests | REDUNDANT | high | Subsumed by same-file exact color tests (all 5 + 8 cases). |
| D132 | `FilamentSignals/.../SignalsStatsWidgetTest.php` | 264 | can instantiate widget | TRIVIAL | med-high | new + instanceof tautology; resolution covered by L270. |
| D133 | `Seating/Unit/AllocationResultTest.php` | 33 | has expected properties | REDUNDANT | med | Subsumed by 'can be created with required data' + 'with category' same file. |
| D134 | `Addressing/Support/NormalizeNavigationUrlTest.php` | 47 | does not perform http requests | REDUNDANT | med | Repeats 'accepts google maps app link' (L24); no HTTP assertion. |
| D135 | `AffiliateNetwork/Unit/Actions/ApproveApplicationTest.php` | 31 | dispatches ApplicationApproved event | REDUNDANT | med | Subset of 'approves pending application' (L17) same file. |
| D136 | `AffiliateNetwork/Unit/Actions/CreateOfferTest.php` | 34 | dispatches OfferCreated event | REDUNDANT | med | Subset of 'creates offer with required data' (L19) same file. |
| D137 | `AffiliateNetwork/Unit/Actions/ApplyToOfferTest.php` | 63 | dispatches ApplicationSubmitted event | REDUNDANT | med | Subset of 'creates pending application…' (L21) same file. |
| D138 | `FilamentAddressing/FilamentAddressingPluginTest.php` | 30 | has correct plugin id | REDUNDANT | low | getId already asserted at L7 same file. |
| D139 | `Membership/Unit/MembershipApplicationTest.php` | 38 | casts status to enum | REDUNDANT | med | Subsumed by `->status->toBe(Pending)` in 'creates…' (L23). |
| D140 | `demo/tests/Unit/ExampleTest.php` | 5 | that true is true | TRIVIAL | high | Pest scaffold tautology, no project code. See whole-file table. |
| D141 | `tests/src/Communications/TrackingTokenTest.php` | 8 | RecordTrackingInteractionAction exists | REDUNDANT | high | Dup of TrackingTest.php:14 same assertion. |
| D142 | `tests/src/Communications/TrackingTokenTest.php` | 13 | CreateTrackingTokenAction exists | TRIVIAL | med | Bare class_exists; covered by RegressionTest.php:177+192. |
| D143 | `tests/src/Communications/PublishTemplateActionTest.php` | 11 | exists and can be instantiated | REDUNDANT | low | Covered by same-file L17. |
| D144 | `tests/src/Communications/CommandTest.php` | 168 | Reconcile cmd can be instantiated | REDUNDANT | low | Covered by same-file Artisan tests L173/179/188. |
| D145 | `tests/src/Communications/CommandTest.php` | 227 | Prune cmd can be instantiated | REDUNDANT | low | Covered by same-file Artisan tests L232/237. |
| D146 | `tests/src/FilamentAuthz/Unit/CommandsTest.php` | 15,55,123,144,165 | exists x5 | REDUNDANT | med | Each covered by same-describe signature test via `app()`. |
| D147 | `tests/src/FilamentAuthz/Unit/TraitsTest.php` | 37,59,69,75 | exists x4 | REDUNDANT | med | Covered by same-file overrides, RegressionTest:64, Authz SyncsRolePermissionsTest. |
| D148 | `tests/src/FilamentAuthz/Unit/PluginMultiPanelTest.php` | 94 | has correct plugin id | REDUNDANT | med | Dup of PluginTest.php:15. |
| D149 | `tests/src/FilamentAuthz/Unit/PluginMultiPanelTest.php` | 100 | can be instantiated via make | REDUNDANT | med | Dup of PluginTest.php:9. |
| D150 | `tests/Feature/Migrations/MigrationsRunTest.php` | 10 | runs migrations, core tables | REDUNDANT | high | Subset of PackageDiscoverabilityTest.php:53; same group. |

## COLLAPSE (29)

| ID | File | Line(s) | Test(s) | Category | Conf | Collapse instruction |
|---|---|---|---|---|---|---|
| C1 | `Tax/TaxModelsTest.php` | 15 | country zone create | REDUNDANT | low | Fold ZoneType assert into TaxZoneTest; keep state/postcode creates. |
| C2 | `Tax/Unit/Services/TaxCalculatorEdgeCasesTest.php` | 423 | fallback via config | LOW-VALUE-DUP | med | Same-file dup of L112; only rate 550 vs 500 differs — merge. |
| C3 | `Tax/Unit/Services/TaxCalculatorTest.php` | 387 | address priority | LOW-VALUE-DUP | low | Weaker than EdgeCases:447 (only billing zone exists) — merge. |
| C4 | `Inventory/Unit/Enums/*Test.php` | 7 (x5 files) | value/count/label/desc/status subsets | REDUNDANT | med | Covered by Unit/*Test counterparts; keep from/tryFrom/throws. |
| C5 | `Inventory/Unit/Models/InventoryLevelTest.php` | 166 | effective strategy pair | LOW-VALUE-DUP | med | Same assert; Fifo vs LeastStock only — merge. |
| C6 | `Tax/Unit/Models/TaxZoneTest.php` | 150,164,178,512 | postcode quartet | LOW-VALUE-DUP | low | Basics dup of PostcodeMatchingTest edge-case file — merge. |
| C7 | `Cart/Unit/Models/CartConditionTest.php` | 327 | validates condition target values | REDUNDANT | L | Same malformed-target path as same-file L164; merge inputs. |
| C8 | `tests/src/FilamentShipping/Unit/FilamentShippingPluginTest.php` | 35–105 | 8x can-disable/chaining instanceof-only | LOW-VALUE-DUP | high | Fold into L84; effect covered by L144. |
| C9 | `tests/src/Shipping/Unit/Services/BatchRateLimiterTest.php` | 65–89 | 5x can-configure instanceof-only | LOW-VALUE-DUP | high | Fold into one chaining test; setters covered in execute() tests. |
| C10 | `tests/src/Shipping/Unit/Services/RetryServiceTest.php` | 92–110 | 4x setter instanceof-only | LOW-VALUE-DUP | high | Fold into one chaining test (sole backoff/jitter coverage — keep it). |
| C11 | `tests/src/Jnt/Unit/Support/TypeTransformerTest.php` | 181–219 | 4x scenario re-tests | LOW-VALUE-DUP | high | Fold values into granular tests L54–134. |
| C12 | `CommerceSupport/JsonColumnTypeConfigTest.php` | 34–138 | 10 pkg fallback/override | LOW-VALUE-DUP | low | Identical shape x10; collapse to 2 dataset tests. |
| C13 | `Vouchers/Unit/Data/VoucherValidationResultTest.php:185` + `Models/VoucherUsageModelTest.php:324` + `Models/VoucherModelTest.php:340` | — | 10 scenario/getTable permutation tests | LOW-VALUE-DUP | med-high | Same-file near-identical permutations; collapse each group to one. |
| C14 | `Products/AttributeTypeTest.php` | whole file | enum tests | LOW-VALUE-DUP | high | Merge into `Products/Enums/AttributeTypeTest.php` (keep Enums from()/values + exact label keys), then delete file. See whole-file table. |
| C15 | `Products/ProductStatusTest.php` | whole file | enum tests | LOW-VALUE-DUP | high | Merge into `Products/Enums/ProductStatusTest.php` (keep Enums from()/values + exact labels), then delete file. |
| C16 | `Products/ProductVisibilityTest.php` | whole file | enum tests | LOW-VALUE-DUP | high | Merge into `Products/Enums/ProductVisibilityTest.php` (keep Enums values/from/color + exact labels), then delete file. |
| C17 | `Products/MassAssignmentGuardTest.php` | 16–74 | whole file (10x guards id) | LOW-VALUE-DUP | med | Identical per-model; collapse to dataset (cf Growth FactoryOwnershipTest loop). |
| C18 | `Pricing/PriceCalculatorTest.php` | 115–145 | accepts params x4 | LOW-VALUE-DUP | med | instanceof-only; effects covered by tier/customer/segment/list tests; keep one smoke test. |
| C19 | `FilamentCashier/Unit/UnifiedInvoiceTest.php` | 82,104 | pdf url set/null | LOW-VALUE-DUP | med | Near-identical DTO-echo bodies; merge. |
| C20 | `FilamentCashierChip/Unit/{ServiceProvider,BillingDashboard,BaseCashierChipResource,Subscription,Invoice,Customer}ResourceTest.php` + `WidgetsTest.php` | structure blocks | 5–12 near-identical reflection checks per file | LOW-VALUE-DUP | med | Collapse each file's structure block to one test. |
| C21 | `FilamentCashierChip/Unit/FilamentCashierChipPluginTest.php` | 98–126 | 5 fluent-return tests | LOW-VALUE-DUP | med | Five identical `->toBe($plugin)`; merge into one. |
| C22 | `Addressing/Database/NavigationLinksMigrationTest.php` | 14–76 | has {col} on {2 tables} x16 | LOW-VALUE-DUP | high | 16 one-assert hasColumn tests; loop like OwnerColumnsMigrationTest. |
| C23 | `Seating/Feature/EnsureSeatHoldActionTest.php` | 42 (+53) | empty for None mode + for GA mode | LOW-VALUE-DUP | med | Same `!requiresSeatAllocation()` branch (EnsureSeatHoldAction.php:35); dataset it. |
| C24 | `AffiliateNetwork/Unit/Actions/CreateOfferTest.php` | 42 (+50) | draft status approval-required + not-required | LOW-VALUE-DUP | low | Identical Draft outcome; iterate both configs in one test. |
| C25 | `tests/src/Moderation/InstallationTest.php` | 73 | helper config sources resolve | REDUNDANT | med | 2 asserts dup L20–23; merge unique one. |
| C26 | `tests/src/FilamentAuthz/Unit/PluginConfigurationTest.php` | 87–127 | supports 6 cases | LOW-VALUE-DUP | med | instanceof-only; getter covered by L133 + AuthzServiceTest. |
| C27 | `tests/src/Communications/CommunicationsEnumsTest.php` | 192 | all enums string-backed | LOW-VALUE-DUP | low | Dup of per-enum describes. |
| C28 | `tests/src/References/EnumsTest.php` | 59 | all enums string-backed | LOW-VALUE-DUP | low | Dup of per-enum describes. |
| C29 | `tests/src/Moderation/EnumsTest.php` | 59 | all enums string-backed | LOW-VALUE-DUP | low | Dup of per-enum describes. |

## HUMAN-CALL (52)

These need a human decision (strengthen the assertion, merge a unique angle, or confirm deletion). Do not bulk-delete.

| ID | File | Line(s) | Test(s) | Category | Conf | Reason / decision needed |
|---|---|---|---|---|---|---|
| H1 | `Tax/Unit/Models/Tax*Test.php` | 186,191,277,329 | 4x activity logging | ✅ RESOLVED | low | Strengthened (no `assertTrue(true)` remains in Tax model tests). |
| H2 | `Inventory/Listeners/*Test.php` + `FilamentInventory/ActionsTest.php` | 75,213 / 83 | 3x no-throw | ✅ RESOLVED | low | Converted (no `expect(true)` fillers remain in those files). |
| H3 | `CashierChip/Feature/ManagesInvoicesTest.php` | 17,33 | weak asserts x2 | ✅ RESOLVED | med | Hardened with strict instanceof/equals/count asserts. |
| H4 | `Chip/LocalAnalyticsIntegrationTest.php` + `HttpTest.php` + `ExceptionsAndHealthTest.php` + `PurchaseActionsTest.php` | 19–53; 318; 24; 12–44 | refl/inst/weak/collapse | ✅ RESOLVED | low-med | Hardened with `Log::spy`/`Log::fake` coverage. |
| H5 | `Cart/Unit/Models/CartTest.php` | 989,999 | subtotal/total alias tests | ✅ RESOLVED | L | Fixed: aliases compared against protected getters via reflection + pinned values (5100/12000). |
| H6 | `Cart/Unit/Exceptions/UnknownModelExceptionTest.php` | 42,50,57 | thrown-caught / hierarchy / message info | ✅ RESOLVED | L | Collapsed to one test pinning default message + CartException hierarchy. |
| H7 | `Cart/Feature/Database/LockingTest.php` | 65 | uses version numbers | ✅ RESOLVED | L | Removed; covered with real assertions by StorageVersionAndIdTest. |
| H8 | `FilamentCart/Unit/Jobs/SyncNormalizedCartJobTest.php` | 37 | defaults queue when config not set | ✅ RESOLVED | M | Fixed: asserts queue `cart-sync` + connection default (array-rebuild removal). |
| H9 | `Checkout/PaymentFlowTest.php` | 689,744,797 | commits inventory reservations x3 | ✅ RESOLVED | LM | Renamed/recast as honest order-creation tests; inventory coupling covered by reserve/release tests (L1067/1104). |
| H10 | `Checkout/PaymentFlowTest.php` | 928 | redeems vouchers after order | ✅ RESOLVED | LM | Renamed to metadata-passthrough truth (L914): voucher codes into order metadata. |
| H11 | `tests/src/Shipping/Unit/Services/RateShoppingEngineTest.php` | 178 | can clear cache | ✅ RESOLVED | med | Added `->once()` flush/tags mock expectations. |
| H12 | `tests/src/Shipping/Unit/Services/ShippingZoneResolverTest.php` | 127 | can clear cache | ✅ RESOLVED | med | Seeds a zone, asserts cached instance reuse. |
| H13 | `tests/src/Jnt/Unit/Health/JntHealthCheckTest.php` | 35,45 | warning/configured instanceof-only | ✅ RESOLVED | low | Asserts `Status::warning()` + short summary text. |
| H14 | `Docs/Feature/SequenceManagerTest.php` | 149 | padding respected | ✅ RESOLVED | low | Pins exact zero-padded `000001` output. |
| H15 | `FilamentDocs/Unit/DocTemplateResourceTest.php` | 28 | correct relations | ✅ RESOLVED | low | Asserts exact `toBe([])` relations. |
| H16 | `FilamentDocs/Unit/FilamentDocsPluginTest.php` | 110 | custom resource fluent API | ✅ RESOLVED | low | Passes anonymous custom resource subclass, asserts panel registration. |
| H17 | `FilamentDocs/Unit/DocResourceTest.php` | 48 | badge color reflection | ✅ RESOLVED | low | Behavior test with real docs pins badge count `2`. |
| H18 | `FilamentCommunications/ResourceTest.php` | 53 | getEloquentQuery exists | ✅ RESOLVED | low | Strengthened: now calls `getEloquentQuery()` in explicit-global context, asserts Eloquent Builder. |
| H19 | `FilamentCommunications/ResourceTest.php` | 89 | widget instantiated | ✅ RESOLVED | low | Deleted: RegressionTest instantiates widget + invokes getStats with value asserts. (Plugin-id test also moved to RegressionTest registry describe.) |
| H20 | `Contacting/ContactSnapshotTest.php` | 52 | action methods exist | ✅ RESOLVED | low | Replaced with behavioral `fromSocialProfile` persistence test (fromBundle already in RegressionTest). |
| H21 | `Vouchers/Unit/Compound/AbstractProductMatcherTest.php` | 14–155 | create block + interface test | ✅ RESOLVED | med-high | Deleted file, no fold: keeper is `Compound/Matchers/…` (not `Matchers/…`); deletable variants were stale (price operator/value ignored by fromArray) or default-equal. |
| H22 | `Vouchers/Unit/VoucherValidationExceptionTest.php` | 8–29 | 2 factory tests | ✅ RESOLVED | med | Deleted file; StackingExceptionsTest covers both factories with stronger asserts + default-ctor case. |
| H23 | `Vouchers/Unit/VoucherUsageModelTest.php` | 88–105, 128–139 | casts + N/A identifier tests | ✅ RESOLVED | med | Deleted both tests: asserted on in-memory instance (no re-fetch), pure dups of finer-grained Models/ tests. |
| H24 | `Orders/OrderStateCoverageTest.php` | 138–217 | chained transitions test | ✅ RESOLVED | med | Deleted file: all 11 edges in OrderTransitionMatrixTest; "chaining" used 4 separate instances anyway. |
| H25 | `Vouchers/Unit/VoucherModelScopesTest.php` | 217–242 | conversion-rate test | ✅ RESOLVED | med | Folded 0.0 branch into VoucherAppliedCountTest as its own test; deleted scopes pct test (dup). |
| H26 | `Vouchers/Unit/VoucherServiceProviderTest.php` | 169–187 | VoucherApplied listener test | ✅ RESOLVED | med | Fixed: replaced dead loop with `getRawListeners()->toContain(...)`, matching sibling checkout test. |
| H27 | `Customers/RebuildAllSegmentsActionTest.php` | 67 | returns-count-for-auto-segment | ✅ RESOLVED | high | Strengthened `>=0` → `toBe(1)` (rebuildSegment returns match count). |
| H28 | `Customers/RebuildAllSegmentsActionTest.php` | 200 | handles-global-segments | ✅ RESOLVED | high | Deleted: identical setup to H27 with no owner angle; whole owner-scoped describe removed. |
| H29 | `Customers/SegmentationServiceTest.php` | 83 | rebuilds-automatic-segment | ✅ RESOLVED | high | Strengthened `>=0` → `toBe(1)` (service delegates to action). |
| H30 | `Events/EventLifecycleWorkflowTest.php` | 13 | publishes-event | ✅ RESOLVED | high | Deleted: strict subset of EventCreationTest publish test. |
| H31 | `Customers/CustomerExtendedTest.php` | 176 | registers-media-collections | ✅ RESOLVED | med | Rewrote to assert collection names `['avatar','documents']` via getRegisteredMediaCollections (EventMediaTest pattern). |
| H32 | `Customers/RebuildAllSegmentsActionTest.php` | 41 | results-keyed-by-name | ✅ RESOLVED | med | Rewrote with real owner fixture: asserts `[$name => 1]` (old test ran on empty results — scoping excluded globals). |
| H33 | `Customers/CustomerModelTest.php` | 121 | filter-marketing-opted-in | ✅ RESOLVED | med | Deleted: no marketing scope exists; opt-in/out covered by neighboring tests. |
| H34 | `Products/VariantGenerationTest.php` | 133 | missing job product no-op | ✅ RESOLVED | med | Converted to `not->toThrow(Exception::class)` on `handle()`. |
| H35 | `FilamentProducts/Integration/ServiceProviderTest.php` | 10 | boots service provider | ✅ RESOLVED | med | Converted `boot()` call to `not->toThrow(Exception::class)`. |
| H36 | `FilamentProducts/Integration/ResourcePagesMutatorsTest.php` | 109 | redirect url methods may throw | ✅ RESOLVED | low | Deleted: throw-assertion would pass even if `getResource()` broke; covered only 3 of 6 identical one-line overrides. |
| H37 | `Cashier/Feature/ServiceProviderTest.php` | 61 | migrations publishable else-branch | ✅ RESOLVED | low-med | Else-branch now `markTestSkipped('laravel/cashier is not installed.')`. |
| H38 | `Cashier/Unit/ExceptionsTest.php` | 36 | forGateway unknown | ✅ RESOLVED | med | Deleted describe; keeper pins message content (`paypal` + `not found`) plus ctor/previous/forDriver cases. |
| H39 | `FilamentCashier/Unit/UnifiedSubscriptionTest.php` | 88,113 | billingCycle monthly/yearly | ✅ RESOLVED | med-low | Assert exact `'Monthly'` / `'Yearly'` (pins branch selection, not just string type). |
| H40 | `FilamentCashier/Feature/CoverageBoostTest.php` | 303,306 | dup assertions in mega-test | ✅ RESOLVED | med | Deleted the 2 dup lines (kept cleaner `toBe(0)`; mega-test still 101 assertions). |
| H41 | `FilamentAddressing/AddressCountryResourceTest.php` | 57 | …does not expose delete bulk action… | ✅ RESOLVED | med | Rewrote to assert `getBulkActions()` is `[]` on the real table (guards read-only resource against future bulk actions). |
| H42 | `Seating/Feature/SeatAllocatorTest.php` | 95 | skips held seats | ✅ RESOLVED | low | Pins `ttl_minutes=15` via config()->set, dropped the `if` — assertions now unconditional. |
| H43 | `Membership/Unit/InstallationTest.php` | 36 | has membership application model | ✅ RESOLVED | low | Deleted: owner-scoping test calls `MembershipApplication::ownerScopeConfig()` (fatals if missing). |
| H44 | `Membership/Unit/InstallationTest.php` | 40 | has membership invitation model | ✅ RESOLVED | low | Deleted: same — `MembershipInvitation::ownerScopeConfig()` in neighboring test. |
| H45 | `Ticketing/Feature/BulkTransferPassesTest.php` | 13 | transfers multiple passes | ✅ RESOLVED | low | Deliberate keep both: strengthened happy path with input→output pass_id mapping (no drops/dupes); holder-row isolation stays in TransferSecurityTest. |
| H46 | `Addressing/Actions/ImportPostalCodesActionTest.php` | 131 | does not delete source-owned coverage… | ✅ RESOLVED | low | Deleted 'scopes source coverage…' (L108); kept the import-action-seeded version with the behavior-focused name. |
| H47 | `tests/src/Communications/PublishTemplateActionTest.php` | 39 | dispatches TemplatePublished event | ✅ RESOLVED | high | Rewrote to really publish a version (explicit-global owner context) + assertDispatched with payload checks. |
| H48 | `tests/src/Communications/WebhookTest.php` | 157 | recordWebhookReplay does not throw | ✅ RESOLVED | low | Converted to `not->toThrow(Exception::class)` on the call. |
| H49 | `tests/src/Communications/NotificationIntegrationTest.php` | 104 | handles null communicationId | ✅ RESOLVED | low | Converted to `not->toThrow(Exception::class)` on the call. |
| H50 | `tests/src/FilamentAuthz/Unit/AuthzServiceTest.php` | 82 | can clear cache | ✅ RESOLVED | med | Seeds both in-memory caches via reflection, asserts `clearCache()` empties them. |
| H51 | `tests/src/FilamentAuthz/Unit/RegressionTest.php` | 553 | ignores null records | ✅ RESOLVED | low | Stronger than suggested: attaches container harness, asserts `getRawState()` stays null. |
| H52 | `tests/src/Organizations/Unit/OrganizationLifecycleGuardsTest.php` | 101 | denies unknown abilities | ✅ RESOLVED | low | Converted filler to `not->toThrow(AuthorizationException)` on the two positive authorizations. |

## Appendix: audit coverage

Batches reviewed (12 prior result refs: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12):

| Batch | Scope | Candidates |
|---|---|---|
| Inventory / Tax | Inventory + Tax unit/feature tests | 21 (D1–D13, C1–C6, H1–H2) |
| Affiliates | Affiliates + FilamentAffiliates unit tests | 28 (D14–D41) |
| Chip / CashierChip | Chip + CashierChip unit/feature tests | 19 (D42–D58, H3–H4) |
| Cart / Checkout | Cart + FilamentCart + Checkout tests | 19 (D59–D70, C7, H5–H10) |
| Shipping / Jnt | Shipping + Jnt + FilamentShipping tests | 20 (D71–D83, C8–C11, H11–H13) |
| Contacting / Docs | Contacting + Docs + FilamentDocs/Contacting/Communications + CommerceSupport | 19 (D84–D94, C12, H14–H20) |
| Vouchers / Orders | Vouchers + Orders tests | 19 (D95–D106, C13, H21–H26) |
| Customers / Events | Customers + Events tests | 18 (D107–D117, H27–H33) |
| Products / Pricing | Products + Pricing + Promotions + FilamentProducts/Promotions | 14 (D118–D123, C14–C18, H34–H36) |
| Cashier | Cashier + FilamentCashier + FilamentCashierChip + FilamentSignals | 16 (D124–D132, C19–C21, H37–H40) |
| Seating / misc | Seating + Addressing + AffiliateNetwork + Membership + Ticketing + FilamentAddressing | 16 (D133–D139, C22–C24, H41–H46) |
| Communications / Authz | Communications + FilamentAuthz + Organizations + Moderation + References + Migrations + demo | 22 (D140–D150, C25–C29, H47–H52) |

Unreviewed / unresolved scope: none reported.

Spot-checks performed by the compiler: opened all 9 whole-file candidates (8 confirmed outright + `MassAssignmentGuardTest` confirmed as in-file COLLAPSE, not a file deletion); verified covering files exist (`Services/StockThresholdServiceTest.php` 26 tests, `ChipGatewayExtendedTest.php` 27 tests, `Promotions/PromotionTypeEnumTest.php` 11 tests, `CommandExecutionTest.php`, `CommandsFeatureTest.php`, `ChipPaymentIntentTest.php`, `Products/Enums/*Test.php` supersets); spot-checked 12 sample candidate paths — all resolve under `tests/src/`.
