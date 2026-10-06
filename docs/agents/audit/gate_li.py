import csv, sys
# Liechtenstein gate. B4 revisit: verify-only, zero data changes.
# Tree exact vs ISO 3166-2:LI (11/11). Code set exact vs GeoNames LI
# dump (13/13 incl. Nendeln->Eschen + Schaanwald->Mauren locality map).
# 9489 held out: absent from the GN dump and swisstopo returns no
# zipcode hit for 9489 (9488 resolves to Schellenberg). UPU lie profile
# defers to Switzerland (Swiss Post operates LI post). Run from repo root:
# python3 docs/agents/audit/gate_li.py
A = './packages/addressing/resources/geography/liechtenstein-address-areas.csv'
C = './packages/addressing/resources/geography/liechtenstein-postal-codes.csv'
L = './packages/addressing/resources/geography/liechtenstein-postal-code-areas.csv'
ISO = {'01': 'Balzers', '02': 'Eschen', '03': 'Gamprin', '04': 'Mauren',
       '05': 'Planken', '06': 'Ruggell', '07': 'Schaan',
       '08': 'Schellenberg', '09': 'Triesen', '10': 'Triesenberg',
       '11': 'Vaduz'}
LINKS = {'9485': 'li:commune:eschen', '9486': 'li:commune:mauren',
         '9487': 'li:commune:gamprin', '9488': 'li:commune:schellenberg',
         '9490': 'li:commune:vaduz', '9491': 'li:commune:ruggell',
         '9492': 'li:commune:eschen', '9493': 'li:commune:mauren',
         '9494': 'li:commune:schaan', '9495': 'li:commune:triesen',
         '9496': 'li:commune:balzers', '9497': 'li:commune:triesenberg',
         '9498': 'li:commune:planken'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-11', len(areas) == 11, str(len(areas)))
got = {r['code']: r['name'] for r in areas}
check('iso-11', got == ISO, str({k for k in ISO if got.get(k) != ISO[k]}))
check('level-1', all(r['level'] == '1' and r['type'] == 'commune' for r in areas))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-13', set(codes) == set(LINKS), str(set(codes) ^ set(LINKS)))
check('9489-held-out', '9489' not in codes)
links = list(csv.DictReader(open(L, newline='')))
check('links-13', len(links) == 13, str(len(links)))
ok = all(r['postcode'] in LINKS and r['area_source_id'] == LINKS[r['postcode']]
         and r['is_primary'] == 'true' for r in links)
check('mapping-13', ok)
check('eschen-dual', sum(1 for r in links if r['area_source_id'] == 'li:commune:eschen') == 2)
check('mauren-dual', sum(1 for r in links if r['area_source_id'] == 'li:commune:mauren') == 2)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
