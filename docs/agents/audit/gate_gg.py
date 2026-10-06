import csv, sys
# Guernsey gate. B4 revisit: verify-only, zero data changes.
# Tree: 10 parishes + Alderney + Sark vs Statoids (no ISO 3166-2:GG
# codes exist). St-spellings kept: WP prose + UPU example use "St",
# "Saint" appears only in article titles; no-period St matches the
# JE/GG in-repo island convention. Herm/Jethou/Lihou/Brecqhou stay
# unlisted (Herm GY1 3HR + Jethou GY1 4AB in St Peter Port).
# Postal 10/10 vs GY postcode-area table + GeoNames GG dump (both
# corroborate both sides of the GY6/7/8 duals). Run from repo root:
# python3 docs/agents/audit/gate_gg.py
A = './packages/addressing/resources/geography/guernsey-address-areas.csv'
C = './packages/addressing/resources/geography/guernsey-postal-codes.csv'
L = './packages/addressing/resources/geography/guernsey-postal-code-areas.csv'
NAMES = {'gg:dependency:alderney': 'Alderney', 'gg:parish:castel': 'Castel',
         'gg:parish:forest': 'Forest', 'gg:dependency:sark': 'Sark',
         'gg:parish:st-andrew': 'St Andrew', 'gg:parish:st-martin': 'St Martin',
         'gg:parish:st-peter-port': 'St Peter Port',
         'gg:parish:st-pierre-du-bois': 'St Pierre du Bois',
         'gg:parish:st-sampson': 'St Sampson',
         'gg:parish:st-saviour': 'St Saviour',
         'gg:parish:torteval': 'Torteval', 'gg:parish:vale': 'Vale'}
LINKS = {'GY1': ('gg:parish:st-peter-port', []), 'GY10': ('gg:dependency:sark', []),
         'GY2': ('gg:parish:st-sampson', []), 'GY3': ('gg:parish:vale', []),
         'GY4': ('gg:parish:st-martin', []), 'GY5': ('gg:parish:castel', []),
         'GY6': ('gg:parish:st-andrew', ['gg:parish:vale']),
         'GY7': ('gg:parish:st-pierre-du-bois', ['gg:parish:st-saviour']),
         'GY8': ('gg:parish:forest', ['gg:parish:torteval']),
         'GY9': ('gg:dependency:alderney', [])}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in areas}
check('areas-12', len(areas) == 12, str(len(areas)))
check('names-12', all(byid[k]['name'] == v for k, v in NAMES.items()))
check('parishes-10', sum(1 for r in areas if r['type'] == 'parish') == 10)
check('dependencies-2', sum(1 for r in areas if r['type'] == 'dependency') == 2)
check('islets-unlisted', not any('herm' in k or 'jethou' in k or 'lihou' in k or 'brecqhou' in k
                                 for k in byid))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-10', set(codes) == set(LINKS), str(set(codes) ^ set(LINKS)))
links = list(csv.DictReader(open(L, newline='')))
check('links-13', len(links) == 13, str(len(links)))
got = {}
for r in links:
    got.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
bad = [c for c, (p, s) in LINKS.items()
       if got.get(c) != [(p, 'true')] + [(x, 'false') for x in s]]
check('mapping-10', not bad, str(bad))
check('primaries-10', sum(1 for r in links if r['is_primary'] == 'true') == 10)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
