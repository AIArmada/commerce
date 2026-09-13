# RUNBOOK — verification pass + single verified file

## Context

Two review artifacts must be merged with per-finding verification into ONE file:

- `audits/e2e-review-2026-09-13.md` (459KB, as-reported reviews, 67 pkgs)
- `audits/all-packages-audit-2026-09-12.md` (61KB, prior verified audit)

A verification workflow (`verify-reviews-v1`) was launched 2026-09-13 ~18:50 +08
but ALL 18 verifier children died instantly to API 429 (quota reset
2026-09-13T14:58:38Z = 22:58 +08). This runbook resumes that work AFTER quota reset.

## Staged inputs (all under `audits/.staging-verify/`)

- `recovery/<pkg>.md` — 29 round-1 e2e texts (verbatim)
- `round2/<group>.md` — 10 round-2 e2e group texts (38 pkgs, verbatim)
- `vpkg/audit-<pkg>.md` — 67 prior-audit chunks + `FALSE-list.md` + `manifest.json`
- `verify-args.json` — exact workflow args (18 verifier groups + paths)
- `merge_verify.py` — merges e2e + audit + verdicts into the final file

## Step 1 — relaunch verification workflow

Call `muse.workflow` with `name: "verify-reviews-v2"`, inline `script` below,
and `args` = the parsed object from `verify-args.json` (read the file, pass the
object itself). Script (args arrive as a JSON string; header normalizes that):

```js
export default async function workflow(host) {
  let A = null;
  try {
    A = typeof host.args === "string" ? JSON.parse(host.args) : host.args;
  } catch (e) {
    A = null;
  }
  if (!A || typeof A !== "object" || !Array.isArray(A.verifiers) || !A.workspace) {
    return { status: "failed-args", argsType: typeof host.args, argsPreview: String(host.args).slice(0, 500) };
  }
  const WS = A.workspace;
  const FALSE_LIST = A.false_list;
  const GROUPS = A.verifiers;
  const verifyPrompt = (g) => `Verify code-review findings (bugs/correctness, security, performance) for these Laravel packages in workspace ${WS}: ${g.pkgs.join(", ")}.\nEVIDENCE (read with muse.read_file ONLY, NEVER muse.bash): e2e findings: ${g.e2e.join(", ")}; prior-audit chunks: ${g.audit.join(", ")}; known-false list: ${FALSE_LIST}.\nMETHOD: for EACH e2e finding, locate the cited file:line in ${WS}/packages/<pkg>/... (muse.search + muse.read_file) and check it against CURRENT source. Prior-audit items are pre-verified: where an e2e finding duplicates one, note DUP and adopt the audit severity unless current source contradicts it; audit-only items need only a light confirm (cited location exists, claim plausible).\nSTALENESS: code changed since 2026-09-12 (a 2026-09-13 migration batch fixed 11 items). If current source already addresses a finding, verdict FIXED with the current-code evidence.\nFALSE LIST: an e2e finding matching an entry there is FALSE (cite it).\nOUTPUT (your entire response, nothing else): per package a "## <pkg>" header then ONE line per finding: "- [<id>] <VERDICT> <sev> <cat> | <file:line> | <note max 140 chars>". IDs: round-2 keep reviewer IDs (e.g. R2:M3); round-1 number sequentially R1:#1.. in section order including a 3-word title quote in the note; audit queue rows AUD:Q#n; audit body bullets AUD:Bn in order. VERDICT: CONFIRMED | DOWNGRADED (was X) | FALSE | FIXED | UNVERIFIED (state what runtime check is needed) | ADOPTED (audit items; add DUP <other-id> when merged, counted once). Cover EVERY finding in your evidence files. HARD CONSTRAINTS: NEVER muse.bash (stalls on approvals and wedges the run). Read-only: do not write files.`;
  phase("verify");
  let results = await host.parallel(GROUPS.map((g) => ({ label: g.id.slice(0, 60), input: verifyPrompt(g) })));
  const failedIdx = results.map((r, i) => (r === null || r.error_kind ? i : -1)).filter((i) => i >= 0);
  if (failedIdx.length > 0) {
    const retries = await host.parallel(failedIdx.map((i) => ({ label: `retry-${GROUPS[i].id}`.slice(0, 60), input: verifyPrompt(GROUPS[i]) })));
    retries.forEach((r, k) => { if (r !== null && !r.error_kind) { results[failedIdx[k]] = r; } });
  }
  const good = results.map((r, i) => ({ r, g: GROUPS[i] })).filter((x) => x.r !== null && !x.r.error_kind);
  const gaps = results.map((r, i) => (r === null || r.error_kind ? GROUPS[i].pkgs : [])).flat();
  const r1texts = good.filter((x) => x.g.round === 1).map((x) => `===== ${x.g.id} =====\n${String(x.r.text || "")}`);
  const r2texts = good.filter((x) => x.g.round === 2).map((x) => `===== ${x.g.id} =====\n${String(x.r.text || "")}`);
  phase("transcribe");
  let t1 = null;
  if (r1texts.length > 0) {
    t1 = await host.agent({ label: "transcribe-r1-verdicts", input: `Transcribe verifier outputs into ONE verdict file. Verifier outputs (paste, authoritative):\n\n${r1texts.join("\n\n")}\n\nTASK: write ${A.verdict_r1} via muse.write_file (NEVER muse.bash) containing every "## <pkg>" verdict block transcribed VERBATIM, grouped by package, nothing added, nothing judged. Packages expected: ${GROUPS.filter((g) => g.round === 1).map((g) => g.pkgs.join(",")).join(",")}. For any expected package with no verdicts above, emit "## <pkg>" followed by "- NO-VERDICTS (verifier produced nothing)". Your returned text: ONLY counts (packages with verdicts, packages empty).` });
  }
  let t2 = null;
  if (r2texts.length > 0) {
    t2 = await host.agent({ label: "transcribe-r2-verdicts", input: `Transcribe verifier outputs into ONE verdict file. Verifier outputs (paste, authoritative):\n\n${r2texts.join("\n\n")}\n\nTASK: write ${A.verdict_r2} via muse.write_file (NEVER muse.bash) containing every "## <pkg>" verdict block transcribed VERBATIM, grouped by package, nothing added, nothing judged. Packages expected: ${GROUPS.filter((g) => g.round === 2).map((g) => g.pkgs.join(",")).join(",")}. For any expected package with no verdicts above, emit "## <pkg>" followed by "- NO-VERDICTS (verifier produced nothing)". Your returned text: ONLY counts (packages with verdicts, packages empty).` });
  }
  return { status: gaps.length > 0 ? "partial" : "complete", verifiers_ok: good.length, verifiers_total: GROUPS.length, gaps, t1_text: t1 ? t1.text : null, t2_text: t2 ? t2.text : null };
}
```

Expected runtime ~1.5–2.5h (18 verifiers, 6-way concurrency). If the run reports
mass 429 failures again: create a new one-shot cron for +60 min with the same
task prompt, then stop and report (do NOT loop).

## Step 2 — merge into the single file

```bash
python3 audits/.staging-verify/merge_verify.py
```

Output: `audits/verified-review-2026-09-13.md`. The script prints coverage
(packages with/without verdicts) and verdict counts — quote them in the report.

## Step 3 — verify + report

- Confirm the output file exists and every package section is present.
- Spot-check 10+ cited `file:line` refs resolve (same technique as before).
- If any packages have NO-VERDICTS, say so explicitly with the package list.
- Report: file path, verdict counts, coverage, limitations.
- Leave `audits/.staging-verify/` in place and mention it can be deleted.
