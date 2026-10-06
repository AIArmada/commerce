import csv, sys
# Guadeloupe gate. B7 revisit: verify-only, zero data changes.
# 33 codes / 33 links, all primary, exact Hexasmal (La Poste)
# mapping incl. 97134 Saint-Louis and double-coded Les Abymes
# (97139/97142); GeoNames GP.txt 33/33 exact; WP commune table
# 32/32 INSEE+names exact. 97133 Saint-Barthélemy (97701) and
# 97150 Saint-Martin (97801) are Hexasmal rows for BL/MF, not GP.
# Run from repo root:
# python3 docs/agents/audit/gate_gp.py
A = './packages/addressing/resources/geography/guadeloupe-address-areas.csv'
C = './packages/addressing/resources/geography/guadeloupe-postal-codes.csv'
L = './packages/addressing/resources/geography/guadeloupe-postal-code-areas.csv'
MAP = {'97100': 'gp:commune:basse-terre',
       '97110': 'gp:commune:pointe-a-pitre',
       '97111': 'gp:commune:morne-a-l-eau',
       '97112': 'gp:commune:grand-bourg',
       '97113': 'gp:commune:gourbeyre',
       '97114': 'gp:commune:trois-rivieres',
       '97115': 'gp:commune:sainte-rose',
       '97116': 'gp:commune:pointe-noire',
       '97117': 'gp:commune:port-louis',
       '97118': 'gp:commune:saint-francois',
       '97119': 'gp:commune:vieux-habitants',
       '97120': 'gp:commune:saint-claude',
       '97121': 'gp:commune:anse-bertrand',
       '97122': 'gp:commune:baie-mahault',
       '97123': 'gp:commune:baillif',
       '97125': 'gp:commune:bouillante',
       '97126': 'gp:commune:deshaies',
       '97127': 'gp:commune:la-desirade',
       '97128': 'gp:commune:goyave',
       '97129': 'gp:commune:lamentin',
       '97130': 'gp:commune:capesterre-belle-eau',
       '97131': 'gp:commune:petit-canal',
       '97134': 'gp:commune:saint-louis',
       '97136': 'gp:commune:terre-de-bas',
       '97137': 'gp:commune:terre-de-haut',
       '97139': 'gp:commune:les-abymes',
       '97140': 'gp:commune:capesterre-de-marie-galante',
       '97141': 'gp:commune:vieux-fort',
       '97142': 'gp:commune:les-abymes',
       '97160': 'gp:commune:le-moule',
       '97170': 'gp:commune:petit-bourg',
       '97180': 'gp:commune:sainte-anne',
       '97190': 'gp:commune:le-gosier'}
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
check('areas-34', len(areas) == 34, str(len(areas)))
check('districts-2', sum(1 for r in areas if r['level'] == '1') == 2)
check('communes-32', sum(1 for r in areas if r['level'] == '2') == 32)
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-33', len(codes) == 33, str(len(codes)))
check('codes-set', set(codes) == set(MAP), str(set(codes) ^ set(MAP)))
links = list(csv.DictReader(open(L, newline='')))
check('links-33', len(links) == 33, str(len(links)))
got = {}
for r in links:
    got.setdefault(r['postcode'], []).append(r['area_source_id'])
check('per-code-mapping', {k: v[0] for k, v in got.items() if len(v) == 1} ==
      {k: v for k, v in MAP.items()}, 'see diff')
check('single-leg-each', all(len(v) == 1 for v in got.values()))
check('all-primary', all(r['is_primary'] == 'true' for r in links))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
