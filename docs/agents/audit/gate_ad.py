import csv, os, sys
# Andorra gate. B2 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_ad.py
A = './packages/addressing/resources/geography/andorra-address-areas.csv'
C = './packages/addressing/resources/geography/andorra-postal-codes.csv'
L = './packages/addressing/resources/geography/andorra-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-7', len(rows) == 7, str(len(rows)))
# ISO 3166-2:AD: seven parishes, exact code set.
iso = {'ad:parish:canillo': ('Canillo', '02'),
       'ad:parish:encamp': ('Encamp', '03'),
       'ad:parish:ordino': ('Ordino', '05'),
       'ad:parish:la-massana': ('La Massana', '04'),
       'ad:parish:andorra-la-vella': ('Andorra la Vella', '07'),
       'ad:parish:sant-julia-de-loria': ('Sant Julià de Lòria', '06'),
       'ad:parish:escaldes-engordany': ('Escaldes-Engordany', '08')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'parish' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
# GeoNames AD postal dump (code + parish + admin1, 7/7) + UPU
# andEn AD700 Escaldes anchor.
table = {'AD100': 'ad:parish:canillo',
         'AD200': 'ad:parish:encamp',
         'AD300': 'ad:parish:ordino',
         'AD400': 'ad:parish:la-massana',
         'AD500': 'ad:parish:andorra-la-vella',
         'AD600': 'ad:parish:sant-julia-de-loria',
         'AD700': 'ad:parish:escaldes-engordany'}
check('codes-7', sorted(codes) == sorted(table), str(sorted(codes)))
check('links-7', len(links) == 7, str(len(links)))
bycode = {r['postcode']: r for r in links}
for pc, sid in table.items():
    r = bycode.get(pc)
    check(f'link-{pc}', bool(r) and r['area_source_id'] == sid
          and r['relationship_type'] == 'served_by'
          and r['is_primary'] == 'true', str(r))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
