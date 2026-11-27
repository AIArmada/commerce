# Workflow

- Expects changes confined strictly to the files the user referenced (e.g. only the contents of an attached zip) — do not extend the same treatment to the repo's own equivalent files unless explicitly asked. Confidence: 0.7
- When the user poses a question ("can we make X part of Y?", "is it possible to…"), answer it in the reply and stop — do not start editing files or "implementing" it until they explicitly ask for the change. Confidence: 0.85
- Prefers the CI pipeline to run the full test suite rather than waiting on a lengthy local full-suite run — commit and push, then verify via CI, using local runs only for targeted/failing tests. Confidence: 0.8
- Distinguishes monorepo-only dev tooling (e.g. `commerce:boost-update`, root `boost.json`, generated `AGENTS.md`) from what a package ships to consumer apps; when the deliverable targets applications installing the package, do not modify the monorepo's own tooling to make it work locally — target and verify against a normal app's runtime instead. Confidence: 0.65
