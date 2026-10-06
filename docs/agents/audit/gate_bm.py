import csv, sys
# Bermuda gate. B4 fix-and-fill: links rewritten from the BPO 2013 Blue
# Pages street vote (2063 rows) + municipality display-name fix.
# Oracles: BPO Blue Pages 2013 via Wayback (official street directory),
# GeoNames BM dump (set 80/80 + dual corroboration), UPU bmu profile via
# Wayback (AA NN street / AA AA box formats), Statoids (tree, no ISO codes
# exist for BM). Holds: parish code HA (GEC parish trigram is HAM; HA is
# baked into provider state mappings, internal code, single-signal -> keep),
# GE CX + HM GX class box codes excluded (street set only). Run from repo root:
# python3 docs/agents/audit/gate_bm.py
A = './packages/addressing/resources/geography/bermuda-address-areas.csv'
C = './packages/addressing/resources/geography/bermuda-postal-codes.csv'
L = './packages/addressing/resources/geography/bermuda-postal-code-areas.csv'
P = 'bm:parish:'
H, DV, SM, PM, PG, WK, SN, SA, SG = (P + s for s in
    ('hamilton', 'devonshire', 'smiths', 'pembroke', 'paget', 'warwick',
     'southampton', 'sandys', 'saint-georges'))
CITY, TOWN = 'bm:municipality:hamilton', 'bm:municipality:saint-georges'
EXP = {
    'CR 01': (H, []), 'CR 02': (H, []), 'CR 03': (H, []), 'CR 04': (H, [SG]),
    'DD 01': (SG, []), 'DD 02': (SG, []), 'DD 03': (SG, []),
    'DV 01': (DV, []), 'DV 02': (DV, []), 'DV 03': (DV, [PG]),
    'DV 04': (PG, [DV]), 'DV 05': (DV, []), 'DV 06': (DV, []),
    'DV 07': (DV, []), 'DV 08': (DV, []),
    'FL 01': (DV, [SM]), 'FL 02': (SM, [DV]), 'FL 03': (DV, [SM]),
    'FL 04': (H, [SM]), 'FL 05': (SM, [DV]), 'FL 06': (SM, []),
    'FL 07': (SM, []), 'FL 08': (SM, []),
    'GE 01': (SG, []), 'GE 02': (SG, [TOWN]), 'GE 03': (TOWN, [SG]),
    'GE 04': (SG, [TOWN]), 'GE 05': (TOWN, [SG]),
    'HM 01': (PM, []), 'HM 02': (PM, []), 'HM 03': (PM, []),
    'HM 04': (PM, []), 'HM 05': (PM, []), 'HM 06': (PM, []),
    'HM 07': (PM, []), 'HM 08': (PM, [CITY]), 'HM 09': (PM, [CITY]),
    'HM 10': (CITY, [PM]), 'HM 11': (CITY, [PM]), 'HM 12': (CITY, [PM]),
    'HM 13': (PM, []), 'HM 14': (PM, [DV]), 'HM 15': (PM, [DV]),
    'HM 16': (PM, [DV]), 'HM 17': (PM, [CITY]), 'HM 18': (PM, [DV]),
    'HM 19': (PM, [CITY, DV]), 'HM 20': (PM, [DV]),
    'HS 01': (SM, []), 'HS 02': (SG, [H, SM]),
    'MA 01': (SA, []), 'MA 02': (SA, []), 'MA 03': (SA, []),
    'MA 04': (SA, []), 'MA 05': (SA, []), 'MA 06': (SA, []),
    'PG 01': (PG, [WK]), 'PG 02': (PG, []), 'PG 03': (PG, []),
    'PG 04': (PG, [WK]), 'PG 05': (PG, []), 'PG 06': (PG, []),
    'SB 01': (SA, []), 'SB 02': (SA, [SN]), 'SB 03': (SA, [SN]),
    'SB 04': (SN, []),
    'SN 01': (SN, []), 'SN 02': (SN, []), 'SN 03': (SN, []), 'SN 04': (SN, []),
    'WK 01': (SN, [WK]), 'WK 02': (WK, []), 'WK 03': (WK, [SN]),
    'WK 04': (WK, []), 'WK 05': (WK, []), 'WK 06': (WK, []),
    'WK 07': (WK, []), 'WK 08': (WK, []), 'WK 09': (WK, []), 'WK 10': (WK, []),
}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in areas}
check('areas-11', len(areas) == 11, str(len(areas)))
check('parishes-9', sum(1 for r in areas if r['type'] == 'parish') == 9)
check('municipalities-2', sum(1 for r in areas if r['type'] == 'municipality') == 2)
check('town-name', byid[TOWN]['name'] == 'Town of St. George', byid[TOWN]['name'])
check('city-parent', byid[CITY]['parent_source_id'] == 'bm:parish:pembroke')
check('town-parent', byid[TOWN]['parent_source_id'] == 'bm:parish:saint-georges')
check('ha-hold', byid['bm:parish:hamilton']['code'] == 'HA',
      'GEC parish trigram HAM held out; HA provider-baked')
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-80', len(codes) == 80 and set(codes) == set(EXP), str(len(codes)))
check('box-excluded', 'GE CX' not in codes and not any('HM GX' in c for c in codes))
links = list(csv.DictReader(open(L, newline='')))
check('links-113', len(links) == 113, str(len(links)))
got = {}
for r in links:
    got.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
bad = 0
for code, (prim, secs) in EXP.items():
    rows = got.get(code, [])
    want = [(prim, 'true')] + [(s, 'false') for s in secs]
    if rows != want:
        bad += 1
        if bad <= 8:
            print('FAIL link-' + code.replace(' ', ''), 'got', rows, 'want', want)
if bad:
    fails.append('per-code-mapping')
else:
    print('PASS per-code-mapping 80/80')
check('extra-codes', set(got) == set(EXP), str(set(got) ^ set(EXP)))
check('primaries-80', sum(1 for r in links if r['is_primary'] == 'true') == 80)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
