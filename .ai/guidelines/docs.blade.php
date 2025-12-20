# Documentation Guidelines
- **Location**: `packages/<pkg>/docs/`
- **Required files**: `01-overview`, `02-install`, `03-config`, `04-usage`, `99-trouble`
- **Format**: Markdown with YAML frontmatter (`title:`) at the top of every file.

## Content rules
- Use `##` for main sections, `###` for subsections.
- Examples must be copy-paste ready (include imports/namespaces where relevant).
- Cross-reference related docs using relative links.
- Call out breaking changes explicitly and explain the migration path.

## Callouts
- Import: `import Aside from "@components/Aside.astro"`
- Variants: `info`, `warning`, `tip`, `danger`