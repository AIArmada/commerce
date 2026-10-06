import csv, os, sys
# Belize gate. B1 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_bz.py
A = './packages/addressing/resources/geography/belize-address-areas.csv'
C = './packages/addressing/resources/geography/belize-postal-codes.csv'
L = './packages/addressing/resources/geography/belize-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-6', len(rows) == 6, str(len(rows)))
# ISO 3166-2:BZ: six districts, exact code set.
iso = {'bz:district:belize': ('Belize', 'BZ'),
       'bz:district:cayo': ('Cayo', 'CY'),
       'bz:district:corozal': ('Corozal', 'CZL'),
       'bz:district:orange-walk': ('Orange Walk', 'OW'),
       'bz:district:stann-creek': ('Stann Creek', 'SC'),
       'bz:district:toledo': ('Toledo', 'TOL')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'district' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
# UPU blzEn (05/2021): Post-Office-reference addressing, no
# postcode system.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
