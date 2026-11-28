# FilamentRewards

Filament Rewards is the administrative entry point for Rewards. Read program-scoped records through `AIArmada\FilamentRewards\Queries\RewardProgramScopeQuery`; delegate durable mutations to Rewards Actions. A staff role or explicit `reward-staff` Gate for users without role methods is required at staff-only entry points. Recheck operation-specific abilities inside mutation handlers, not only in button visibility. Never widen tenant scope for a record mutation. Do not add compatibility aliases.
