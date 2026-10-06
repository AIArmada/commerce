import csv, os, sys
# Dominica gate. B3 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_dm.py
A = './packages/addressing/resources/geography/dominica-address-areas.csv'
C = './packages/addressing/resources/geography/dominica-postal-codes.csv'
L = './packages/addressing/resources/geography/dominica-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-10', len(rows) == 10, str(len(rows)))
# ISO 3166-2:DM: ten parishes numbered 02-11 (01 unassigned).
iso = {'dm:parish:saint-andrew': ('Saint Andrew', '02'),
       'dm:parish:saint-david': ('Saint David', '03'),
       'dm:parish:saint-george': ('Saint George', '04'),
       'dm:parish:saint-john': ('Saint John', '05'),
       'dm:parish:saint-joseph': ('Saint Joseph', '06'),
       'dm:parish:saint-luke': ('Saint Luke', '07'),
       'dm:parish:saint-mark': ('Saint Mark', '08'),
       'dm:parish:saint-patrick': ('Saint Patrick', '09'),
       'dm:parish:saint-paul': ('Saint Paul', '10'),
       'dm:parish:saint-peter': ('Saint Peter', '11')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'parish' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
# UPU dmaEn (07/2002): example + contact only, no postcode system.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
