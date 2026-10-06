import csv, os, sys
# Fiji gate. B5 revisit: verify-only, zero data changes.
# Tree 19/19 vs ISO 3166-2:FJ (divisions C/E/N/W + dependency R +
# provinces 01-14 with division parents). FJ-08 stays hyphenated
# "Nadroga-Navosa" (Provinces of Fiji uses the hyphen 6x, the ISO
# "and" form 0x). No postal files: no postcode system (UPU fji
# example + contact only; GeoNames has no FJ postal export).
# Run from repo root: python3 docs/agents/audit/gate_fj.py
A = './packages/addressing/resources/geography/fiji-address-areas.csv'
C = './packages/addressing/resources/geography/fiji-postal-codes.csv'
L = './packages/addressing/resources/geography/fiji-postal-code-areas.csv'
DIV = {'C': 'Central', 'E': 'Eastern', 'N': 'Northern', 'W': 'Western'}
PROV = {'01': ('Ba', 'fj:division:western'), '02': ('Bua', 'fj:division:northern'),
        '03': ('Cakaudrove', 'fj:division:northern'),
        '04': ('Kadavu', 'fj:division:eastern'),
        '05': ('Lau', 'fj:division:eastern'),
        '06': ('Lomaiviti', 'fj:division:eastern'),
        '07': ('Macuata', 'fj:division:northern'),
        '08': ('Nadroga-Navosa', 'fj:division:western'),
        '09': ('Naitasiri', 'fj:division:central'),
        '10': ('Namosi', 'fj:division:central'),
        '11': ('Ra', 'fj:division:western'),
        '12': ('Rewa', 'fj:division:central'),
        '13': ('Serua', 'fj:division:central'),
        '14': ('Tailevu', 'fj:division:central')}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in areas}
check('areas-19', len(areas) == 19, str(len(areas)))
divs = {r['code']: r['name'] for r in areas if r['type'] == 'division'}
check('divisions-4', divs == DIV, str(divs))
rots = [r for r in areas if r['type'] == 'dependency']
check('rotuma', len(rots) == 1 and rots[0]['code'] == 'R' and rots[0]['name'] == 'Rotuma')
provs = {r['code']: (r['name'], r['parent_source_id'])
         for r in areas if r['type'] == 'province'}
check('provinces-14', provs == PROV,
      str({k for k in PROV if provs.get(k) != PROV[k]}))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
