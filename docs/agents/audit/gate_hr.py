import csv, sys
from collections import Counter
# Croatia gate. Pins the B20 verify-only pass (577 areas: 21 L1
# + 556 L2 = 128 towns + 428 municipalities; 1094 codes / 1094
# legs, all L1 single-primary; areas CRLF, postal LF): tree
# 556/556 exact (name+type+parent, diacritics) vs TWO
# independent compilations — WP towns+municipalities lists
# (NN/Ministry-sourced) + citypopulation.de (DZS census);
# L1 codes 01-21 = ISO 3166-2:HR HR-01..HR-21, corroborated
# by NN Territories Act cl.2-3 + HP's 21 zupanija values.
# Postal: 1094/1094 HP-covered (900 settlement + 194
# office/box-only per UPU xx1/xx2 rule); 1089/1094 legs
# unanimous across HP-settlement + HP-office + GN; 5 keeps:
# 10290/10456 Zagreb-county (GN/office quirks outvoted),
# 10253/10373/10361 city-of-zagreb (UPU office-owns-code).
# HOLDS: 10004 customs office + 31200 stale GN absent (not
# filled); 3 L1 display names vs ISO-en (Zagreb,
# Vukovar-Syrmia, City of Zagreb) held as convention.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_hr.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/croatia-address-areas.csv'
C = f'{G}/croatia-postal-codes.csv'
L = f'{G}/croatia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-pure-CRLF', raw_a.count(b'\r\n') == raw_a.count(b'\n') == 578, str(raw_a.count(b'\r\n')))
for tag, p, n in [('codes', C, 1095), ('legs', L, 1095)]:
    raw = open(p, 'rb').read()
    check(f'{tag}-LF-only', b'\r' not in raw)
    check(f'{tag}-lines-{n}', raw.count(b'\n') == n, str(raw.count(b'\n')))
    check(f'{tag}-trailing-LF', raw.endswith(b'\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-577', len(rows) == 577, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-21', sum(1 for r in rows if r['level'] == '1') == 21)
check('L2-556', sum(1 for r in rows if r['level'] == '2') == 556)
check('L2-types', sum(1 for r in rows if r['type'] == 'town') == 128
      and sum(1 for r in rows if r['type'] == 'municipality') == 428)
l1codes = sorted(r['code'] for r in rows if r['level'] == '1')
check('iso-01-21', l1codes == [f'{i:02d}' for i in range(1, 22)])
dist = sorted(Counter(r['parent_source_id'] for r in rows if r['level'] == '2').values(), reverse=True)
check('L2-distribution', dist == [55, 42, 41, 36, 34, 34, 32, 31, 28, 28, 25, 25, 23, 22, 22, 20, 19, 16, 12, 10, 1], str(dist))
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('zagreb-town-under-city', byid.get('hr:town:zagreb', {}).get('parent_source_id') == 'hr:county:city-of-zagreb')
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-1094', len(codes) == 1094, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-1094', len(legs) == 1094, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
check('legs-all-L1', all(byid[r['area_source_id']]['level'] == '1' for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('anchor-10000', bycode['10000'] == [('hr:county:city-of-zagreb', 'true')])
check('anchor-21000', bycode['21000'] == [('hr:county:split-dalmatia', 'true')])
check('anchor-31000', bycode['31000'] == [('hr:county:osijek-baranja', 'true')])
check('anchor-51000', bycode['51000'] == [('hr:county:primorje-gorski-kotar', 'true')])
check('anchor-20000', bycode['20000'] == [('hr:county:dubrovnik-neretva', 'true')])
check('keep-10290', bycode['10290'] == [('hr:county:zagreb', 'true')])
check('keep-10456', bycode['10456'] == [('hr:county:zagreb', 'true')])
check('keep-10253', bycode['10253'] == [('hr:county:city-of-zagreb', 'true')])
check('keep-10373', bycode['10373'] == [('hr:county:city-of-zagreb', 'true')])
check('keep-10361', bycode['10361'] == [('hr:county:city-of-zagreb', 'true')])
check('split-53295', bycode['53295'] == [('hr:county:lika-senj', 'true')])
check('box-10101', bycode['10101'] == [('hr:county:city-of-zagreb', 'true')])
check('hold-10004-absent', '10004' not in set(codes))
check('hold-31200-absent', '31200' not in set(codes))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
