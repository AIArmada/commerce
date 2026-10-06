import csv, sys
# New Caledonia gate. B8 revisit: fix-and-fill, 2 areas cells
# (Koné wiki-markup strip + Poya parent South->North); codes
# verify-only. Poya: WP (2592/2802 inhabitants + main
# settlement in Poya-Nord) + GeoNames Province Nord admin tag
# on both 98827/98877 rows. 50 codes / 50 links, all primary,
# exact vs OPT-NC Feb-2025 table (incl. 98880 LA FOA vs
# 98881 FARINO — the GeoNames "Farino 98880" row is a locality
# artefact) and GeoNames NC.txt 50/50 set. No ISO 3166-2:NC
# codes exist (FR-NC under France). Run from repo root:
# python3 docs/agents/audit/gate_nc.py
A = './packages/addressing/resources/geography/new-caledonia-address-areas.csv'
C = './packages/addressing/resources/geography/new-caledonia-postal-codes.csv'
L = './packages/addressing/resources/geography/new-caledonia-postal-code-areas.csv'
MAP = {'98800': 'nc:commune:noumea', '98809': 'nc:commune:le-mont-dore',
       '98810': 'nc:commune:le-mont-dore', '98811': 'nc:commune:belep',
       '98812': 'nc:commune:boulouparis', '98813': 'nc:commune:canala',
       '98814': 'nc:commune:ouvea', '98815': 'nc:commune:hienghene',
       '98816': 'nc:commune:houailou', '98817': 'nc:commune:kaala-gomen',
       '98818': 'nc:commune:kouaoua', '98819': 'nc:commune:moindou',
       '98820': 'nc:commune:lifou', '98821': 'nc:commune:ouegoa',
       '98822': 'nc:commune:poindimie', '98823': 'nc:commune:ponerihouen',
       '98824': 'nc:commune:pouebo', '98825': 'nc:commune:pouembout',
       '98826': 'nc:commune:poum', '98827': 'nc:commune:poya',
       '98828': 'nc:commune:mare', '98829': 'nc:commune:thio',
       '98830': 'nc:commune:dumbea', '98831': 'nc:commune:touho',
       '98832': 'nc:commune:isle-of-pines', '98833': 'nc:commune:voh',
       '98834': 'nc:commune:yate', '98835': 'nc:commune:dumbea',
       '98836': 'nc:commune:dumbea', '98837': 'nc:commune:dumbea',
       '98838': 'nc:commune:houailou', '98839': 'nc:commune:dumbea',
       '98840': 'nc:commune:paita', '98850': 'nc:commune:koumac',
       '98859': 'nc:commune:kone', '98860': 'nc:commune:kone',
       '98870': 'nc:commune:bourail', '98874': 'nc:commune:le-mont-dore',
       '98875': 'nc:commune:le-mont-dore', '98876': 'nc:commune:le-mont-dore',
       '98877': 'nc:commune:poya', '98878': 'nc:commune:mare',
       '98880': 'nc:commune:la-foa', '98881': 'nc:commune:farino',
       '98882': 'nc:commune:sarramea', '98883': 'nc:commune:voh',
       '98884': 'nc:commune:lifou', '98885': 'nc:commune:lifou',
       '98889': 'nc:commune:paita', '98890': 'nc:commune:paita'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


for label, path in (('areas', A), ('codes', C), ('links', L)):
    raw = open(path, 'rb').read()
    check(f'{label}-lf', b'\r' not in raw)
    check(f'{label}-trailing-nl', raw.endswith(b'\n'))
areas = list(csv.DictReader(open(A, newline='')))
check('areas-36', len(areas) == 36, str(len(areas)))
byId = {r['source_id']: r for r in areas}
check('kone-name', byId['nc:commune:kone']['name'] == 'Koné',
      repr(byId['nc:commune:kone']['name']))
check('poya-north', byId['nc:commune:poya']['parent_source_id'] ==
      'nc:province:north-province',
      byId['nc:commune:poya']['parent_source_id'])
check('north-17', sum(1 for r in areas
                      if r['parent_source_id'] == 'nc:province:north-province') == 17)
check('south-13', sum(1 for r in areas
                      if r['parent_source_id'] == 'nc:province:south-province') == 13)
check('loyalty-3', sum(1 for r in areas
                       if r['parent_source_id'] == 'nc:province:loyalty-islands-province') == 3)
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-50', len(codes) == 50, str(len(codes)))
check('codes-set', set(codes) == set(MAP), str(set(codes) ^ set(MAP)))
links = list(csv.DictReader(open(L, newline='')))
check('links-50', len(links) == 50, str(len(links)))
got = {r['postcode']: r['area_source_id'] for r in links}
check('per-code-mapping', got == MAP, str({k for k in MAP if got.get(k) != MAP[k]}))
check('all-primary', all(r['is_primary'] == 'true' for r in links))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
