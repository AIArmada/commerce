import csv, os, sys
# Suriname gate. No postcode system. M3 revisit: verify-only, zero
# data changes. Run from repo root: python3 docs/agents/audit/gate_sr.py
A = './packages/addressing/resources/geography/suriname-address-areas.csv'
C = './packages/addressing/resources/geography/suriname-postal-codes.csv'
L = './packages/addressing/resources/geography/suriname-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-73', len(rows) == 73, str(len(rows)))
check('districts-10', sum(1 for r in rows if r['type'] == 'district') == 10)
check('resorts-63', sum(1 for r in rows if r['type'] == 'resort') == 63)
# ISO 3166-2:SR codes.
iso = {'sr:district:brokopondo': ('Brokopondo', 'BR'),
 'sr:district:commewijne': ('Commewijne', 'CM'),
 'sr:district:coronie': ('Coronie', 'CR'),
 'sr:district:marowijne': ('Marowijne', 'MA'),
 'sr:district:nickerie': ('Nickerie', 'NI'),
 'sr:district:para': ('Para', 'PR'),
 'sr:district:paramaribo': ('Paramaribo', 'PM'),
 'sr:district:saramacca': ('Saramacca', 'SA'),
 'sr:district:sipaliwini': ('Sipaliwini', 'SI'),
 'sr:district:wanica': ('Wanica', 'WA')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full district -> resorts table per Resorts-of-Suriname oracle (63).
table = {'sr:district:brokopondo': ['Brownsweg', 'Centrum', 'Klaaskreek',
    'Kwakoegron', 'Marshallkreek', 'Sarakreek'],
 'sr:district:commewijne': ['Alkmaar', 'Bakkie', 'Margaretha',
    'Meerzorg', 'Nieuw Amsterdam', 'Tamanredjo'],
 'sr:district:coronie': ['Johanna Maria', 'Totness', 'Welgelegen'],
 'sr:district:marowijne': ['Albina', 'Galibi', 'Moengo', 'Moengo Tapoe',
    'Patamacca', 'Wanhatti'],
 'sr:district:nickerie': ['Groot Henar', 'Nieuw Nickerie',
    'Oostelijke Polders', 'Wageningen', 'Westelijke Polders'],
 'sr:district:para': ['Bigi Poika', 'Carolina', 'Para Noord',
    'Para Oost', 'Para, Zuid'],
 'sr:district:paramaribo': ['Beekhuizen', 'Blauwgrond', 'Centrum',
    'Flora', 'Latour', 'Livorno', 'Munder', 'Pontbuiten', 'Rainville',
    'Tammenga', 'Weg naar Zee', 'Welgelegen'],
 'sr:district:saramacca': ['Calcutta', 'Groningen', 'Jarikaba',
    'Kampong Baroe', 'Tijgerkreek', 'Wayamboweg'],
 'sr:district:sipaliwini': ['Boven Coppename', 'Boven Saramacca',
    'Boven Suriname', 'Coeroeni', 'Kabalebo', 'Paramacca', 'Tapanahony'],
 'sr:district:wanica': ['De Nieuwe Grond', 'Domburg', 'Houttuin',
    'Koewarasan', 'Kwatta', 'Lelydorp', 'Saramacca Polder']}
ok = True
for dist, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == dist)
    if have != sorted(names):
        print('FAIL district', dist, have); fails.append(f'district {dist}'); ok = False
if ok: print('PASS all 10 district mappings (63 resorts)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: UPU surEn profile shows a codeless street address;
# Sep-2025 UPU list carries Suriname on do-not-require; GeoNames has
# no SR postal dump (404).
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
