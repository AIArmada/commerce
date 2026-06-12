# Documentation Guidelines

## Location and Structure
- Put package docs in `packages/<pkg>/docs/`.
- Required files: `01-overview.md`, `02-installation.md`, `03-configuration.md`, `04-usage.md`, `99-troubleshooting.md`.
- Use Markdown with YAML frontmatter. Every file must include a `title:` entry.

## Writing Rules
- Use `##` for main sections and `###` for subsections.
- Examples must be copy-paste ready, including imports and namespaces where relevant.
- Cross-reference related docs using relative links.
- Call out breaking changes explicitly and explain the migration path.

## Callouts
- Use the docs callout syntax consistently when a callout improves readability.
- Supported variants: `info`, `warning`, `tip`, `danger`.
