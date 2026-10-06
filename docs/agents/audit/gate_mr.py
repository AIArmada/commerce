import csv, os, sys
# Mauritania gate. No postcode system. M4 revisit: verify-only, zero
# data changes. Run from repo root: python3 docs/agents/audit/gate_mr.py
A = './packages/addressing/resources/geography/mauritania-address-areas.csv'
C = './packages/addressing/resources/geography/mauritania-postal-codes.csv'
L = './packages/addressing/resources/geography/mauritania-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A, encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-78', len(rows) == 78, str(len(rows)))
check('regions-15', sum(1 for r in rows if r['type'] == 'region') == 15)
check('departments-63', sum(1 for r in rows if r['type'] == 'department') == 63)
# ISO 3166-2:MR codes. Display note: CSV 'Dakhlet Nouadhibou' drops
# the ISO conventional-name circumflex (Nouadhibou), matching the
# Regions/Departments oracle display; CSV 'Guidimaka' follows ISO
# against the departments-table link-text variant 'Guidimakha'.
iso = {'mr:region:hodh-ech-chargui': ('Hodh Ech Chargui', '01'),
 'mr:region:hodh-el-gharbi': ('Hodh El Gharbi', '02'),
 'mr:region:assaba': ('Assaba', '03'),
 'mr:region:gorgol': ('Gorgol', '04'),
 'mr:region:brakna': ('Brakna', '05'),
 'mr:region:trarza': ('Trarza', '06'),
 'mr:region:adrar': ('Adrar', '07'),
 'mr:region:dakhlet-nouadhibou': ('Dakhlet Nouadhibou', '08'),
 'mr:region:tagant': ('Tagant', '09'),
 'mr:region:guidimaka': ('Guidimaka', '10'),
 'mr:region:tiris-zemmour': ('Tiris Zemmour', '11'),
 'mr:region:inchiri': ('Inchiri', '12'),
 'mr:region:nouakchott-ouest': ('Nouakchott-Ouest', '13'),
 'mr:region:nouakchott-nord': ('Nouakchott-Nord', '14'),
 'mr:region:nouakchott-sud': ('Nouakchott-Sud', '15')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full region -> departments table per Departments-of-Mauritania
# oracle (63 departments; 2014 Nouakchott 3-way split: Nord 14,
# Ouest 13, Sud 15).
table = {'mr:region:adrar': ['Aoujeft', 'Atar', 'Chinguetti', 'Ouadane'],
 'mr:region:assaba': ['Barkéol', 'Boumdeid', 'Guerou', 'Kankossa', 'Kiffa'],
 'mr:region:brakna': ['Aleg', 'Bababé', 'Bogué', "M'Bagne", 'Magta Lahjar',
    'Male'],
 'mr:region:dakhlet-nouadhibou': ['Chami', 'Nouadhibou'],
 'mr:region:gorgol': ['Kaédi', 'Lexeiba', "M'Bout", 'Maghama', 'Monguel'],
 'mr:region:guidimaka': ['Ghabou', 'Ould Yengé', 'Sélibaby', 'Wompou'],
 'mr:region:hodh-ech-chargui': ['Adel Bagrou', 'Amourj', 'Bassiknou',
    'Djigueni', "N'Beiket Lehwach", 'Néma', 'Oualata', 'Timbédra'],
 'mr:region:hodh-el-gharbi': ['Aïoun', 'Koubenni', 'Tamchekett', 'Tintane',
    'Touil'],
 'mr:region:inchiri': ['Akjoujt', 'Bénichab'],
 'mr:region:nouakchott-nord': ['Dar Naïm', 'Teyarett', 'Toujounine'],
 'mr:region:nouakchott-ouest': ['Ksar', 'Sebkha', 'Tevragh Zeina'],
 'mr:region:nouakchott-sud': ['Arafat', 'El Mina', 'Riyad'],
 'mr:region:tagant': ['Moudjéria', 'Tichitt', 'Tidjikja'],
 'mr:region:tiris-zemmour': ['Bir Moghrein', "F'Déirick", 'Zouérate'],
 'mr:region:trarza': ['Boutilimit', 'Keur Macène', 'Méderdra', 'Ouad Naga',
    "R'Kiz", 'Rosso', 'Tékane']}
ok = True
for reg, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == reg)
    if have != sorted(names):
        print('FAIL region', reg, have); fails.append(f'region {reg}'); ok = False
    check(f'{reg.split(":")[-1]}-{len(names)}', len(have) == len(names), str(len(have)))
if ok: print('PASS all 15 region mappings (63 departments)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: UPU mrtEn (03/2005) shows a codeless B.P. address
# with no postcode section; Sep-2025 UPU list carries Mauritania
# on do-not-require; GeoNames has no MR postal dump (404).
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
