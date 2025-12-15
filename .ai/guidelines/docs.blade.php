# Documentation Guidelines
- **Loc**: `packages/<pkg>/docs/`.
  - **Files**: `01-overview`, `02-install`, `03-config`, `04-usage`, `99-trouble`.
  - **Fmt**: Markdown + YAML Frontmatter (`title:`).

  ## Features
  - **Components**: Use `import Aside from "@components/Aside.astro"`.
  - **Variants**: `info`, `warning`, `tip`, `danger`.
  - **Content**: Copy-paste ready code examples. `##` headers. Explains breaking changes.