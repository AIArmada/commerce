import csv, os, sys
# US-Minor-Outlying-Islands gate. B3 revisit: verify-only, zero
# data changes. Run from repo root: python3 docs/agents/audit/gate_um.py
A = './packages/addressing/resources/geography/us-minor-outlying-islands-address-areas.csv'
C = './packages/addressing/resources/geography/us-minor-outlying-islands-postal-codes.csv'
L = './packages/addressing/resources/geography/us-minor-outlying-islands-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, newline='')))
byid = {r['source_id']: r for r in rows}
check('areas-9', len(rows) == 9, str(len(rows)))
# ISO 3166-2:UM: nine islands (FIPS-derived numbers), exact set.
iso = {'um:island:baker-island': ('Baker Island', '81'),
       'um:island:howland-island': ('Howland Island', '84'),
       'um:island:jarvis-island': ('Jarvis Island', '86'),
       'um:island:johnston-atoll': ('Johnston Atoll', '67'),
       'um:island:kingman-reef': ('Kingman Reef', '89'),
       'um:island:midway-islands': ('Midway Islands', '71'),
       'um:island:navassa-island': ('Navassa Island', '76'),
       'um:island:palmyra-atoll': ('Palmyra Atoll', '95'),
       'um:island:wake-island': ('Wake Island', '79')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == 'island' and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
# UPU umiEn (03/2005): islands follow the US postal system (state
# code UM); no permanent population; no UM domestic system.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
