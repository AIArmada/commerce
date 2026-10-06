import csv, os, sys
A = './packages/addressing/resources/geography/sierra-leone-address-areas.csv'
C = './packages/addressing/resources/geography/sierra-leone-postal-codes.csv'
L = './packages/addressing/resources/geography/sierra-leone-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-21', len(rows) == 21, str(len(rows)))
check('provinces-4', sum(1 for r in rows if r['type'] == 'province') == 4)
check('area-1', sum(1 for r in rows if r['type'] == 'area') == 1)
check('districts-16', sum(1 for r in rows if r['type'] == 'district') == 16)
# ISO 3166-2:SL: SL-E/NW/N/S/W. 'North Western' is the ISO name;
# 'North West' (Stats SL/display) is a known shorthand, not a fix.
for sid, name, code in [('sl:province:eastern', 'Eastern', 'E'),
        ('sl:province:north-western', 'North Western', 'NW'),
        ('sl:province:northern', 'Northern', 'N'),
        ('sl:province:southern', 'Southern', 'S'),
        ('sl:area:western', 'Western', 'W')]:
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['name'] == name and r['code'] == code and r['level'] == '1', str(r))
# Full parent table: Districts of Sierra Leone + Stats SL MTPHC pilot.
table = {'sl:province:eastern': ['kailahun', 'kenema', 'kono'],
 'sl:province:northern': ['bombali', 'falaba', 'koinadugu', 'tonkolili'],
 'sl:province:north-western': ['kambia', 'karene', 'port-loko'],
 'sl:province:southern': ['bo', 'bonthe', 'moyamba', 'pujehun'],
 'sl:area:western': ['western-rural', 'western-urban']}
ok = True
for p, kids in table.items():
    have = sorted(r['source_id'].split(':')[-1] for r in rows if r['parent_source_id'] == p)
    if have != sorted(kids):
        print('FAIL parent', p, have); fails.append(f'parent {p}'); ok = False
if ok: print('PASS all 5 parent mappings')
names = {'kailahun': 'Kailahun', 'kenema': 'Kenema', 'kono': 'Kono', 'bombali': 'Bombali',
 'falaba': 'Falaba', 'koinadugu': 'Koinadugu', 'tonkolili': 'Tonkolili', 'kambia': 'Kambia',
 'karene': 'Karene', 'port-loko': 'Port Loko', 'bo': 'Bo', 'bonthe': 'Bonthe',
 'moyamba': 'Moyamba', 'pujehun': 'Pujehun', 'western-rural': 'Western Rural',
 'western-urban': 'Western Urban'}
bad = [s for s, n in names.items() if byid.get(f'sl:district:{s}', {}).get('name') != n]
check('district-names-16', not bad, str(bad))
# Deliberate: Western short forms match the Districts-table display labels;
# Stats SL long forms 'Western Area Rural/Urban' ship as alternative areaNames.
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
check('no-postal-codes-file', not os.path.exists(C))
check('no-postal-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
