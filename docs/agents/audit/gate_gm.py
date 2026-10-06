import csv, os, sys
# Gambia gate. No postcode system. M2 revisit: verify-only, zero data
# changes. Run from repo root: python3 docs/agents/audit/gate_gm.py
A = './packages/addressing/resources/geography/gambia-address-areas.csv'
C = './packages/addressing/resources/geography/gambia-postal-codes.csv'
L = './packages/addressing/resources/geography/gambia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-49', len(rows) == 49, str(len(rows)))
check('cities-2', sum(1 for r in rows if r['type'] == 'city') == 2)
check('regions-5', sum(1 for r in rows if r['type'] == 'region') == 5)
check('districts-42', sum(1 for r in rows if r['type'] == 'district') == 42)
# ISO 3166-2:GM city/division letters + Kanifing municipality (K).
codes = {'gm:city:banjul': ('Banjul', 'B'), 'gm:city:kanifing': ('Kanifing', 'K'),
 'gm:region:central-river': ('Central River', 'M'),
 'gm:region:lower-river': ('Lower River', 'L'),
 'gm:region:north-bank': ('North Bank', 'N'),
 'gm:region:upper-river': ('Upper River', 'U'),
 'gm:region:west-coast': ('West Coast', 'W')}
for sid, (name, code) in codes.items():
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full district table per Subdivisions-of-the-Gambia oracle (districts
# listed per LGA; Basse Santa Su -> Upper River, Brikama -> West Coast,
# Janjanbureh + Kuntaur -> Central River, Kerewan -> North Bank,
# Mansa Konko -> Lower River).
table = {'gm:city:banjul': ['Banjul Central', 'Banjul North', 'Banjul South'],
 'gm:region:central-river': ['Fulladu West', 'Janjanbureh', 'Lower Saloum',
    'Niamina Dankunku', 'Niamina East', 'Niamina West', 'Niani', 'Nianija',
    'Sami', 'Upper Saloum'],
 'gm:region:lower-river': ['Jarra Central', 'Jarra East', 'Jarra West',
    'Kiang Central', 'Kiang East', 'Kiang West'],
 'gm:region:north-bank': ['Central Baddibu', 'Illiasa', 'Jokadu',
    'Lower Baddibu', 'Lower Niumi', 'Sabach Sanjal', 'Upper Niumi'],
 'gm:region:upper-river': ['Basse Fulladu East', 'Jimara', 'Kantora',
    'Sandu', 'Tumana', 'Wuli East', 'Wuli West'],
 'gm:region:west-coast': ['Foni Bintang-Karenai', 'Foni Bondali',
    'Foni Brefet', 'Foni Jarrol', 'Foni Kansala', 'Kombo Central',
    'Kombo East', 'Kombo North', 'Kombo South']}
ok = True
for par, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par)
    if have != sorted(names):
        print('FAIL parent', par, have); fails.append(f'parent {par}'); ok = False
if ok: print('PASS all 6 district mappings (42 districts)')
# Kanifing is a single-district municipality; the degenerate self-named
# district is deliberately not bundled (Kanifing terminal by design).
kids = [r for r in rows if r['parent_source_id'] == 'gm:city:kanifing']
check('kanifing-terminal', not kids, str(kids[:2]))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: UPU gmbEn (07/2002) shows a codeless address; Sep-2025
# UPU list puts Gambia on do-not-require; GeoNames has no GM postal
# dump (404).
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
