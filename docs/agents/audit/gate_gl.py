import csv, os, sys
# Greenland gate. B1 revisit: filled 3985 Nerlerit Inaat /
# Constable Pynt -> Sermersooq (27->28; Mittarfeqarfiit official
# "3985 Constable Pynt" addressed usage + zipcodehere + zirinsky
# + postalpin mirrors; airport sits in Sermersooq per its
# article). Run from repo root: python3 docs/agents/audit/gate_gl.py
A = './packages/addressing/resources/geography/greenland-address-areas.csv'
C = './packages/addressing/resources/geography/greenland-postal-codes.csv'
L = './packages/addressing/resources/geography/greenland-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-5', len(rows) == 5, str(len(rows)))
# ISO 3166-2:GL: five municipalities; the National Park and
# Pituffik Space Base are unincorporated and not listed.
iso = {'gl:municipality:avannaata': ('Avannaata', 'AV'),
       'gl:municipality:kujalleq': ('Kujalleq', 'KU'),
       'gl:municipality:qeqertalik': ('Qeqertalik', 'QT'),
       'gl:municipality:qeqqata': ('Qeqqata', 'QE'),
       'gl:municipality:sermersooq': ('Sermersooq', 'SM')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'municipality' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
# Code->town per the GeoNames GL postal dump; town->municipality per
# the en-wiki towns list + individual town articles (Nuussuaq and
# Qaarsut in Avannaata, Kangilinnguit in Sermersooq, Ikerasassuaq /
# Prince Christian Sound in the far south = Kujalleq). 3970 Pituffik
# is an unincorporated enclave surrounded by Avannaata, kept on the
# enclosing municipality as served_by. Station codes outside the
# municipal system stay out: 3972 Station Nord + 3982 Mestersvig +
# 3984 Danmarkshavn (all Northeast Greenland National Park,
# unincorporated) and 2412 (Santa novelty with North-Pole coords).
# GeoNames misses live 3984/3985; 3985 fills (Sermersooq).
table = {'3900': 'gl:municipality:sermersooq',
         '3905': 'gl:municipality:avannaata',
         '3910': 'gl:municipality:qeqqata',
         '3911': 'gl:municipality:qeqqata',
         '3912': 'gl:municipality:qeqqata',
         '3913': 'gl:municipality:sermersooq',
         '3915': 'gl:municipality:sermersooq',
         '3919': 'gl:municipality:kujalleq',
         '3920': 'gl:municipality:kujalleq',
         '3921': 'gl:municipality:kujalleq',
         '3922': 'gl:municipality:kujalleq',
         '3923': 'gl:municipality:kujalleq',
         '3924': 'gl:municipality:kujalleq',
         '3930': 'gl:municipality:sermersooq',
         '3932': 'gl:municipality:sermersooq',
         '3940': 'gl:municipality:sermersooq',
         '3950': 'gl:municipality:qeqertalik',
         '3951': 'gl:municipality:qeqertalik',
         '3952': 'gl:municipality:avannaata',
         '3953': 'gl:municipality:qeqertalik',
         '3955': 'gl:municipality:qeqertalik',
         '3961': 'gl:municipality:avannaata',
         '3962': 'gl:municipality:avannaata',
         '3964': 'gl:municipality:avannaata',
         '3970': 'gl:municipality:avannaata',
         '3971': 'gl:municipality:avannaata',
         '3980': 'gl:municipality:sermersooq',
         '3985': 'gl:municipality:sermersooq'}
check('codes-28', sorted(codes) == sorted(table), str(sorted(set(codes) ^ set(table))))
check('links-28', len(links) == 28, str(len(links)))
bycode = {r['postcode']: r for r in links}
for pc, sid in table.items():
    r = bycode.get(pc)
    check(f'link-{pc}', bool(r) and r['area_source_id'] == sid
          and r['relationship_type'] == 'served_by'
          and r['is_primary'] == 'true', str(r))
check('out-of-scope-absent',
      not (set(codes) & {'3972', '3982', '3984', '2412'}),
      str(sorted(set(codes) & {'3972', '3982', '3984', '2412'})))
check('covered-5-5', {r['area_source_id'] for r in links} == set(iso))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
