import csv, os, sys
# Bolivia (BO) gate. Pins the B13 rename pass: 9 departments + 112 provinces =
# 121 rows. Fixes vs pre-state (all WP-dept-table + Statoids or stronger):
# Campero→Narciso Campero, Murillo→Pedro Domingo Murillo, Atahuallpa→Sabaya
# (documented rename; EN article + ES WP + GeoNames 2025; Statoids pre-rename),
# Burnet→Burdett O'Connor (EN canonical redirect + ES infobox/body/category +
# person etymology), Pantaléon→Pantaleón Dalence, Tomas→Tomás Barrón,
# Sur→Sud Chichas/Lípez. Holds: Marbán, Loayza, Jaime Zudáñez, Azurduy,
# Bolívar, Sebastián Pagador, Manuel María Caballero, Obispo Santistevan
# (CSV+Statoids or CSV+WP-title over WP-display/Statoids-full). No postcode
# system: UPU bolEn profile (02/2026) codeless, UPU Sep-2025 list carries
# Bolivia on do-not-require, GeoNames BO.zip 404. Run from repo root:
# python3 docs/agents/audit/gate_bo.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/bolivia-address-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# --- EOL + trailing newline (raw bytes) ---
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-121', len(rows) == 121, str(len(rows)))
check('areas-unique-ids', len(byid) == len(rows))
check('areas-country-BO', all(r['country_code'] == 'BO' for r in rows))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
check('departments-9', len(l1) == 9 and all(r['type'] == 'department' and not r['parent_source_id'] for r in l1))
check('provinces-112', len(l2) == 112 and all(r['type'] == 'province' for r in l2), str(len(l2)))
check('l2-parents-valid', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in l2))
# ISO 3166-2:BO (Beni displayed 'Beni', ISO 'El Beni' — deliberate).
iso = {'bo:department:beni': ('Beni', 'B'), 'bo:department:cochabamba': ('Cochabamba', 'C'),
 'bo:department:chuquisaca': ('Chuquisaca', 'H'), 'bo:department:la-paz': ('La Paz', 'L'),
 'bo:department:pando': ('Pando', 'N'), 'bo:department:oruro': ('Oruro', 'O'),
 'bo:department:potosi': ('Potosí', 'P'), 'bo:department:santa-cruz': ('Santa Cruz', 'S'),
 'bo:department:tarija': ('Tarija', 'T')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['name'] == name and r['code'] == code, str(r))
