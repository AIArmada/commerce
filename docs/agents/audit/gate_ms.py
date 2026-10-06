import csv, os, sys
# Montserrat gate. B1 revisit: dropped phantom ms:parish:saint-patrick
# (4->3 areas; Saint Patrick's is a destroyed village, GeoNames PPLW;
# three parish articles + Statoids + GENC + ISO draft all say three
# parishes; Plymouth sits in Saint Anthony) and retargeted MSR1310
# Cudjoe Head saint-anthony -> saint-peter (UPU parish digit 1,
# Photon county Saint Peter, Saint Anthony wholly uninhabited
# exclusion zone). Run from repo root: python3 docs/agents/audit/gate_ms.py
A = './packages/addressing/resources/geography/montserrat-address-areas.csv'
C = './packages/addressing/resources/geography/montserrat-postal-codes.csv'
L = './packages/addressing/resources/geography/montserrat-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-3', len(rows) == 3, str(len(rows)))
check('no-saint-patrick', 'ms:parish:saint-patrick' not in byid)
# ISO 3166-2:MS defines no codes (divisions "not relevant");
# bundled 01/02/03 are synthetic.
exp = {'ms:parish:saint-peter': ('Saint Peter', '01'),
       'ms:parish:saint-georges': ('Saint Georges', '02'),
       'ms:parish:saint-anthony': ('Saint Anthony', '03')}
for sid, (name, code) in exp.items():
    r = byid.get(sid)
    check(f'parish-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'parish' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
# En-wiki "Postal codes in Montserrat" table: 8 sub-post-office codes.
table = {'MSR1110': 'GPO (Brades)', 'MSR1120': 'Little Bay',
         'MSR1210': 'Davy Hill', 'MSR1230': 'St John\'s',
         'MSR1250': 'Look Out', 'MSR1310': 'Cudjoe Head',
         'MSR1330': 'St Peter\'s', 'MSR1350': 'Salem'}
check('codes-8', sorted(codes) == sorted(table), str(sorted(codes)))
check('links-8', len(links) == 8, str(len(links)))
# UPU msrEn (06/2026): first digit is the parish; all 8 codes are
# 1xxx so all 8 link to parish 01 Saint Peter, single primaries.
bycode = {r['postcode']: r for r in links}
for pc in table:
    r = bycode.get(pc)
    check(f'link-{pc}', bool(r)
          and r['area_source_id'] == 'ms:parish:saint-peter'
          and r['relationship_type'] == 'served_by'
          and r['is_primary'] == 'true', str(r))
check('no-orphans', all(r['area_source_id'] in byid for r in links))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
