import csv, sys
from collections import Counter
# Puerto Rico gate. Pins the B20 verify-only pass (979 areas:
# 78 L1 municipios + 901 L2 = 827 barrios + 74 barrio-pueblos;
# 177 ZIPs / 177 legs, all L1 single-primary, all LF): tree
# == U.S. Census 2024 gazetteer EXACTLY (78/78 counties FIPS
# +name accent-exact; 901/901 cousubs key+name+type+counts;
# FUNCSTAT=F balance rows correctly excluded); L1 seconded
# by wiki/pr.gov/Ley-70 (78 municipios); wiki "902 barrios"
# is a stale 2011 cite, rejected. ISO 3166-2:PR defines no
# subdivisions (stub real). Postal set == GN 177/177; legs
# 177/177 match GN municipio. HOLD H1: 00938 kept (GN-current
# + usage + federal docs vs 3 aggregator omissions — needs a
# human USPS-finder lookup to clear). HOLD H2: USPS/HUD
# oracles unreachable programmatically (403/404), so oracle
# role fell to GN-current + directory consensus + federal docs.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_pr.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/puerto-rico-address-areas.csv'
C = f'{G}/puerto-rico-postal-codes.csv'
L = f'{G}/puerto-rico-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
for tag, p, n in [('areas', A, 980), ('codes', C, 178), ('legs', L, 178)]:
    raw = open(p, 'rb').read()
    check(f'{tag}-LF-only', b'\r' not in raw)
    check(f'{tag}-lines-{n}', raw.count(b'\n') == n, str(raw.count(b'\n')))
    check(f'{tag}-trailing-LF', raw.endswith(b'\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-979', len(rows) == 979, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-78', sum(1 for r in rows if r['level'] == '1') == 78)
check('L2-901', sum(1 for r in rows if r['level'] == '2') == 901)
check('L2-types', sum(1 for r in rows if r['type'] == 'barrio') == 827
      and sum(1 for r in rows if r['type'] == 'barrio_pueblo') == 74)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
kids = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
check('ponce-31', kids.get('pr:municipality:ponce') == 31, str(kids.get('pr:municipality:ponce')))
check('san-juan-18', kids.get('pr:municipality:san-juan') == 18, str(kids.get('pr:municipality:san-juan')))
check('florida-1', kids.get('pr:municipality:florida') == 1, str(kids.get('pr:municipality:florida')))
check('catano-2', kids.get('pr:municipality:catano') == 2, str(kids.get('pr:municipality:catano')))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-177', len(codes) == 177, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-177', len(legs) == 177, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
check('legs-all-L1', all(byid[r['area_source_id']]['level'] == '1' for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('leg-00968-san-juan', bycode['00968'] == [('pr:municipality:san-juan', 'true')])
check('leg-00934-bayamon', bycode['00934'] == [('pr:municipality:bayamon', 'true')])
check('leg-00785-guayanilla', bycode['00785'] == [('pr:municipality:guayanilla', 'true')])
check('leg-00742-ceiba', bycode['00742'] == [('pr:municipality:ceiba', 'true')])
check('keep-00636', bycode['00636'] == [('pr:municipality:san-german', 'true')])
check('keep-00930', bycode['00930'] == [('pr:municipality:san-juan', 'true')])
check('hold-00938-present', bycode['00938'] == [('pr:municipality:san-juan', 'true')])
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
