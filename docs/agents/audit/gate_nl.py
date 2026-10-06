import csv, sys
from collections import Counter
# Netherlands gate. Pins the B18 worker pass (354 areas: 12
# provinces + 342 municipalities; 4071 PC4 codes / 4092 links):
# tree 342/342 PASS vs BAG + CBS; BAG address census beats
# CBS-2024 vintage (1364 real per BAG, 5369 withdrawn). ONE leg
# DEL (1216 secondary ->wijdemeren, code keeps its primary) +
# TWO leg ADDs (6153->beekdaelen, 6881->rozendaal secondaries;
# code rows pre-existed). EOL: areas LF; codes/legs pure CRLF.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_nl.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/netherlands-address-areas.csv'
C = f'{G}/netherlands-postal-codes.csv'
L = f'{G}/netherlands-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
raw_c = open(C, 'rb').read()
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 4072)
raw_l = open(L, 'rb').read()
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 4093)
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-354', len(rows) == 354, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('provinces-12', sum(1 for r in rows if r['level'] == '1') == 12)
check('municipalities-342', sum(1 for r in rows if r['level'] == '2') == 342)
iso = {'drenthe': 'DR', 'flevoland': 'FL', 'friesland': 'FR',
       'gelderland': 'GE', 'groningen': 'GR', 'limburg': 'LI',
       'noord-brabant': 'NB', 'noord-holland': 'NH', 'overijssel': 'OV',
       'utrecht': 'UT', 'zeeland': 'ZE', 'zuid-holland': 'ZH'}
ok = all(byid.get(f'nl:province:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-12', ok and len(iso) == 12)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-4071', len(codes) == 4071, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-4092', len(legs) == 4092, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
check('fix-1216-keeps-primary', prim.get('1216') == 1)
check('fix-1216-wijdemeren-gone', all(r['area_source_id'] != 'nl:municipality:wijdemeren' for r in bycode['1216']))
check('fix-6153', ('nl:municipality:beekdaelen', 'false') in [(r['area_source_id'], r['is_primary']) for r in bycode['6153']])
check('fix-6881', ('nl:municipality:rozendaal', 'false') in [(r['area_source_id'], r['is_primary']) for r in bycode['6881']])
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
