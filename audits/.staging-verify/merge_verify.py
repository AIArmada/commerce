#!/usr/bin/env python3
"""Merge e2e findings + prior-audit chunks + verifier verdicts into ONE verified file.

Inputs (all under STAGE dir): recovery/<pkg>.md (29), round2/<group>.md (10),
vpkg/audit-<pkg>.md (67), vpkg/verdicts-r1.md, vpkg/verdicts-r2.md.
Output: audits/verified-review-2026-09-13.md
"""
import json, os, re, sys

STAGE = '/Users/Saiffil/Herd/commerce/audits/.staging-verify'
WS = '/Users/Saiffil/Herd/commerce'
AUD = f'{WS}/audits'
OUT = f'{AUD}/verified-review-2026-09-13.md'

R1 = ['addressing','affiliate-network','affiliates','authz','cart','cashier','cashier-chip',
 'checkout','chip','commerce-support','communications','contacting','csuite','customers','docs',
 'engagement','events','feedback','filament-addressing','filament-affiliate-network','filament-affiliates',
 'filament-authz','filament-cart','filament-cashier-chip','filament-commerce-support','filament-communications',
 'filament-contacting','filament-customers','filament-docs']
R2G = {
 'review-filament-cashier-etc': ['filament-cashier','ticketing','pricing','filament-chip'],
 'review-filament-feedback-etc': ['filament-feedback','signals','filament-shipping','tax'],
 'review-filament-jnt-etc': ['filament-jnt','persons','jnt','filament-orders'],
 'review-filament-organizations-etc': ['filament-organizations','products','filament-pricing','references'],
 'review-filament-products-etc': ['filament-products','organizations','filament-seating','filament-persons'],
 'review-filament-promotions-etc': ['filament-promotions','inventory','vouchers','filament-engagement'],
 'review-filament-signals-etc': ['filament-signals','promotions','filament-vouchers','filament-ticketing'],
 'review-filament-tax-etc': ['filament-tax','shipping','growth','filament-growth'],
 'review-membership-etc': ['membership','moderation'],
 'review-seating-etc': ['seating','orders','filament-events','filament-inventory'],
}
R2 = [p for g in R2G.values() for p in g]
assert len(R1) == 29 and len(R2) == 38 and len(set(R1 + R2)) == 67

def parse_verdicts(path):
    """Return {pkg: [verdict lines]} parsed from ## <pkg> blocks."""
    out, cur = {}, None
    if not os.path.exists(path):
        return out
    for line in open(path):
        m = re.match(r'## ([a-z0-9-]+)\s*$', line.strip())
        if m:
            cur = m.group(1)
            out.setdefault(cur, [])
        elif cur and line.strip().startswith('- '):
            out[cur].append(line.rstrip())
    return out

v1 = parse_verdicts(f'{STAGE}/vpkg/verdicts-r1.md')
v2 = parse_verdicts(f'{STAGE}/vpkg/verdicts-r2.md')
verdicts = {**v1, **v2}

# counts: - [ID] VERDICT sev cat | ...  (sev/cat may be absent for FALSE/NO-VERDICTS)
counts = {}
n_dups = 0
for pkg, vlines in verdicts.items():
    for ln in vlines:
        m = re.match(r'-\s*\[[^\]]+\]\s*([A-Z-]+)(?:\s+\((was [^)]+)\))?(?:\s+(critical|high|medium|med|low))?', ln, re.I)
        if not m:
            counts.setdefault(('UNPARSED', ''), []).append((pkg, ln[:80]))
            continue
        vd = m.group(1).upper()
        sev = (m.group(3) or '').lower()
        if sev == 'med':
            sev = 'medium'
        counts.setdefault((vd, sev), []).append((pkg, ln[:80]))
        if 'DUP' in ln.upper():
            n_dups += 1

defsev = lambda vd: {s: len(counts.get((vd, s), [])) for s in ('critical', 'high', 'medium', 'low', '')}
parts = []
total_actionable = 0
for vd in ('CONFIRMED', 'ADOPTED', 'DOWNGRADED'):
    total_actionable += sum(defsev(vd).values())
n_false = sum(defsev('FALSE').values())
n_fixed = sum(defsev('FIXED').values())
n_unver = sum(defsev('UNVERIFIED').values()) + sum(defsev('NO-VERDICTS').values())

