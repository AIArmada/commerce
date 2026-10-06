import csv, sys
from collections import Counter
# Latvia gate. Pins the B20 pass (627 areas: 42 L1 + 585 L2;
# 697 codes / 731 legs, all LF): tree VERIFIED CLEAN — L1
# 42 = 35 municipalities + 7 state cities (post-2025-07-01
# truth: Varaklani merged into Madona, enwiki+lvwiki agree;
# bundle tree already parents its 3 units under Madona; ISO
# 3166-2:LV still carries stale LV-102); L2 585/585 names
# (71 towns + 3 cities + 511 parishes) vs law-cited table.
# Postal inventory clean (697/697 = GN-live set); legs had a
# systematic builder collapse (6 L1 held ZERO legs:
# city+municipality pairs collapsed onto one side) -> 108
# codes: 96 MOVEs + 12 ADD-LEG duals + 9 primary flips + 1
# leg-swap (5015). Convention (proven by correctly built
# Riga/Daugavpils/Jurmala/Liepaja pairs + 9/9 built duals):
# rural->municipality, town->city, edge splits DUAL with
# municipality primary. 4835-38 -> MADONA (2025 law),
# superseding the doc-18 "Varaklani mapped to Rezekne" note.
# HOLDS: 19 in-range numbers absent both sources (retired);
# Pasts finder unreachable (OSM/OSM-parish + lvwiki ranges
# + structural proof instead); acc=1 rural moves retained.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_lv.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/latvia-address-areas.csv'
C = f'{G}/latvia-postal-codes.csv'
L = f'{G}/latvia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
for tag, p, n in [('areas', A, 628), ('codes', C, 698), ('legs', L, 732)]:
    raw = open(p, 'rb').read()
    check(f'{tag}-LF-only', b'\r' not in raw)
    check(f'{tag}-lines-{n}', raw.count(b'\n') == n, str(raw.count(b'\n')))
    check(f'{tag}-trailing-LF', raw.endswith(b'\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-627', len(rows) == 627, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-42', sum(1 for r in rows if r['level'] == '1') == 42)
check('L2-585', sum(1 for r in rows if r['level'] == '2') == 585)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('varaklani-under-madona', byid.get('lv:town:varaklani', {}).get('parent_source_id') == 'lv:municipality:madona')
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-697', len(codes) == 697, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-731', len(legs) == 731, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
legged = Counter(r['area_source_id'] for r in legs)
check('zero-leg-areas-filled', all(legged.get(a, 0) > 0 for a in
      ['lv:municipality:jelgava', 'lv:municipality:valmiera', 'lv:municipality:ogre',
       'lv:municipality:jekabpils', 'lv:state_city:rezekne', 'lv:state_city:ventspils']))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('move-3011', bycode['LV-3011'] == [('lv:municipality:jelgava', 'true')])
check('dual-3001', sorted(bycode['LV-3001']) == [('lv:municipality:jelgava', 'true'), ('lv:state_city:jelgava', 'false')])
check('move-3602', bycode['LV-3602'] == [('lv:state_city:ventspils', 'true')])
check('dual-3601', sorted(bycode['LV-3601']) == [('lv:municipality:ventspils', 'true'), ('lv:state_city:ventspils', 'false')])
check('dual-4601', sorted(bycode['LV-4601']) == [('lv:municipality:rezekne', 'true'), ('lv:state_city:rezekne', 'false')])
check('move-4206', bycode['LV-4206'] == [('lv:municipality:valmiera', 'true')])
check('move-5011', bycode['LV-5011'] == [('lv:municipality:ogre', 'true')])
check('dual-5001', sorted(bycode['LV-5001']) == [('lv:city:ogre', 'false'), ('lv:municipality:ogre', 'true')])
check('swap-5015', sorted(bycode['LV-5015']) == [('lv:municipality:ogre', 'true'), ('lv:municipality:salaspils', 'false')])
check('move-5204', bycode['LV-5204'] == [('lv:municipality:jekabpils', 'true')])
check('dual-5201', sorted(bycode['LV-5201']) == [('lv:city:jekabpils', 'false'), ('lv:municipality:jekabpils', 'true')])
check('varaklani-4838', bycode['LV-4838'] == [('lv:municipality:madona', 'true')])
check('keep-5003', bycode['LV-5003'] == [('lv:city:ogre', 'true')])
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
