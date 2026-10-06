import csv, os, sys
# Chad gate. No postcode system. M4 revisit: verify-only, zero
# data changes. Bundled tree is the 2018-era 63-department set
# (Ordonnance N°001/PR/2024 moved to 120 departments; kept as a
# scope note, not a fix — single-oracle signal only).
# Run from repo root: python3 docs/agents/audit/gate_td.py
A = './packages/addressing/resources/geography/chad-address-areas.csv'
C = './packages/addressing/resources/geography/chad-postal-codes.csv'
L = './packages/addressing/resources/geography/chad-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-86', len(rows) == 86, str(len(rows)))
check('provinces-23', sum(1 for r in rows if r['type'] == 'province') == 23)
check('departments-63', sum(1 for r in rows if r['type'] == 'department') == 63)
# ISO 3166-2:TD codes (2018 23-province restructure; OBP
# 2020-11-24 recategorized regions to provinces). Deliberate
# display deviations from ISO fr names: Bahr el Gazel (Ghazal),
# Hadjer-Lamis (Hadjer Lamis), Logone Occidental/Oriental
# (hyphenated), Mayo-Kebbi Est/Ouest (Mayo-Kebbi-Est/-Ouest),
# N'Djamena (Ville de Ndjamena).
iso = {'td:province:bahr-el-gazel': ('Bahr el Gazel', 'BG'),
 'td:province:batha': ('Batha', 'BA'),
 'td:province:borkou': ('Borkou', 'BO'),
 'td:province:chari-baguirmi': ('Chari-Baguirmi', 'CB'),
 'td:province:ennedi-est': ('Ennedi-Est', 'EE'),
 'td:province:ennedi-ouest': ('Ennedi-Ouest', 'EO'),
 'td:province:guera': ('Guéra', 'GR'),
 'td:province:hadjer-lamis': ('Hadjer-Lamis', 'HL'),
 'td:province:kanem': ('Kanem', 'KA'),
 'td:province:lac': ('Lac', 'LC'),
 'td:province:logone-occidental': ('Logone Occidental', 'LO'),
 'td:province:logone-oriental': ('Logone Oriental', 'LR'),
 'td:province:mandoul': ('Mandoul', 'MA'),
 'td:province:mayo-kebbi-est': ('Mayo-Kebbi Est', 'ME'),
 'td:province:mayo-kebbi-ouest': ('Mayo-Kebbi Ouest', 'MO'),
 'td:province:moyen-chari': ('Moyen-Chari', 'MC'),
 'td:province:ndjamena': ("N'Djamena", 'ND'),
 'td:province:ouaddai': ('Ouaddaï', 'OD'),
 'td:province:salamat': ('Salamat', 'SA'),
 'td:province:sila': ('Sila', 'SI'),
 'td:province:tandjile': ('Tandjilé', 'TA'),
 'td:province:tibesti': ('Tibesti', 'TI'),
 'td:province:wadi-fira': ('Wadi Fira', 'WF')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full province -> departments table per the pre-2024
# Departments-of-Chad oracle (rev 2024-05-03, 2018-era set).
# Note: that revision's intro says "61 departments" but its own
# grouped tables list 63. N'Djamena is terminal (10
# arrondissements, no departments) per the same oracle.
# M4 fix: 'Bahr El Gazel Sud' -> 'Bahr el Gazel Sud' (live
# oracle lowercase + sibling Nord lowercase = 2 signals).
table = {'td:province:bahr-el-gazel': ['Bahr el Gazel Nord',
    'Bahr el Gazel Sud'],
 'td:province:batha': ['Batha Est', 'Batha Ouest', 'Fitri'],
 'td:province:borkou': ['Borkou', 'Borkou Yala'],
 'td:province:chari-baguirmi': ['Baguirmi', 'Chari', 'Loug Chari'],
 'td:province:ennedi-est': ['Am-Djarass', 'Wadi Hawar'],
 'td:province:ennedi-ouest': ['Fada', 'Mourtcha'],
 'td:province:guera': ['Abtouyour', 'Barh Signaka', 'Guéra',
    'Mangalmé'],
 'td:province:hadjer-lamis': ['Dababa', 'Dagana',
    'Haraze Al Biar'],
 'td:province:kanem': ['Kanem', 'Nord Kanem', 'Wadi Bissam'],
 'td:province:lac': ['Mamdi', 'Wayi'],
 'td:province:logone-occidental': ['Dodjé', 'Guéni', 'Lac Wey',
    'Ngourkosso'],
 'td:province:logone-oriental': ['Kouh-Est', 'Kouh-Ouest',
    'La Nya', 'La Nya Pendé', 'La Pendé', 'Monts de Lam'],
 'td:province:mandoul': ['Barh Sara', 'Mandoul Occidental',
    'Mandoul Oriental'],
 'td:province:mayo-kebbi-est': ['Kabbia', 'Mayo-Boneye',
    'Mayo-Lémié', 'Mont Illi'],
 'td:province:mayo-kebbi-ouest': ['Lac Léré', 'Mayo-Dallah'],
 'td:province:moyen-chari': ['Barh Köh', 'Grande Sido',
    'Lac Iro'],
 'td:province:ouaddai': ['Abdi', 'Assoungha', 'Ouara'],
 'td:province:salamat': ['Aboudeïa', 'Barh Azoum',
    'Haraze-Mangueigne'],
 'td:province:sila': ['Djourf Al Ahmar', 'Kimiti'],
 'td:province:tandjile': ['Tandjilé Est', 'Tandjilé Ouest'],
 'td:province:tibesti': ['Tibesti Est', 'Tibesti Ouest'],
 'td:province:wadi-fira': ['Biltine', 'Dar Tama', 'Kobé']}
ok = True
for prov, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == prov)
    if have != sorted(names):
        print('FAIL province', prov, have); fails.append(f'province {prov}'); ok = False
if ok: print('PASS all 22 settled province mappings (63 departments)')
check('ndjamena-terminal', not [r for r in rows
      if r['parent_source_id'] == 'td:province:ndjamena'])
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: UPU tcdEn profile (09/2004) shows a codeless B.P.
# address; Sep-2025 UPU list carries Chad on do-not-require;
# GeoNames has no TD postal dump (404); List of postal codes
# reads "no codes".
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
