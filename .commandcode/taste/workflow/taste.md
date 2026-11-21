# Workflow

- Expects changes confined strictly to the files the user referenced (e.g. only the contents of an attached zip) — do not extend the same treatment to the repo's own equivalent files unless explicitly asked. Confidence: 0.7
- Distinguishes monorepo-only dev tooling (e.g. `commerce:boost-update`, root `boost.json`, generated `AGENTS.md`) from what a package ships to consumer apps; when the deliverable targets applications installing the package, do not modify the monorepo's own tooling to make it work locally — target and verify against a normal app's runtime instead. Confidence: 0.65
