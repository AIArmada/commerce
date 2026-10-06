import csv, sys
from collections import Counter
# Thailand gate. Pins the B20 pass (1006 areas: 78 L1 + 928 L2;
# 789 codes / 930 legs; areas LF, postal pure CRLF): tree HOLD
# (zero changes) — 78/78 L1 vs ISO 3166-2:TH (Bangkok local
# variant + Pattaya/Phatthaya policy notes), 928/928 L2 names
# + per-province counts vs Statoids, 50/50 khet vs en.wiki;
# same-L1 Bang Sai pair is a real TIS homonym (1404/1413).
# Postal NEEDS-FIX applied (Thailand Post finder DNS-dead, so
# GN+Statoids+Wiki trio): FIX-1 67000 -> Mueang Phetchabun
# (GN admin1 typo 76 + builder match), FIX-2 43170 -> So
# Phisai (fuzzy Phon-Phisai match; stale Nong Khai admin1),
# FIX-3 Surin cluster +13 codes/+17 legs (GN had ZERO Surin
# rows), FIX-4 Bangkok +1 code/+6 legs (6 codeless khet),
# FIX-5 +5 upcountry legs, FIX-6 42190 Nong Hin, FIX-7 three
# wrong-code moves (23170 Ko Chang, 42220 Erawan, 41280 Wang
# Sam Mo; Na Yung flipped primary on 41380). Report's "21
# legs/934" is a typo: FIX-3 enumerates 17 legs (4 duals + 9
# singles = 17 districts), 930 is correct. HOLDS: primacy
# judgment calls (BKK child-primaries, old-district ties),
# rejected single-signal/outvoted items (H10), 11
# Statoids-side errors, transliteration policy (Mueang etc).
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_th.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/thailand-address-areas.csv'
C = f'{G}/thailand-postal-codes.csv'
L = f'{G}/thailand-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-lines-1007', raw_a.count(b'\n') == 1007, str(raw_a.count(b'\n')))
check('areas-trailing-LF', raw_a.endswith(b'\n'))
raw_c = open(C, 'rb').read()
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 790, str(raw_c.count(b'\r\n')))
raw_l = open(L, 'rb').read()
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 931, str(raw_l.count(b'\r\n')))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-1006', len(rows) == 1006, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-78', sum(1 for r in rows if r['level'] == '1') == 78)
check('L2-928', sum(1 for r in rows if r['level'] == '2') == 928)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-789', len(codes) == 789, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-930', len(legs) == 930, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
legged = {r['area_source_id'] for r in legs}
check('zero-codeless-L2', all(r['source_id'] in legged for r in rows if r['level'] == '2'))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('fix1-67000', bycode['67000'] == [('th:amphoe:mueang-phetchabun', 'true')])
check('fix2-43170', bycode['43170'] == [('th:amphoe:so-phisai', 'true')])
check('fix3-32000', sorted(bycode['32000']) == [('th:amphoe:khwao-sinarin', 'false'), ('th:amphoe:mueang-surin', 'true')])
check('fix3-32230', bycode['32230'] == [('th:amphoe:buachet', 'true')])
check('fix4-10900', bycode['10900'] == [('th:khet:chatuchak', 'true')])
check('fix4-10110', sorted(bycode['10110']) == [('th:khet:khlong-toei', 'false'), ('th:khet:watthana', 'true')])
check('fix5-50270', sorted(bycode['50270']) == [('th:amphoe:galyani-vadhana', 'false'), ('th:amphoe:mae-chaem', 'true')])
check('fix6-42190', bycode['42190'] == [('th:amphoe:nong-hin', 'true')])
check('fix7-23170', bycode['23170'] == [('th:amphoe:ko-chang', 'true')])
check('fix7-23120-single', bycode['23120'] == [('th:amphoe:laem-ngop', 'true')])
check('fix7-42220', bycode['42220'] == [('th:amphoe:erawan', 'true')])
check('fix7-41280', bycode['41280'] == [('th:amphoe:wang-sam-mo', 'true')])
check('fix7-41380-flip', bycode['41380'] == [('th:amphoe:na-yung', 'true')])
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
