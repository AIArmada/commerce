import csv, sys
from collections import Counter
# Greece gate. Pins the B18 worker pass (346 areas: 14 regions +
# 332 municipalities; 974 postcodes / 984 links): tree 346/346
# PASS vs Kallikratis roster + ELTA delivery data; all 974 codes
# ELTA-exact (72k-record ELTA file). THREE leg fixes: 14121/14122
# metamorfosi->irakleio, 49083 north-corfu->
# central-corfu-and-diapontia-islands (ELTA + finder, 2 signals).
# EOL: areas pure CRLF; codes pure CRLF; legs LF body with a
# single CRLF header line (pre-existing; preserved byte-exact).
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_gr.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/greece-address-areas.csv'
C = f'{G}/greece-postal-codes.csv'
L = f'{G}/greece-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-pure-CRLF', raw_a.count(b'\r\n') == raw_a.count(b'\n') == 347)
check('areas-trailing-CRLF', raw_a.endswith(b'\r\n'))
raw_c = open(C, 'rb').read()
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 975)
raw_l = open(L, 'rb').read()
check('legs-one-CRLF-header', raw_l.count(b'\r\n') == 1 and raw_l.split(b'\n')[0].endswith(b'\r'))
check('legs-trailing-LF', raw_l.endswith(b'\n') and not raw_l.endswith(b'\r\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-346', len(rows) == 346, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('regions-14', sum(1 for r in rows if r['level'] == '1') == 14)
check('municipalities-332', sum(1 for r in rows if r['level'] == '2') == 332)
iso = {'attica': 'I', 'central-greece': 'H', 'central-macedonia': 'B',
       'crete': 'M', 'east-macedonia-and-thrace': 'A', 'epirus': 'D',
       'ionian-islands': 'F', 'mount-athos': '69', 'north-aegean': 'K',
       'peloponnese': 'J', 'south-aegean': 'L', 'thessaly': 'E',
       'west-greece': 'G', 'west-macedonia': 'C'}
ok = all(byid.get(f'gr:administrative_region:{s}', {}).get('code') == c for s, c in iso.items())
check('region-codes-14', ok and len(iso) == 14)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-974', len(codes) == 974, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-984', len(legs) == 984, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
check('fix-14121', [(r['area_source_id'], r['is_primary']) for r in bycode['14121']] == [('gr:municipality:irakleio', 'true')])
check('fix-14122', [(r['area_source_id'], r['is_primary']) for r in bycode['14122']] == [('gr:municipality:irakleio', 'true')])
check('fix-49083', [(r['area_source_id'], r['is_primary']) for r in bycode['49083']] == [('gr:municipality:central-corfu-and-diapontia-islands', 'true')])
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
