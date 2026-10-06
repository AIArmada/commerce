import csv, sys
# Niue gate. B5 revisit: verify-only, zero data changes.
# Tree 14/14 vs Statoids Villages of Niue (no ISO 3166-2:NU
# codes exist; 01-14 synthetic). Sole code 9974 vs the UPU niu
# profile (single postcode for the whole territory) + GeoNames
# NU dump; zero links. Run from repo root:
# python3 docs/agents/audit/gate_nu.py
A = './packages/addressing/resources/geography/niue-address-areas.csv'
C = './packages/addressing/resources/geography/niue-postal-codes.csv'
L = './packages/addressing/resources/geography/niue-postal-code-areas.csv'
NAMES = {'14': 'Alofi North', '13': 'Alofi South', '11': 'Avatele',
         '09': 'Hakupu', '04': 'Hikutavake', '07': 'Lakepa',
         '08': 'Liku', '01': 'Makefu', '06': 'Mutalau',
         '03': 'Namukulu', '12': 'Tamakautoga', '05': 'Toi',
         '02': 'Tuapa', '10': 'Vaiea'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-14', len(areas) == 14, str(len(areas)))
got = {r['code']: r['name'] for r in areas}
check('names-14', got == NAMES, str({k for k in NAMES if got.get(k) != NAMES[k]}))
check('villages-14', all(r['type'] == 'village' and r['level'] == '1' for r in areas))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-9974', codes == ['9974'], str(codes))
links = list(csv.DictReader(open(L, newline='')))
check('links-0', len(links) == 0, str(len(links)))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
