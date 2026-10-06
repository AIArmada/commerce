import csv, os, sys
# Niger gate. 4-digit box-only postcode system (nothing area-mappable).
# M4 revisit: verify-only, zero data changes.
# Run from repo root: python3 docs/agents/audit/gate_ne.py
A = './packages/addressing/resources/geography/niger-address-areas.csv'
C = './packages/addressing/resources/geography/niger-postal-codes.csv'
L = './packages/addressing/resources/geography/niger-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-79', len(rows) == 79, str(len(rows)))
check('regions-7', sum(1 for r in rows if r['type'] == 'region') == 7)
check('urban-community-1', sum(1 for r in rows if r['type'] == 'urban_community') == 1)
check('departments-66', sum(1 for r in rows if r['type'] == 'department') == 66)
check('communes-5', sum(1 for r in rows if r['type'] == 'commune') == 5)
# ISO 3166-2:NE codes (1-7 regions, 8 urban community).
iso = {'ne:region:agadez': ('Agadez', '1'),
 'ne:region:diffa': ('Diffa', '2'),
 'ne:region:dosso': ('Dosso', '3'),
 'ne:region:maradi': ('Maradi', '4'),
 'ne:region:tahoua': ('Tahoua', '5'),
 'ne:region:tillaberi': ('Tillabéri', '6'),
 'ne:region:zinder': ('Zinder', '7'),
 'ne:urban_community:niamey': ('Niamey', '8')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full L1 -> L2 table per Departments-of-Niger oracle region lists
# (66 incl. the Maradi/Tahoua/Zinder city departments; the page lead
# prose still says 63, the lists themselves total 66) plus the Niamey
# article's five communes (I-V).
table = {'ne:region:agadez': ['Aderbissina', 'Arlit', 'Bilma', 'Iferouane',
    'In-Gall', 'Tchirozerine'],
 'ne:region:diffa': ['Bosso', 'Diffa', 'Goudoumaria', 'Maine-soroa',
    "N'Gourti", "N'guigmi"],
 'ne:region:dosso': ['Boboye', 'Dioundiou', 'Dogondoutchi', 'Dosso',
    'Falmey', 'Gaya', 'Loga', 'Tibiri'],
 'ne:region:maradi': ['Aguie', 'Bermo', 'Dakoro', 'Gazaoua',
    'Guidan Roumdji', 'Madarounfa', 'Maradi City', 'Mayahi', 'Tessaoua'],
 'ne:region:tahoua': ['Abalak', 'Bagaroua', 'Bkonni', 'Bouza', 'Illela',
    'Keita', 'Madaoua', 'Malbaza', 'Tahoua', 'Tahoua City', 'Tassara',
    'Tchin-Tabaraden', 'Tillia'],
 'ne:region:tillaberi': ['Abala', 'Ayourou', 'Balléyara', 'Banibangou',
    'Bankilaré', 'Filingue', 'Gothèye', 'Kollo', 'Ouallam', 'Say', 'Téra',
    'Tillabéri', 'Torodi'],
 'ne:region:zinder': ['Belbédji', 'Damagaram Takaya', 'Dungass', 'Goure',
    'Magaria', 'Matameye', 'Mirriah', 'Takeita', 'Tanout', 'Tesker',
    'Zinder City'],
 'ne:urban_community:niamey': ['Niamey I', 'Niamey II', 'Niamey III',
    'Niamey IV', 'Niamey V']}
ok = True
for par, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par)
    if have != sorted(names):
        print('FAIL parent', par, have); fails.append(f'parent {par}'); ok = False
    check(f'{par.split(":")[-1]}-{len(names)}', len(have) == len(names), str(len(have)))
if ok: print('PASS all 8 L1 mappings (66 departments + 5 communes)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none (nothing to import): UPU nerEn (03/2005) gives 4
# digits left of the locality but 'Deliveries are made to P.O.
# Boxes only' (example 8001 NIAMEY); GeoNames has no NE postal
# dump (404); directory evidence shows 800x codes routing to
# Niamey post offices (plateau/aeroport/rive droite/RP), i.e.
# sub-city box routing, not department geography.
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
