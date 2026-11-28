# Tax

Tax owns zones, rates, classes, exemptions and tax configuration. Use TaxCalculatorInterface and TaxResultData for calculations. TaxOwnerScope separates owner-scoped reads from strict write locks. Use DeleteTaxClassesAction, DeleteTaxRatesAction, DeleteTaxZonesAction and SetTaxRatesActiveAction for bulk writes; exemption lifecycle and settings writes belong in Actions. Never allow tenant mutation of global configuration records through include_global.

Do not introduce compatibility wrappers, dual writes, or backfills.
