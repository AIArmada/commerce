import csv, sys
# Guam gate. B6 revisit: verify-only, zero data changes.
# Tree 19/19 vs Statoids village set + Villages of Guam (bundled
# uses the current Chamorro forms with old forms parenthesized).
# Overlay 21/21 exact vs the GeoNames GU dump (USPS city mapping);
# UPU gum range 96910-96931 is stale (misses 96932, corroborated
# by GN + mirrors). Holds: 96920/96924 unassigned (absent from GN
# + all mirrors); Chalan Pago-Ordot codeless (no post office;
# 96910 spill overlap is ZCTA-style, not USPS city assignment, so
# no secondary — would cascade across all 21 codes). Run from repo
# root: python3 docs/agents/audit/gate_gu.py
A = './packages/addressing/resources/geography/guam-address-areas.csv'
C = './packages/addressing/resources/geography/guam-postal-codes.csv'
L = './packages/addressing/resources/geography/guam-postal-code-areas.csv'
LINKS = {'96910': 'gu:village:hagatna', '96911': 'gu:village:tamuning',
         '96912': 'gu:village:dededo', '96913': 'gu:village:barrigada',
         '96914': 'gu:village:yona',
         '96915': 'gu:village:santa-rita-santa-rita-sumai',
         '96916': 'gu:village:merizo-malesso',
         '96917': 'gu:village:inarajan-inalahan',
         '96918': 'gu:village:umatac-humatak',
         '96919': 'gu:village:agana-heights',
         '96921': 'gu:village:barrigada',
         '96922': 'gu:village:asan-maina',
         '96923': 'gu:village:mangilao', '96925': 'gu:village:piti',
         '96926': 'gu:village:sinajana',
         '96927': 'gu:village:mongmong-toto-maite',
         '96928': 'gu:village:hagat', '96929': 'gu:village:yigo',
         '96930': 'gu:village:talofofo-talofofo',
         '96931': 'gu:village:tamuning',
         '96932': 'gu:village:hagatna'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-19', len(areas) == 19, str(len(areas)))
check('villages-19', all(r['type'] == 'village' and r['level'] == '1' for r in areas))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-21', set(codes) == set(LINKS), str(set(codes) ^ set(LINKS)))
check('gaps-held', '96920' not in codes and '96924' not in codes)
links = list(csv.DictReader(open(L, newline='')))
check('links-21', len(links) == 21, str(len(links)))
ok = all(r['postcode'] in LINKS and r['area_source_id'] == LINKS[r['postcode']]
         and r['is_primary'] == 'true' for r in links)
check('mapping-21', ok)
check('cpo-codeless', not any('chalan-pago-ordot' in r['area_source_id'] for r in links))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
