import csv, os, sys
# Cameroon gate. No postcode system. M3 revisit: verify-only, zero
# data changes. Run from repo root: python3 docs/agents/audit/gate_cm.py
A = './packages/addressing/resources/geography/cameroon-address-areas.csv'
C = './packages/addressing/resources/geography/cameroon-postal-codes.csv'
L = './packages/addressing/resources/geography/cameroon-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-68', len(rows) == 68, str(len(rows)))
check('regions-10', sum(1 for r in rows if r['type'] == 'region') == 10)
check('departments-58', sum(1 for r in rows if r['type'] == 'department') == 58)
# ISO 3166-2:CM codes (Adamaoua/North-West/South-West hyphen variants
# pinned in provider English: Adamawa, Northwest, Southwest).
iso = {'cm:region:adamawa': ('Adamawa', 'AD'), 'cm:region:centre': ('Centre', 'CE'),
 'cm:region:far-north': ('Far North', 'EN'), 'cm:region:east': ('East', 'ES'),
 'cm:region:littoral': ('Littoral', 'LT'), 'cm:region:north': ('North', 'NO'),
 'cm:region:northwest': ('Northwest', 'NW'), 'cm:region:west': ('West', 'OU'),
 'cm:region:south': ('South', 'SU'), 'cm:region:southwest': ('Southwest', 'SW')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full region -> departments table per Departments-of-Cameroon oracle.
table = {'cm:region:adamawa': ['Djérem', 'Faro-et-Déo', 'Mayo-Banyo',
    'Mbéré', 'Vina'],
 'cm:region:centre': ['Haute-Sanaga', 'Lekié', 'Mbam-et-Inoubou',
    'Mbam-et-Kim', 'Mfoundi', 'Méfou-et-Afamba', 'Méfou-et-Akono',
    'Nyong-et-Kéllé', 'Nyong-et-Mfoumou', "Nyong-et-So'o"],
 'cm:region:far-north': ['Diamaré', 'Logone-et-Chari', 'Mayo-Danay',
    'Mayo-Kani', 'Mayo-Sava', 'Mayo-Tsanaga'],
 'cm:region:east': ['Boumba-et-Ngoko', 'Haut-Nyong', 'Kadey',
    'Lom-et-Djerem'],
 'cm:region:littoral': ['Moungo', 'Nkam', 'Sanaga-Maritime', 'Wouri'],
 'cm:region:north': ['Bénoué', 'Faro', 'Mayo-Louti', 'Mayo-Rey'],
 'cm:region:northwest': ['Boyo', 'Bui', 'Donga-Mantung', 'Menchum',
    'Mezam', 'Momo', 'Ngo-ketunjia'],
 'cm:region:west': ['Bamboutos', 'Haut-Nkam', 'Hauts-Plateaux',
    'Koung-Khi', 'Menoua', 'Mifi', 'Ndé', 'Noun'],
 'cm:region:south': ['Dja-et-Lobo', 'Mvila', 'Océan', 'Vallée-du-Ntem'],
 'cm:region:southwest': ['Fako', 'Koupé-Manengouba', 'Lebialem',
    'Manyu', 'Meme', 'Ndian']}
ok = True
for reg, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == reg)
    if have != sorted(names):
        print('FAIL region', reg, have); fails.append(f'region {reg}'); ok = False
if ok: print('PASS all 10 region mappings (58 departments)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: UPU cmrEn (07/2002) shows a codeless B.P. address;
# Sep-2025 UPU list carries Cameroon on do-not-require; GeoNames has
# no CM postal dump (404).
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
