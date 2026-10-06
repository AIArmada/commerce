import csv, sys
# Ghana gate. Pins the B17 inline pass as VERIFY-ONLY (zero
# changes; 277 areas, tree-only — Ghana has no postcode system,
# GhanaPost GPS addresses are not postcodes): 16 regions ISO
# 3166-2:GH exact (GH-BA Brong-Ahafo is a FORMER code, deleted
# 2019-11-22) + 261 MMDAs exact vs the en.wiki Districts table
# (names + parents + 6 metropolitan / 113 municipal / 142
# district categories, 261/261). Citypopulation agrees modulo
# mechanical conventions (appended status words, hyphen/space/
# slash variants); its 6 category lags adjudicated for the
# bundle: Kwabre East / West Gonja / Nandom canonical-municipal
# (redirects), Jasikan + Krachi West elevated 11 May 2021
# (L.I. 2437/2418; MoFEP budgets + news), Obuasi East municipal
# (pre-batch; NDPC 2026-2029 MTDP + oeda.gov.gh). The Nov-2024
# 15-MDA executive-approval batch (3 metro + 12 municipal)
# NEVER completed into law: 8/8 probed members confirmed at old
# status by 2025/2026 official docs (MoFEP budget covers:
# South Tongu/Bole/Anloga/Ningo-Prampram/Techiman North
# DISTRICT, Techiman MUNICIPAL, Gomoa East DISTRICT,
# Kpone-Katamanso MUNICIPAL via MoFEP 2025 + KKMA 2025 docs;
# stda.gov.gh July-2026 news still District) — remaining 6
# same-batch members held by pattern. GN ADM2 is stale for GH
# (pre-split names, typos) and was not used as a voter.
# EOL: LF-only. No postal files exist for GH by design.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_gh.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/ghana-address-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
import os
check('no-postal-codes', not os.path.exists(
    f'{ROOT}/packages/addressing/resources/geography/ghana-postal-codes.csv'))
check('no-postal-links', not os.path.exists(
    f'{ROOT}/packages/addressing/resources/geography/ghana-postal-code-areas.csv'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-277', len(rows) == 277, str(len(rows)))
check('region-16', sum(1 for r in rows if r['type'] == 'region') == 16)
check('metro-6', sum(1 for r in rows if r['type'] == 'metropolitan_city') == 6)
check('municipal-113', sum(1 for r in rows if r['type'] == 'municipality') == 113)
check('district-142', sum(1 for r in rows if r['type'] == 'district') == 142)
iso = {'greater-accra': 'AA', 'ahafo': 'AF', 'ashanti': 'AH', 'bono-east': 'BE',
       'bono': 'BO', 'central': 'CP', 'eastern': 'EP', 'north-east': 'NE',
       'northern': 'NP', 'oti': 'OT', 'savannah': 'SV', 'volta': 'TV',
       'upper-east': 'UE', 'upper-west': 'UW', 'western-north': 'WN',
       'western': 'WP'}
ok = all(byid.get(f'gh:region:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-16', ok and len(iso) == 16)
spots = {'gh:municipality:obuasi-east': ('Obuasi East', 'gh:region:ashanti'),
         'gh:municipality:kwabre-east': ('Kwabre East', 'gh:region:ashanti'),
         'gh:municipality:jasikan': ('Jasikan', 'gh:region:oti'),
         'gh:municipality:krachi-west': ('Krachi West', 'gh:region:oti'),
         'gh:municipality:west-gonja': ('West Gonja', 'gh:region:savannah'),
         'gh:municipality:nandom-municipal': ('Nandom Municipal', 'gh:region:upper-west'),
         'gh:district:guan': ('Guan', 'gh:region:oti'),
         'gh:district:south-tongu': ('South Tongu', 'gh:region:volta'),
         'gh:district:bole': ('Bole', 'gh:region:savannah'),
         'gh:municipality:techiman': ('Techiman', 'gh:region:bono-east'),
         'gh:municipality:kpone-katamanso': ('Kpone-Katamanso', 'gh:region:greater-accra'),
         'gh:district:gomoa-east': ('Gomoa East', 'gh:region:central')}
for sid, (nm, par) in spots.items():
    r = byid.get(sid, {})
    check(f'spot-{sid}', r.get('name') == nm and r.get('parent_source_id') == par,
          str((r.get('name'), r.get('parent_source_id'))))
for sid in ['gh:municipality:south-tongu', 'gh:municipality:bole',
            'gh:metropolitan_city:techiman', 'gh:metropolitan_city:kpone-katamanso',
            'gh:metropolitan_city:gomoa-east', 'gh:district:obuasi-east']:
    check(f'absent-{sid}', sid not in byid)
check('orphans-0', all(r['parent_source_id'] in byid or r['level'] == '1' for r in rows))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
