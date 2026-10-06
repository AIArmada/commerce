import csv, os, sys
# Jamaica gate. B4 revisit: verify-only, zero data changes.
# Tree 14/14 vs ISO 3166-2:JM. No postcode system per the UPU jam
# profile 05/2021 ("Jamaica has no postcode system"; Kingston
# sector codes are not postcodes and ship no directory), so no
# postal files exist. Run from repo root:
# python3 docs/agents/audit/gate_jm.py
A = './packages/addressing/resources/geography/jamaica-address-areas.csv'
C = './packages/addressing/resources/geography/jamaica-postal-codes.csv'
L = './packages/addressing/resources/geography/jamaica-postal-code-areas.csv'
ISO = {'13': 'Clarendon', '09': 'Hanover', '01': 'Kingston',
       '12': 'Manchester', '04': 'Portland', '02': 'Saint Andrew',
       '06': 'Saint Ann', '14': 'Saint Catherine', '11': 'Saint Elizabeth',
       '08': 'Saint James', '05': 'Saint Mary', '03': 'Saint Thomas',
       '07': 'Trelawny', '10': 'Westmoreland'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-14', len(areas) == 14, str(len(areas)))
got = {r['code']: r['name'] for r in areas}
check('iso-14', got == ISO, str({k for k in ISO if got.get(k) != ISO[k]}))
check('parishes-14', all(r['type'] == 'parish' and r['level'] == '1' for r in areas))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
