import csv, sys
from collections import Counter
# Samoa gate. Pins the B18 worker pass (353 areas: 11 districts +
# 342 villages; 223 postcodes / 240 links): itumalo membership
# governs parentage (SamoaPost village table + itumalo roster, 2
# signals) over SBS constituency geography for 5 exclave
# villages. FIVE re-parents: satuimalufilufi->aana,
# faleapuna->vaa-o-fonoti, salamumu-tai/uta->gagaemauga,
# leauvaa->gagaemauga. TWO leg moves: WS1434->atua district,
# WS2491->vaisigano district. 17 extra legs are legitimate
# multi-village postcodes (SamoaPost table). EOL: all LF.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_ws.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/samoa-address-areas.csv'
C = f'{G}/samoa-postal-codes.csv'
L = f'{G}/samoa-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
for tag, p, n in [('areas', A, 354), ('codes', C, 224), ('legs', L, 241)]:
    raw = open(p, 'rb').read()
    check(f'{tag}-LF-only', b'\r' not in raw)
    check(f'{tag}-lines-{n}', raw.count(b'\n') == n, str(raw.count(b'\n')))
    check(f'{tag}-trailing-LF', raw.endswith(b'\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-353', len(rows) == 353, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('districts-11', sum(1 for r in rows if r['level'] == '1') == 11)
check('villages-342', sum(1 for r in rows if r['level'] == '2') == 342)
iso = {'aana': 'AA', 'aiga-i-le-tai': 'AL', 'atua': 'AT',
       'faasaleleaga': 'FA', 'gagaemauga': 'GE', 'gagaifomauga': 'GI',
       'palauli': 'PA', 'satupaitea': 'SA', 'tuamasaga': 'TU',
       'vaa-o-fonoti': 'VF', 'vaisigano': 'VS'}
ok = all(byid.get(f'ws:district:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-11', ok and len(iso) == 11)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
moves = {'ws:village:satuimalufilufi': 'ws:district:aana',
         'ws:village:faleapuna': 'ws:district:vaa-o-fonoti',
         'ws:village:salamumu-tai': 'ws:district:gagaemauga',
         'ws:village:salamumu-uta': 'ws:district:gagaemauga',
         'ws:village:leauvaa': 'ws:district:gagaemauga'}
for sid, par in moves.items():
    check(f'fix-{sid.split(":")[-1]}', byid.get(sid, {}).get('parent_source_id') == par, byid.get(sid, {}).get('parent_source_id', 'MISSING'))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-223', len(codes) == 223, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-240', len(legs) == 240, str(len(legs)))
check('multi-village-17', len(legs) - len(codes) == 17)
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
check('fix-WS1434', [(r['area_source_id'], r['is_primary']) for r in bycode['WS1434']] == [('ws:district:atua', 'true')])
check('fix-WS2491', [(r['area_source_id'], r['is_primary']) for r in bycode['WS2491']] == [('ws:district:vaisigano', 'true')])
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
