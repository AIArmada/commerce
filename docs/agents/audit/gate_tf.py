import csv, os, sys
# French-Southern-Territories (TF) gate. B1 revisit: verify-only,
# zero data changes. Run from repo root: python3 docs/agents/audit/gate_tf.py
A = './packages/addressing/resources/geography/french-southern-territories-address-areas.csv'
C = './packages/addressing/resources/geography/french-southern-territories-postal-codes.csv'
L = './packages/addressing/resources/geography/french-southern-territories-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-5', len(rows) == 5, str(len(rows)))
# ISO 3166-2:TF includes no codes; 01-05 synthetic. The five
# districts match the UPU atfEn (08/2011) enumeration (Saint Paul
# and Amsterdam, Crozet, Kerguelen, Adelie Land, Scattered Islands).
exp = {'tf:district:adelie-land': ('Adélie Land', '01'),
       'tf:district:crozet-islands': ('Crozet Islands', '02'),
       'tf:district:kerguelen-islands': ('Kerguelen Islands', '03'),
       'tf:district:saint-paul-and-amsterdam-islands': ('Saint-Paul and Amsterdam Islands', '04'),
       'tf:district:scattered-islands': ('Scattered Islands', '05')}
for sid, (name, code) in exp.items():
    r = byid.get(sid)
    check(f'district-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'district' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
# UPU atfEn: uninhabited, no postcode system.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
