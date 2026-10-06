import csv, os, sys
# Turks-and-Caicos gate. B2 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_tc.py
A = './packages/addressing/resources/geography/turks-and-caicos-address-areas.csv'
C = './packages/addressing/resources/geography/turks-and-caicos-postal-codes.csv'
L = './packages/addressing/resources/geography/turks-and-caicos-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-6', len(rows) == 6, str(len(rows)))
# Six administrative districts (2 Turks Islands + 4 Caicos Islands);
# East Caicos administers under South Caicos, West Caicos under
# Providenciales. ISO 3166-2:TC defines no codes; 01-06 synthetic.
exp = {'tc:district:providenciales': ('Providenciales', '01'),
       'tc:district:north-caicos': ('North Caicos', '02'),
       'tc:district:middle-caicos': ('Middle Caicos', '03'),
       'tc:district:south-caicos': ('South Caicos', '04'),
       'tc:district:grand-turk': ('Grand Turk', '05'),
       'tc:district:salt-cay': ('Salt Cay', '06')}
for sid, (name, code) in exp.items():
    r = byid.get(sid)
    check(f'district-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'district' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
# UPU tcaEn (10/2025): single postcode TKCA 1ZZ for the whole
# territory; code-only import with no links (non-discriminating).
check('codes-tkca1zz', codes == ['TKCA 1ZZ'], str(codes))
check('links-0', links == [], str(links))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
