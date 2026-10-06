import csv, sys
# French Guiana gate. B6 fix-and-fill: "Papaichton"->"Papaïchton" +
# "Remire-Montjoly"->"Rémire-Montjoly" (GeoNames + WP titles; ST
# Lemba precedent) and the Papaichton area code 97340->97316
# (Hexasmal lists Papaichton only under 97316; 97340 belongs to
# Grand-Santi). Tree 22/22 + overlay 25/25 exact vs Hexasmal +
# GeoNames GF (52 CEDEX rows correctly excluded). Run from repo
# root: python3 docs/agents/audit/gate_gf.py
A = './packages/addressing/resources/geography/french-guiana-address-areas.csv'
C = './packages/addressing/resources/geography/french-guiana-postal-codes.csv'
L = './packages/addressing/resources/geography/french-guiana-postal-code-areas.csv'
LINKS = {'97300': 'gf:commune:cayenne', '97310': 'gf:commune:kourou',
         '97311': 'gf:commune:roura', '97312': 'gf:commune:saint-elie',
         '97313': 'gf:commune:saint-georges', '97314': 'gf:commune:saul',
         '97315': 'gf:commune:sinnamary',
         '97316': 'gf:commune:papaichton', '97317': 'gf:commune:apatou',
         '97318': 'gf:commune:mana',
         '97319': 'gf:commune:awala-yalimapo',
         '97320': 'gf:commune:saint-laurent-du-maroni',
         '97330': 'gf:commune:camopi',
         '97340': 'gf:commune:grand-santi',
         '97350': 'gf:commune:iracoubo', '97351': 'gf:commune:matoury',
         '97352': 'gf:commune:roura', '97353': 'gf:commune:regina',
         '97354': 'gf:commune:remire-montjoly',
         '97355': 'gf:commune:macouria',
         '97356': 'gf:commune:montsinery-tonnegrande',
         '97360': 'gf:commune:mana',
         '97370': 'gf:commune:maripasoula',
         '97380': 'gf:commune:ouanary', '97390': 'gf:commune:regina'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in areas}
check('areas-23', len(areas) == 23, str(len(areas)))
check('communes-22', sum(1 for r in areas if r['type'] == 'commune') == 22)
check('papaichton-name', byid['gf:commune:papaichton']['name'] == 'Papaïchton',
      byid['gf:commune:papaichton']['name'])
check('papaichton-code', byid['gf:commune:papaichton']['code'] == '97316',
      byid['gf:commune:papaichton']['code'])
check('remire-name', byid['gf:commune:remire-montjoly']['name'] == 'Rémire-Montjoly',
      byid['gf:commune:remire-montjoly']['name'])
check('grand-santi-code', byid['gf:commune:grand-santi']['code'] == '97340')
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-25', set(codes) == set(LINKS), str(set(codes) ^ set(LINKS)))
links = list(csv.DictReader(open(L, newline='')))
check('links-25', len(links) == 25, str(len(links)))
ok = all(r['postcode'] in LINKS and r['area_source_id'] == LINKS[r['postcode']]
         and r['is_primary'] == 'true' for r in links)
check('mapping-25', ok)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