def verdict_block(pkg):
    vlines = verdicts.get(pkg)
    if not vlines:
        return '### Verification verdicts\n\n- NO-VERDICTS (package was not verified; findings below are as-reported).\n'
    return '### Verification verdicts\n\n' + '\n'.join(vlines) + '\n'

doc = [f"""---
title: Verified End-to-End Review — packages/* (bugs, security, performance)
date: 2026-09-13
scope: packages/* (67 packages)
method: e2e reviews + prior verified audit, per-finding verification pass
---

# Verified End-to-End Review — packages/* (bugs, security, performance)

Date: 2026-09-13. Scope: all 67 packages under `packages/*`.
Sources merged per package: (1) e2e review findings (round 1 recovered verbatim
from the interrupted 2026-09-12 session + round 2 fresh reviews 2026-09-13),
(2) prior verified audit `all-packages-audit-2026-09-12.md` chunks,
(3) per-finding verification verdicts checked against CURRENT source
(`CONFIRMED` / `DOWNGRADED` / `FALSE` / `FIXED` / `UNVERIFIED` / `ADOPTED`,
with `DUP` where both sources reported the same issue — counted once).

## Verdict counts

| Verdict | critical | high | medium | low | n/a |
|---------|----------|------|--------|-----|-----|
"""]
for vd in ('CONFIRMED', 'ADOPTED', 'DOWNGRADED', 'FALSE', 'FIXED', 'UNVERIFIED'):
    c = defsev(vd)
    doc.append(f"| {vd} | {c['critical']} | {c['high']} | {c['medium']} | {c['low']} | {c['']} |")
doc.append(f"""
Actionable (CONFIRMED + ADOPTED + DOWNGRADED): **{total_actionable}**.
Rejected as false: **{n_false}**. Already fixed in current source: **{n_fixed}**.
Unverified: **{n_unver}**. Duplicate e2e/audit pairs merged: **{n_dups}**.

## Limitations

- Verdicts reflect CURRENT source at verification time; code fixed after that
  (e.g. the 2026-09-13 migration batch) is marked FIXED where observed.
- DUP pairs were judged by description + file:line overlap; near-duplicates with
  different scopes were kept separate.
- UNVERIFIED items need runtime/prod-data confirmation (reason stated per item).

# Part 1 — Round-1 packages (29)

""")
for pkg in R1:
    e2e = open(f'{STAGE}/recovery/{pkg}.md').read().strip()
    aud = open(f'{STAGE}/vpkg/audit-{pkg}.md').read().strip()
    doc.append(f'---\n\n## {pkg}\n\n### E2E findings (verbatim)\n\n{e2e}\n\n### Prior-audit findings (verbatim chunk)\n\n{aud}\n\n{verdict_block(pkg)}')
doc.append('\n# Part 2 — Round-2 packages (38, grouped by review batch)\n')
for label, pkgs in sorted(R2G.items()):
    grp = open(f'{STAGE}/round2/{label}.md').read().strip()
    doc.append(f"---\n\n## Round 2 — {', '.join('`' + p + '`' for p in pkgs)}\n\n### E2E findings (verbatim)\n\n{grp}\n")
    for pkg in pkgs:
        aud = open(f'{STAGE}/vpkg/audit-{pkg}.md').read().strip()
        doc.append(f'### Prior-audit chunk: {pkg}\n\n{aud}\n\n#### Verdicts: {pkg}\n\n' +
                   '\n'.join(verdicts.get(pkg, ['- NO-VERDICTS (package was not verified; findings above are as-reported).'])) + '\n')
if 'cross-cutting' in verdicts:
    doc.append('\n# Appendix — Cross-cutting verdicts (span multiple packages)\n\n' +
               '\n'.join(verdicts['cross-cutting']) + '\n')
open(OUT, 'w').write('\n'.join(doc) + '\n')

# assertions
missing_e2e = [p for p in R1 + R2 if p not in set(R1 + R2)]
no_verd = [p for p in R1 + R2 if p not in verdicts]
print(f'wrote {OUT} ({os.path.getsize(OUT)} bytes)')
print(f'packages with verdicts: {len(verdicts)}/67; without: {no_verd}')
print(f'actionable={total_actionable} false={n_false} fixed={n_fixed} unverified={n_unver} dups={n_dups}')
