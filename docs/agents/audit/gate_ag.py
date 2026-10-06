import csv, os, sys
# Antigua-and-Barbuda gate. B2 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_ag.py
A = './packages/addressing/resources/geography/antigua-and-barbuda-address-areas.csv'
C = './packages/addressing/resources/geography/antigua-and-barbuda-postal-codes.csv'
L = './packages/addressing/resources/geography/antigua-and-barbuda-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-8', len(rows) == 8, str(len(rows)))
# ISO 3166-2:AG: six parishes + Barbuda + Redonda, exact set.
iso = {'ag:parish:saint-george': ('Saint George', '03'),
       'ag:parish:saint-john': ('Saint John', '04'),
       'ag:parish:saint-mary': ('Saint Mary', '05'),
       'ag:parish:saint-paul': ('Saint Paul', '06'),
       'ag:parish:saint-peter': ('Saint Peter', '07'),
       'ag:parish:saint-philip': ('Saint Philip', '08'),
       'ag:dependency:barbuda': ('Barbuda', '10'),
       'ag:dependency:redonda': ('Redonda', '11')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
# UPU atgEn (07/2002): contact block only, no postcode system.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
