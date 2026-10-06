import csv, os, sys
# Equatorial-Guinea gate. B3 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_gq.py
A = './packages/addressing/resources/geography/equatorial-guinea-address-areas.csv'
C = './packages/addressing/resources/geography/equatorial-guinea-postal-codes.csv'
L = './packages/addressing/resources/geography/equatorial-guinea-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-10', len(rows) == 10, str(len(rows)))
# ISO 3166-2:GQ: two regions (C/I) + eight provinces including
# DJ Djibloho (2017 province), exact set.
check('regions-2', sum(1 for r in rows if r['type'] == 'region') == 2)
iso = {'gq:region:rio-muni': ('Río Muni', 'C'),
       'gq:region:insular': ('Insular', 'I'),
       'gq:province:annobon': ('Annobón', 'AN'),
       'gq:province:bioko-norte': ('Bioko Norte', 'BN'),
       'gq:province:bioko-sur': ('Bioko Sur', 'BS'),
       'gq:province:centro-sur': ('Centro Sur', 'CS'),
       'gq:province:djibloho': ('Djibloho', 'DJ'),
       'gq:province:kie-ntem': ('Kié-Ntem', 'KN'),
       'gq:province:litoral': ('Litoral', 'LI'),
       'gq:province:wele-nzas': ('Wele-Nzas', 'WN')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code, str(r))
ins = 'gq:region:insular'
muni = 'gq:region:rio-muni'
check('insular-3', sorted(r['source_id'] for r in rows if r['parent_source_id'] == ins)
      == ['gq:province:annobon', 'gq:province:bioko-norte', 'gq:province:bioko-sur'])
check('muni-5', sorted(r['source_id'] for r in rows if r['parent_source_id'] == muni)
      == ['gq:province:centro-sur', 'gq:province:djibloho', 'gq:province:kie-ntem',
          'gq:province:litoral', 'gq:province:wele-nzas'])
# UPU gnqEn (03/2023): province + locality addressing, no postcode.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
