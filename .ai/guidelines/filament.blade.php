# Filament Guidelines

## Platform Rules
- Use Filament v5 APIs.
- Filament v5 is the target surface. If v5 documentation is thin, the equivalent v4 examples are acceptable because the APIs are compatible.
- Use the official Filament plugins for Tags, Settings, Media, and Fonts when those capabilities are needed.
- Use the built-in `Import` and `Export` actions only.

## Tenancy
- Filament tenancy is not a security boundary. All queries and all action handlers must still obey the owner-scoping contract.

## Navigation

### Config Standard
Every `filament-*` package MUST use a nested `navigation.group` key in its config file:

```php
// config/filament-xxx.php
'navigation' => [
    'group' => 'Default Group Name',
],
```

Settings pages use `navigation.settings_group` instead of `navigation.group`.

### Resource/Page Standard
Every Resource or Page MUST use `getNavigationGroup()` reading from config. Do NOT use the `$navigationGroup` static property:

```php
public static function getNavigationGroup(): string | UnitEnum | null
{
    return config('filament-xxx.navigation.group');
}
```

### Navigation Sort
When navigation sort order is configurable, use `getNavigationSort()` reading from config:

```php
public static function getNavigationSort(): ?int
{
    return config('filament-xxx.navigation.sort');
}
```

### Run-time Overrides
The `CommerceNavigation` engine (from `commerce-support`) supports overriding any navigation setting at runtime via `commerce-support.filament.navigation.items.{FQCN}`. Config-driven navigation is the foundation that makes this work — the engine reads a resource's config default, then merges runtime overrides on top.

### What NOT to do
- Do NOT use `$navigationGroup` static property on a Resource or Page (blocks runtime override)
- Do NOT use flat config keys like `navigation_group` (use nested `navigation.group`)
- Do NOT hardcode a group string in `getNavigationGroup()` — always read from config
- Do NOT delegate through a plugin (avoid `Plugin::get()->getNavigationGroup()` pattern) — resources should read `config()` directly

## Verification
- Double-check method signatures in the installed Filament version before shipping.
- Verify no static `$navigationGroup` remains: `rg "static.*\\\$navigationGroup" packages/filament-*/src`
- Verify config uses nested key: `rg "'navigation_group'" packages/filament-*/config` (should be empty)
