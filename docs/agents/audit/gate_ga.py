import csv, os, sys
# Gabon gate. No postcode system (the UPU "99" format entry is the
# 2-digit delivery-office code, routing not a postcode). M2 revisit:
# verify-only, zero data changes. Run from repo root:
# python3 docs/agents/audit/gate_ga.py
A = './packages/addressing/resources/geography/gabon-address-areas.csv'
C = './packages/addressing/resources/geography/gabon-postal-codes.csv'
L = './packages/addressing/resources/geography/gabon-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-58', len(rows) == 58, str(len(rows)))
check('provinces-9', sum(1 for r in rows if r['type'] == 'province') == 9)
check('departments-49', sum(1 for r in rows if r['type'] == 'department') == 49)
# ISO 3166-2:GA province digits.
iso = {'ga:province:estuaire': ('Estuaire', '1'),
 'ga:province:haut-ogooue': ('Haut-Ogooué', '2'),
 'ga:province:moyen-ogooue': ('Moyen-Ogooué', '3'),
 'ga:province:ngounie': ('Ngounié', '4'),
 'ga:province:nyanga': ('Nyanga', '5'),
 'ga:province:ogooue-ivindo': ('Ogooué-Ivindo', '6'),
 'ga:province:ogooue-lolo': ('Ogooué-Lolo', '7'),
 'ga:province:ogooue-maritime': ('Ogooué-Maritime', '8'),
 'ga:province:woleu-ntem': ('Woleu-Ntem', '9')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full province -> departments table per Departments-of-Gabon oracle
# (49 departments; Cap Estérias deleted 2013, correctly absent;
# Leboumbi-Leyou uses the corrected display spelling).
table = {'ga:province:estuaire': ['Komo', 'Komo-Mondah', 'Komo-Océan',
    'Libreville', 'Noya'],
 'ga:province:haut-ogooue': ['Bayi-Brikolo', 'Djoue', 'Djououri-Aguilli',
    'Leboumbi-Leyou', 'Lekoko', 'Lekoni-Lekori', 'Lékabi-Léwolo',
    'Mpassa', 'Ogooué-Létili', 'Plateaux', 'Sebe-Brikolo'],
 'ga:province:moyen-ogooue': ['Abanga-Bigne', 'Ogooué et des Lacs'],
 'ga:province:ngounie': ['Boumi-Louetsi', 'Dola', 'Douya-Onoy',
    'Louetsi-Bibaka', 'Louetsi-Wano', 'Mougalaba', 'Ndolou', 'Ogoulou',
    'Tsamba-Magotsi'],
 'ga:province:nyanga': ['Basse-Banio', 'Douigni', 'Doutsila',
    'Haute-Banio', 'Mongo', 'Mougoutsi'],
 'ga:province:ogooue-ivindo': ['Ivindo', 'Lope', 'Mvoung', 'Zadie'],
 'ga:province:ogooue-lolo': ['Lolo-Bouenguidi', 'Lombo-Bouenguidi',
    'Mouloundou', 'Offoué-Onoye'],
 'ga:province:ogooue-maritime': ['Bendje', 'Etimboue', 'Ndougou'],
 'ga:province:woleu-ntem': ['Haut-Komo', 'Haut-Ntem', 'Ntem', 'Okano',
    'Woleu']}
ok = True
for prov, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == prov)
    if have != sorted(names):
        print('FAIL province', prov, have); fails.append(f'province {prov}'); ok = False
if ok: print('PASS all 9 province mappings (49 departments)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: UPU gabEn (07/2002) shows the 2-digit code is a
# delivery-office code (same shape as CI); Sep-2025 UPU list puts
# Gabon on do-not-require; GeoNames has no GA postal dump (404).
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
