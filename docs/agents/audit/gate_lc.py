import csv, os, sys
# Saint-Lucia gate. B3 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_lc.py
A = './packages/addressing/resources/geography/saint-lucia-address-areas.csv'
C = './packages/addressing/resources/geography/saint-lucia-postal-codes.csv'
L = './packages/addressing/resources/geography/saint-lucia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-10', len(rows) == 10, str(len(rows)))
# ISO 3166-2:LC: ten quarters (04/09 unassigned: former Dauphin +
# Praslin quarters), exact mapping including LC-12 Canaries.
iso = {'lc:district:anse-la-raye': ('Anse la Raye', '01'),
       'lc:district:castries': ('Castries', '02'),
       'lc:district:choiseul': ('Choiseul', '03'),
       'lc:district:dennery': ('Dennery', '05'),
       'lc:district:gros-islet': ('Gros Islet', '06'),
       'lc:district:laborie': ('Laborie', '07'),
       'lc:district:micoud': ('Micoud', '08'),
       'lc:district:soufriere': ('Soufrière', '10'),
       'lc:district:vieux-fort': ('Vieux Fort', '11'),
       'lc:district:canaries': ('Canaries', '12')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'district' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
# Government of Saint Lucia postcode table (47 delivery codes;
# 7 private-letter-box codes LC01 601/LC02 601+701+801/LC03 301/
# LC07 301/LC12 301 excluded): district per sample address, with
# Babonneau town (LC02 101/301/401) in Castries Quarter and the
# LC01 501 Marisule Castries-primary border dual.
CA = 'lc:district:castries'
GI = 'lc:district:gros-islet'
table = {'LC01 101': GI, 'LC01 201': GI, 'LC01 301': GI, 'LC01 401': GI,
         'LC01 501': CA, 'LC02 101': CA, 'LC02 201': CA, 'LC02 301': CA,
         'LC02 401': CA, 'LC02 501': CA, 'LC03 101': CA, 'LC03 201': CA,
         'LC04 101': CA, 'LC04 201': CA, 'LC04 301': CA, 'LC04 401': CA,
         'LC05 101': CA, 'LC05 201': CA, 'LC06 101': CA, 'LC06 201': CA,
         'LC06 301': CA, 'LC06 401': CA, 'LC07 101': CA, 'LC07 201': CA,
         'LC08 101': 'lc:district:anse-la-raye', 'LC08 301': 'lc:district:anse-la-raye',
         'LC08 401': 'lc:district:anse-la-raye', 'LC08 501': 'lc:district:anse-la-raye',
         'LC09 101': 'lc:district:soufriere', 'LC09 201': 'lc:district:soufriere',
         'LC10 101': 'lc:district:choiseul', 'LC10 201': 'lc:district:choiseul',
         'LC10 301': 'lc:district:choiseul', 'LC11 101': 'lc:district:laborie',
         'LC12 101': 'lc:district:vieux-fort', 'LC12 201': 'lc:district:vieux-fort',
         'LC13 101': 'lc:district:vieux-fort', 'LC14 101': 'lc:district:micoud',
         'LC14 201': 'lc:district:micoud', 'LC15 101': 'lc:district:micoud',
         'LC15 201': 'lc:district:micoud', 'LC16 101': 'lc:district:dennery',
         'LC17 101': 'lc:district:dennery', 'LC17 201': 'lc:district:dennery',
         'LC17 301': 'lc:district:dennery', 'LC17 401': 'lc:district:dennery',
         'LC18 101': 'lc:district:canaries'}
check('codes-47', sorted(codes) == sorted(table), str(sorted(set(codes) ^ set(table))))
check('links-48', len(links) == 48, str(len(links)))
prim = [r for r in links if r['is_primary'] == 'true']
bycode = {r['postcode']: r for r in prim}
for pc, sid in table.items():
    r = bycode.get(pc)
    check(f'link-{pc}', bool(r) and r['area_source_id'] == sid
          and r['relationship_type'] == 'served_by', str(r))
sec = [r for r in links if r['is_primary'] != 'true']
check('dual-501', len(sec) == 1 and sec[0]['postcode'] == 'LC01 501'
      and sec[0]['area_source_id'] == GI, str(sec))
check('box-codes-absent', not (set(codes) & {'LC01 601', 'LC02 601', 'LC02 701',
      'LC02 801', 'LC03 301', 'LC07 301', 'LC12 301'}))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
