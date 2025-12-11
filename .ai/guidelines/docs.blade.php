# Documentation Guidelines (Filament-Style)

Documentation follows Filament's structure: markdown files with Astro component imports stored in the main repo, consumed by a separate docs site.

## How Filament Does It

1. **Markdown in main repo** - `docs/` and `packages/*/docs/` contain plain markdown
2. **Astro imports in markdown** - Files include `import Aside from "@components/Aside.astro"` 
3. **Separate docs site** - A separate repository/project builds the actual website
4. **Docs site pulls markdown** - The Astro site copies/imports markdown from the main repo

## File Structure

### Naming Convention
```
packages/<package>/docs/
├── 01-overview.md           # Package introduction
├── 02-installation.md       # Setup instructions
├── 03-configuration.md      # Config options
├── 04-usage.md              # Basic usage
├── 05-<feature>.md          # Feature-specific docs
├── ...
└── 99-troubleshooting.md    # Common issues
```

- Use numbered prefixes (`01-`, `02-`) for ordering
- Use lowercase kebab-case for filenames
- One topic per file, max 500 lines

### Frontmatter (Required)
Every markdown file must have YAML frontmatter:

```yaml
---
title: Getting Started
---
```

Optional frontmatter fields:
```yaml
---
title: Overview
contents: false           # Hide table of contents
---
```

## Astro Components (For Future Docs Site)

Prepare markdown with Astro component imports that will work when the docs site is built:

```md
---
title: Configuration
---
import Aside from "@components/Aside.astro"
import AutoScreenshot from "@components/AutoScreenshot.astro"

## Introduction

<Aside variant="info">
    This feature requires PHP 8.4 or higher.
</Aside>

<Aside variant="warning">
    Breaking change in v2.0: The `oldMethod()` has been renamed to `newMethod()`.
</Aside>
```

### Available Components

| Component | Purpose | Variants |
|-----------|---------|----------|
| `<Aside>` | Callouts/alerts | `info`, `warning`, `tip`, `danger` |
| `<AutoScreenshot>` | Versioned screenshots | `version="1.x"` |
| `<Disclosure>` | Collapsible sections | - |

## Content Style

### Code Examples
Always include working, copy-paste ready examples:

```php
use AIArmada\Cart\Facades\Cart;

Cart::session('user-123')
    ->add([
        'id' => 'product-1',
        'name' => 'Product Name',
        'price' => 99.99,
        'quantity' => 1,
    ]);
```

### Headings
- `##` for main sections
- `###` for subsections
- `####` sparingly for deep nesting
- Never skip heading levels

### Links
Cross-reference related documentation:
```md
See the [configuration](configuration) documentation for details.
For panel setup, visit the [introduction/installation](../introduction/installation).
```

## Package Documentation Structure

Each package must have a `docs/` folder with:

1. **01-overview.md** - What it does, key features
2. **02-installation.md** - Composer, config, migrations
3. **03-configuration.md** - All config options explained
4. **04-usage.md** - Basic usage patterns
5. **Feature docs** - One file per major feature (numbered)
6. **99-troubleshooting.md** - Common issues and solutions

## Hosting on Dedicated Domain

### Option 1: Separate Docs Repository (Filament's Approach)

Create a separate repository for the docs site:

```
commerce-docs/           # Separate repo
├── astro.config.mjs
├── package.json
├── src/
│   ├── content/
│   │   └── docs/        # Markdown copied/synced from main repo
│   └── components/
│       ├── Aside.astro
│       ├── AutoScreenshot.astro
│       └── Disclosure.astro
# Documentation Guidelines (Filament-Style)

- Markdown lives in `docs/` and `packages/*/docs/`; Astro site consumes it later.
- File naming per package: 01-overview, 02-installation, 03-configuration, 04-usage, 05-<feature>..., 99-troubleshooting. Lowercase kebab, numbered, one topic per file, ≤500 lines.
- Frontmatter required with `title`; `contents: false` optional. Example:
```yaml
---
title: Configuration
---
import Aside from "@components/Aside.astro"
import AutoScreenshot from "@components/AutoScreenshot.astro"
```
- Components allowed: `<Aside variant="info|warning|tip|danger">`, `<AutoScreenshot version="1.x">`, `<Disclosure>`.
- Content style: working code samples, consistent heading levels (##, ###), cross-link related docs.
- Hosting/deploy: can be separate Astro repo or `docs-site/` subfolder; sync markdown then build with Astro/Starlight; deploy via Vercel/Netlify/CF Pages/GitHub Pages.
- Verification: ensure numbered files exist per package, frontmatter present, and filenames follow numbering.
```
