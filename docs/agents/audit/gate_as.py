import csv, sys
# American Samoa gate. B6 revisit: verify-only, zero data changes.
# Tree 20/20: 3 districts + Rose/Swains atolls (Statoids treats the
# pair as one "Unorganized" row) + 15 counties (Fofo corroborated
# by its WP article; Statoids predates the split). Sole code 96799
# vs the UPU asm profile + GeoNames AS dump; zero links. Run from
# repo root: python3 docs/agents/audit/gate_as.py
A = './packages/addressing/resources/geography/american-samoa-address-areas.csv'
C = './packages/addressing/resources/geography/american-samoa-postal-codes.csv'
L = './packages/addressing/resources/geography/american-samoa-postal-code-areas.csv'
WEST = ['Lealataua', 'Fofo', 'Leasina', 'Tualatai', 'Tualauta']
EAST = ['Ituʻau', 'Maʻoputasi', 'Vaifanua', 'Sua', 'Saʻole']
MANUA = ['Ofu', 'Olosega', 'Taʻū', 'Faleasao', 'Fitiuta']
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in areas}
check('areas-20', len(areas) == 20, str(len(areas)))
check('districts-3', sorted(r['name'] for r in areas if r['type'] == 'district')
      == ['Eastern', 'Manuʻa', 'Western'])
check('atolls-2', sorted(r['name'] for r in areas if r['type'] == 'atoll')
      == ['Rose', 'Swains'])
cos = [r for r in areas if r['type'] == 'county']
check('counties-15', len(cos) == 15, str(len(cos)))
check('west-5', sorted(r['name'] for r in cos
                       if r['parent_source_id'] == 'as:district:western') == sorted(WEST))
check('east-5', sorted(r['name'] for r in cos
                       if r['parent_source_id'] == 'as:district:eastern') == sorted(EAST))
check('manua-5', sorted(r['name'] for r in cos
                        if r['parent_source_id'] == 'as:district:manua') == sorted(MANUA))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-96799', codes == ['96799'], str(codes))
links = list(csv.DictReader(open(L, newline='')))
check('links-0', len(links) == 0, str(len(links)))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
