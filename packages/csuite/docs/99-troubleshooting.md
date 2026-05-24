---
title: Troubleshooting
---

# Troubleshooting

## Metapackage installs but no admin resources appear

**Likely cause:** the metapackage only installs packages; it does not automatically register every Filament plugin in your panel.

**Fix:** register the specific `filament-*` plugins your application should expose.

**Verify:** open the panel and confirm the expected navigation groups appear after plugin registration and cache clearing.

## Expected config file is missing

**Likely cause:** the bundle package does not publish a single shared config file for all packages.

**Fix:** publish the config for the underlying package you actually need, such as `cart-config`, `vouchers-config`, `chip-config`, or `docs-config`.

**Verify:** confirm the expected `config/*.php` file exists for the specific package you are configuring.

## Install succeeds but runtime behavior is incomplete

**Likely cause:** individual packages still need their own setup steps such as migrations, credentials, owner resolver binding, or plugin registration.

**Fix:** treat the metapackage as a dependency shortcut, not as a zero-config runtime bootstrap.

**Verify:** walk through the install and configuration docs for the included package you are trying to use.

## Composer conflicts during bundle installation

**Likely cause:** your application already pins dependencies that conflict with one of the bundled packages.

**Fix:** review the Composer conflict output and either align versions or install only the specific packages you need instead of the full metapackage.

**Verify:** rerun Composer after resolving the conflicting package constraints.