import csv, sys
# Anguilla gate. B4 revisit: verify-only, zero data changes.
# Tree 14/14 vs Statoids district table (no ISO 3166-2:AI codes
# exist). Single code AI-2640 vs GeoNames AI dump + WP citation of
# The Anguillian 2007-10-12 ("Anguilla Has a Postal Code, AI-2640");
# UPU has no AI profile (live URL serves the shell, no archive).
# Zero links: island-wide code. Run from repo root:
# python3 docs/agents/audit/gate_ai.py
A = './packages/addressing/resources/geography/anguilla-address-areas.csv'
C = './packages/addressing/resources/geography/anguilla-postal-codes.csv'
L = './packages/addressing/resources/geography/anguilla-postal-code-areas.csv'
NAMES = ['Blowing Point', 'East End', 'George Hill', 'Island Harbour',
         'North Hill', 'North Side', 'Sandy Ground', 'Sandy Hill',
         'South Hill', 'Stoney Ground', 'The Farrington', 'The Quarter',
         'The Valley', 'West End']
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


areas = list(csv.DictReader(open(A, newline='')))
check('areas-14', len(areas) == 14, str(len(areas)))
check('names-14', [r['name'] for r in areas] == NAMES)
check('districts-14', all(r['type'] == 'district' and r['level'] == '1' for r in areas))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
check('codes-ai2640', codes == ['AI-2640'], str(codes))
links = list(csv.DictReader(open(L, newline='')))
check('links-0', len(links) == 0, str(len(links)))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES: {fails}')
sys.exit(1 if fails else 0)
