import csv, os, sys
# San-Marino gate. B3 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_sm.py
A = './packages/addressing/resources/geography/san-marino-address-areas.csv'
C = './packages/addressing/resources/geography/san-marino-postal-codes.csv'
L = './packages/addressing/resources/geography/san-marino-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-9', len(rows) == 9, str(len(rows)))
# ISO 3166-2:SM: nine castelli, exact code set (SM-07 Città di
# San Marino ships as San Marino).
iso = {'sm:municipality:acquaviva': ('Acquaviva', '01'),
       'sm:municipality:chiesanuova': ('Chiesanuova', '02'),
       'sm:municipality:domagnano': ('Domagnano', '03'),
       'sm:municipality:faetano': ('Faetano', '04'),
       'sm:municipality:fiorentino': ('Fiorentino', '05'),
       'sm:municipality:borgo-maggiore': ('Borgo Maggiore', '06'),
       'sm:municipality:san-marino': ('San Marino', '07'),
       'sm:municipality:montegiardino': ('Montegiardino', '08'),
       'sm:municipality:serravalle': ('Serravalle', '09')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'municipality' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
codes = [r['code'] for r in csv.DictReader(open(C, newline=''))]
links = list(csv.DictReader(open(L, newline='')))
# UPU smrEn (10/2010) full locality list: every curazia's code
# sits in its municipality (Dogana/Falciano/Rovereta/Galazzano/
# Fiorina 47891+47899 Serravalle; Ca' Rigo/Cailungo/San Giovanni/
# Valdragone/Ventoso 47893 Borgo; Casole/Murat/Santa Mustiola
# 47890 San Marino; Cerbaiola 47898 Montegiardino; Teglio 47894
# Chiesanuova; Torraccia 47895 Domagnano).
table = {'47890': 'sm:municipality:san-marino',
         '47891': 'sm:municipality:serravalle',
         '47892': 'sm:municipality:acquaviva',
         '47893': 'sm:municipality:borgo-maggiore',
         '47894': 'sm:municipality:chiesanuova',
         '47895': 'sm:municipality:domagnano',
         '47896': 'sm:municipality:faetano',
         '47897': 'sm:municipality:fiorentino',
         '47898': 'sm:municipality:montegiardino',
         '47899': 'sm:municipality:serravalle'}
check('codes-10', sorted(codes) == sorted(table), str(sorted(codes)))
check('links-10', len(links) == 10, str(len(links)))
bycode = {r['postcode']: r for r in links}
for pc, sid in table.items():
    r = bycode.get(pc)
    check(f'link-{pc}', bool(r) and r['area_source_id'] == sid
          and r['relationship_type'] == 'served_by'
          and r['is_primary'] == 'true', str(r))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
