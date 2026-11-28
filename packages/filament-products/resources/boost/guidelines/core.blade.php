## AIArmada Filament Products

Keep resources and pages thin. Delegate catalog writes to named Products actions; use DeleteCatalogBulkAction for authorized bulk deletion. Use ProductStatsAggregator for dashboard reads and ProductStatsCache for cache ownership. Never write inventory or pricing tables from product forms; call the owning package's operations. Preserve exact owner scope and canonical composite identities.
