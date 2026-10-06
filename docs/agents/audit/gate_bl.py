import csv, os, sys
# Saint-Barthelemy gate. B1 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_bl.py
A = './packages/addressing/resources/geography/saint-barthelemy-address-areas.csv'
C = './packages/addressing/resources/geography/saint-barthelemy-postal-codes.csv'
L = './packages/addressing/resources/geography/saint-barthelemy-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
# ISO 3166-2:BL defines no codes (no subdivisions); 01 synthetic.
check('areas-1', len(rows) == 1, str(len(rows)))
r = rows[0] if rows else {}
check('single-collectivity',
      r.get('source_id') == 'bl:overseas_collectivity:saint-barthelemy'
      and r.get('name') == 'Saint-Barthélemy'
      and r.get('code') == '01' and r.get('level') == '1'
      and r.get('parent_source_id') == '', str(r))
codes = [x['code'] for x in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
# UPU blmEn (08/2011): single code 97133 left of the locality.
check('codes-97133', codes == ['97133'], str(codes))
check('links-1', len(links) == 1, str(len(links)))
l = links[0] if links else {}
check('link-97133', l.get('postcode') == '97133'
      and l.get('area_source_id') == 'bl:overseas_collectivity:saint-barthelemy'
      and l.get('relationship_type') == 'served_by'
      and l.get('is_primary') == 'true', str(l))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
