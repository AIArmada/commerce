import csv, os, sys
# Aruba gate. B3 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_aw.py
A = './packages/addressing/resources/geography/aruba-address-areas.csv'
C = './packages/addressing/resources/geography/aruba-postal-codes.csv'
L = './packages/addressing/resources/geography/aruba-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-9', len(rows) == 9, str(len(rows)))
# Eight CBS census regions (citypopulation: Noord/Tanki Leendert,
# Oranjestad Oost/West, Paradera, San Nicolas Noord/Zuid, Santa
# Cruz, Savaneta; bundled uses English East/Nicolaas forms) plus
# the intentional capital Oranjestad addressing row (09). ISO
# 3166-2:AW defines no codes; 01-09 synthetic.
exp = {'aw:region:noord': ('Noord', '01'),
       'aw:region:oranjestad-west': ('Oranjestad West', '02'),
       'aw:region:oranjestad-east': ('Oranjestad East', '03'),
       'aw:region:paradera': ('Paradera', '04'),
       'aw:region:san-nicolaas-noord': ('San Nicolaas Noord', '05'),
       'aw:region:san-nicolaas-zuid': ('San Nicolaas Zuid', '06'),
       'aw:region:santa-cruz': ('Santa Cruz', '07'),
       'aw:region:savaneta': ('Savaneta', '08')}
for sid, (name, code) in exp.items():
    r = byid.get(sid)
    check(f'region-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'region' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
r = byid.get('aw:capital_city:oranjestad')
check('capital-row', bool(r) and r['name'] == 'Oranjestad' and r['code'] == '09'
      and r['type'] == 'capital_city' and r['level'] == '1', str(r))
# UPU abwEn (1/2019): address format with no postcode section.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
