import csv, os, sys
# Grenada gate. B2 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_gd.py
A = './packages/addressing/resources/geography/grenada-address-areas.csv'
C = './packages/addressing/resources/geography/grenada-postal-codes.csv'
L = './packages/addressing/resources/geography/grenada-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-7', len(rows) == 7, str(len(rows)))
# ISO 3166-2:GD: six parishes + GD-10 Southern Grenadine Islands
# (bundled under the common dependency name Carriacou).
iso = {'gd:parish:saint-andrew': ('Saint Andrew', '01'),
       'gd:parish:saint-david': ('Saint David', '02'),
       'gd:parish:saint-george': ('Saint George', '03'),
       'gd:parish:saint-john': ('Saint John', '04'),
       'gd:parish:saint-mark': ('Saint Mark', '05'),
       'gd:parish:saint-patrick': ('Saint Patrick', '06'),
       'gd:dependency:carriacou': ('Carriacou', '10')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
check('dependency-type', byid.get('gd:dependency:carriacou', {}).get('type') == 'dependency')
# UPU grdEn (05/2004): address format with no postcode section.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