# Full membership: WP 9 department tables + Statoids HASC + ES WP + GeoNames.
table = {'bo:department:beni': ['Cercado', 'Iténez', 'José Ballivián', 'Mamoré', 'Marbán', 'Moxos', 'Vaca Díez', 'Yacuma'],
 'bo:department:chuquisaca': ['Azurduy', 'Belisario Boeto', 'Hernando Siles', 'Jaime Zudáñez', 'Luis Calvo', 'Nor Cinti', 'Oropeza', 'Sud Cinti', 'Tomina', 'Yamparáez'],
 'bo:department:cochabamba': ['Arani', 'Arque', 'Ayopaya', 'Bolívar', 'Capinota', 'Carrasco', 'Cercado', 'Chapare', 'Esteban Arce', 'Germán Jordán', 'Mizque', 'Narciso Campero', 'Punata', 'Quillacollo', 'Tapacarí', 'Tiraque'],
 'bo:department:la-paz': ['Abel Iturralde', 'Aroma', 'Bautista Saavedra', 'Caranavi', 'Eliodoro Camacho', 'Franz Tamayo', 'Gualberto Villarroel', 'Ingavi', 'Inquisivi', 'José Manuel Pando', 'Larecaja', 'Loayza', 'Los Andes', 'Manco Kapac', 'Muñecas', 'Nor Yungas', 'Omasuyos', 'Pacajes', 'Pedro Domingo Murillo', 'Sud Yungas'],
 'bo:department:oruro': ['Carangas', 'Cercado', 'Eduardo Avaroa', 'Ladislao Cabrera', 'Litoral', 'Nor Carangas', 'Pantaleón Dalence', 'Poopó', 'Puerto de Mejillones', 'Sabaya', 'Sajama', 'San Pedro de Totora', 'Saucarí', 'Sebastián Pagador', 'Sud Carangas', 'Tomás Barrón'],
 'bo:department:pando': ['Abuná', 'Federico Román', 'Madre de Dios', 'Manuripi', 'Nicolás Suárez'],
 'bo:department:potosi': ['Alonso de Ibáñez', 'Antonio Quijarro', 'Bernardino Bilbao', 'Charcas', 'Chayanta', 'Cornelio Saavedra', 'Daniel Campos', 'Enrique Baldivieso', 'José María Linares', 'Modesto Omiste', 'Nor Chichas', 'Nor Lípez', 'Rafael Bustillo', 'Sud Chichas', 'Sud Lípez', 'Tomás Frías'],
 'bo:department:santa-cruz': ['Andrés Ibáñez', 'Chiquitos', 'Cordillera', 'Florida', 'Germán Busch', 'Guarayos', 'Ichilo', 'Ignacio Warnes', 'José Miguel de Velasco', 'Manuel María Caballero', 'Obispo Santistevan', 'Sara', 'Vallegrande', 'Ángel Sandoval', 'Ñuflo de Chávez'],
 'bo:department:tarija': ['Aniceto Arce', "Burdett O'Connor", 'Cercado', 'Eustaquio Méndez', 'Gran Chaco', 'José María Avilés']}
kids = {}
for r in rows:
    if r['level'] == '2': kids.setdefault(r['parent_source_id'], []).append(r['name'])
for sid, names in table.items():
    check(f'mem-{sid.split(":")[-1]}', sorted(kids.get(sid, [])) == sorted(names), str(sorted(set(kids.get(sid, [])) ^ set(names))))
# B13 rename pins + old-slug absence.
check('pin-narciso-campero', byid.get('bo:province:narciso-campero', {}).get('name') == 'Narciso Campero')
check('pin-pedro-murillo', byid.get('bo:province:pedro-domingo-murillo', {}).get('name') == 'Pedro Domingo Murillo')
check('pin-sabaya', byid.get('bo:province:sabaya', {}).get('parent_source_id') == 'bo:department:oruro')
check('pin-burdett', byid.get('bo:province:burdett-o-connor', {}).get('name') == "Burdett O'Connor")
check('pin-pantaleon', byid.get('bo:province:pantaleon-dalence', {}).get('name') == 'Pantaleón Dalence')
check('pin-tomas', byid.get('bo:province:tomas-barron', {}).get('name') == 'Tomás Barrón')
check('pin-sud-chichas', byid.get('bo:province:sud-chichas', {}).get('name') == 'Sud Chichas')
check('pin-sud-lipez', byid.get('bo:province:sud-lipez', {}).get('name') == 'Sud Lípez')
for old in ['bo:province:campero', 'bo:province:murillo', 'bo:province:atahuallpa',
            'bo:province:burnet-o-connor', 'bo:province:sur-chichas', 'bo:province:sur-lipez']:
    check(f'gone-{old.split(":")[-1]}', old not in byid)
# Hold pins (short/accented forms kept over WP-display or Statoids-full).
check('hold-marban', byid.get('bo:province:marban', {}).get('name') == 'Marbán')
check('hold-loayza', byid.get('bo:province:loayza', {}).get('name') == 'Loayza')
check('hold-santistevan', byid.get('bo:province:obispo-santistevan', {}).get('name') == 'Obispo Santistevan')
# No-postal pins: no BO postal CSVs exist.
check('no-postal-csvs', not os.path.exists(f'{ROOT}/packages/addressing/resources/geography/bolivia-postal-codes.csv')
    and not os.path.exists(f'{ROOT}/packages/addressing/resources/geography/bolivia-postal-code-areas.csv'))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
