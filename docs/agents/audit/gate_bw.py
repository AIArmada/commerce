import csv, os, sys
# Botswana gate. B8 revisit. Fix-and-fill, 1 cell:
# Selibe Phikwe -> Selebi Phikwe (WP title + Statoids +
# gov.bw DailyNews + citypopulation; ISO BW-SP stale).
# Tree 40/40: 17 L1 (10 districts + 2 cities + 5 towns incl.
# Orapa; ISO lacks only Orapa) + 23 subdistricts (WP exact,
# Chobe/North-East N/A). No postcode system: WP List of postal
# codes "no codes" + absent from UPU Aug-2022 type table +
# no BW.zip in the GeoNames index, so no postal files exist.
# Run from repo root:
# python3 docs/agents/audit/gate_bw.py
A = './packages/addressing/resources/geography/botswana-address-areas.csv'
C = './packages/addressing/resources/geography/botswana-postal-codes.csv'
L = './packages/addressing/resources/geography/botswana-postal-code-areas.csv'
L1 = {'CE': ('Central', 'district'), 'CH': ('Chobe', 'district'),
      'FR': ('Francistown', 'city'), 'GA': ('Gaborone', 'city'),
      'GH': ('Ghanzi', 'district'), 'JW': ('Jwaneng', 'town'),
      'KG': ('Kgalagadi', 'district'), 'KL': ('Kgatleng', 'district'),
      'KW': ('Kweneng', 'district'), 'LO': ('Lobatse', 'town'),
      'NE': ('North-East', 'district'), 'NW': ('North-West', 'district'),
      'OR': ('Orapa', 'town'), 'SP': ('Selebi Phikwe', 'town'),
      'SE': ('South-East', 'district'), 'SO': ('Southern', 'district'),
      'ST': ('Sowa Town', 'town')}
SUBS = {'Central': ['Bobirwa', 'Boteti', 'Mahalapye', 'Palapye',
                    'Serowe', 'Tonota', 'Tutume'],
        'Ghanzi': ['Charleshill', 'Ghanzi'],
        'Kgalagadi': ['Hukuntsi', 'Tsabong'],
        'Kgatleng': ['Mochudi'],
        'Kweneng': ['Letlhakeng', 'Mogoditshane', 'Molepolole'],
        'North-West': ['Maun', 'Okavango'],
        'South-East': ['Ramotswa', 'Tlokweng'],
        'Southern': ['Goodhope', 'Kanye', 'Mabutsane', 'Moshupa']}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw = open(A, 'rb').read()
check('areas-lf', b'\r' not in raw)
check('areas-trailing-nl', raw.endswith(b'\n'))
areas = list(csv.DictReader(open(A, newline='')))
check('areas-40', len(areas) == 40, str(len(areas)))
l1 = {r['code']: (r['name'], r['type']) for r in areas if r['level'] == '1'}
check('l1-17', l1 == L1, str({k for k in L1 if l1.get(k) != L1[k]}))
check('selebi-name', l1.get('SP', ('',))[0] == 'Selebi Phikwe')
subs = {}
for r in areas:
    if r['level'] == '2':
        subs.setdefault(r['parent_source_id'], []).append(r['name'])
byname = {}
for r in areas:
    if r['level'] == '1':
        byname[r['source_id']] = r['name']
got = {byname[k]: sorted(v) for k, v in subs.items()}
want = {k: sorted(v) for k, v in SUBS.items()}
check('subs-23', got == want, str({k for k in want if got.get(k) != want[k]}))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
