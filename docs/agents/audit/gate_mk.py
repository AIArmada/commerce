import csv, re, sys
# North Macedonia geography gate. Pins the CORRECTED B10 state (80
# municipalities + 326 codes / 326 links, with the 1128 airport retarget
# petrovec -> ilinden). Run from repo root:
# python3 docs/agents/audit/gate_mk.py
A = './packages/addressing/resources/geography/north-macedonia-address-areas.csv'
C = './packages/addressing/resources/geography/north-macedonia-postal-codes.csv'
L = './packages/addressing/resources/geography/north-macedonia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: WP Municipalities of North Macedonia (2013 roster) + ISO 3166-2:MK ---
check('areas-80', len(rows) == 80, str(len(rows)))
check('all-municipality-l1', all(r['type'] == 'municipality' and r['level'] == '1' for r in rows))
check('no-parents', all(r['parent_source_id'] == '' for r in rows))
# Full (name, code) roster == ISO 3166-2:MK current codes; WP list uses the
# same 80 names (ISO romanizes Debrca / Mavrovo i Rostuse; repo keeps the WP
# spellings Debarca / Mavrovo and Rostusa per stability).
check('iso-roster', sorted((r['name'], r['code']) for r in rows) ==
      [('Aerodrom', '801'), ('Aračinovo', '802'), ('Berovo', '201'),
       ('Bitola', '501'), ('Bogdanci', '401'), ('Bogovinje', '601'),
       ('Bosilovo', '402'), ('Brvenica', '602'), ('Butel', '803'),
       ('Centar', '814'), ('Centar Župa', '313'), ('Debar', '303'),
       ('Debarca', '304'), ('Delčevo', '203'), ('Demir Hisar', '502'),
       ('Demir Kapija', '103'), ('Dojran', '406'), ('Dolneni', '503'),
       ('Gazi Baba', '804'), ('Gevgelija', '405'), ('Gjorče Petrov', '805'),
       ('Gostivar', '604'), ('Gradsko', '102'), ('Ilinden', '807'),
       ('Jegunovce', '606'), ('Karbinci', '205'), ('Karpoš', '808'),
       ('Kavadarci', '104'), ('Kisela Voda', '809'), ('Kičevo', '307'),
       ('Konče', '407'), ('Kočani', '206'), ('Kratovo', '701'),
       ('Kriva Palanka', '702'), ('Krivogaštani', '504'), ('Kruševo', '505'),
       ('Kumanovo', '703'), ('Lipkovo', '704'), ('Lozovo', '105'),
       ('Makedonska Kamenica', '207'), ('Makedonski Brod', '308'),
       ('Mavrovo and Rostuša', '607'), ('Mogila', '506'),
       ('Negotino', '106'), ('Novaci', '507'), ('Novo Selo', '408'),
       ('Ohrid', '310'), ('Pehčevo', '208'), ('Petrovec', '810'),
       ('Plasnica', '311'), ('Prilep', '508'), ('Probištip', '209'),
       ('Radoviš', '409'), ('Rankovce', '705'), ('Resen', '509'),
       ('Rosoman', '107'), ('Saraj', '811'), ('Sopište', '812'),
       ('Staro Nagoričane', '706'), ('Struga', '312'), ('Strumica', '410'),
       ('Studeničani', '813'), ('Sveti Nikole', '108'), ('Tearce', '608'),
       ('Tetovo', '609'), ('Valandovo', '403'), ('Vasilevo', '404'),
       ('Veles', '101'), ('Vevčani', '301'), ('Vinica', '202'),
       ('Vrapčište', '603'), ('Zelenikovo', '806'), ('Zrnovci', '204'),
       ('Čair', '815'), ('Čaška', '109'),
       ('Češinovo-Obleševo', '210'), ('Čučer-Sandevo', '816'),
       ('Štip', '211'), ('Šuto Orizari', '817'), ('Želino', '605')])
# 2013 merges: Kicevo absorbed Drugovo/Zajas/Oslomej/Vranestica; the four
# former municipalities must be absent.
names = {r['name'] for r in rows}
check('kicevo-307', byid['mk:municipality:kicevo']['code'] == '307')
check('no-pre2013', not ({'Drugovo', 'Zajas', 'Oslomej', 'Vraneštica'} & names))
# Skopje city municipalities: 10 (Greater Skopje), all L1.
skopje = {'aerodrom', 'butel', 'cair', 'centar', 'gazi-baba', 'gjorce-petrov',
          'karpos', 'kisela-voda', 'saraj', 'suto-orizari'}
check('skopje-10', skopje <= {r['source_id'].split(':')[-1] for r in rows})
# --- counts (corrected state; 1128 moved, counts unchanged) ---
check('codes-326', len(codes) == 326, str(len(codes)))
check('links-326', len(links) == 326, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{4}$', c['code'])]
check('code-format', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
from collections import Counter
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 326 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('link-code-equality', {l['postcode'] for l in links} == {c['code'] for c in codes})
# --- per-municipality primary counts (corrected; 80/80 covered) ---
expect = {
         'aerodrom': 4,
         'aracinovo': 1,
         'berovo': 4,
         'bitola': 17,
         'bogdanci': 2,
         'bogovinje': 5,
         'bosilovo': 3,
         'brvenica': 2,
         'butel': 3,
         'cair': 3,
         'caska': 3,
         'centar': 11,
         'centar-zupa': 1,
         'cesinovo-oblesevo': 2,
         'cucer-sandevo': 2,
         'debar': 2,
         'debarca': 4,
         'delcevo': 3,
         'demir-hisar': 3,
         'demir-kapija': 1,
         'dojran': 3,
         'dolneni': 5,
         'gazi-baba': 7,
         'gevgelija': 6,
         'gjorce-petrov': 2,
         'gostivar': 9,
         'gradsko': 1,
         'ilinden': 3,
         'jegunovce': 4,
         'karbinci': 2,
         'karpos': 6,
         'kavadarci': 8,
         'kicevo': 11,
         'kisela-voda': 6,
         'kocani': 5,
         'konce': 1,
         'kratovo': 2,
         'kriva-palanka': 5,
         'krivogastani': 2,
         'krusevo': 1,
         'kumanovo': 15,
         'lipkovo': 6,
         'lozovo': 1,
         'makedonska-kamenica': 1,
         'makedonski-brod': 3,
         'mavrovo-and-rostusa': 4,
         'mogila': 4,
         'negotino': 3,
         'novaci': 2,
         'novo-selo': 4,
         'ohrid': 9,
         'pehcevo': 1,
         'petrovec': 2,
         'plasnica': 1,
         'prilep': 7,
         'probistip': 2,
         'radovis': 3,
         'rankovce': 2,
         'resen': 7,
         'rosoman': 2,
         'saraj': 4,
         'sopiste': 1,
         'staro-nagoricane': 5,
         'stip': 4,
         'struga': 9,
         'strumica': 10,
         'studenicani': 2,
         'suto-orizari': 1,
         'sveti-nikole': 3,
         'tearce': 4,
         'tetovo': 10,
         'valandovo': 5,
         'vasilevo': 3,
         'veles': 7,
         'vevcani': 1,
         'vinica': 4,
         'vrapciste': 5,
         'zelenikovo': 1,
         'zelino': 2,
         'zrnovci': 1,
}
have = Counter()
for l in links:
    if l['is_primary'] == 'true':
        have[l['area_source_id'].split(':')[-1]] += 1
for slug, n in expect.items():
    check(f'count-{slug}-{n}', have[slug] == n, str(have[slug]))
check('zero-codeless', set(expect) == set(have))
plink = {l['postcode']: l['area_source_id'] for l in links
         if l['is_primary'] == 'true'}
# --- the fix: 1128 airport office is in Ilinden, not Petrovec ---
# Terminal + post-office POI sit in Mralino/Ilinden (OSM boundaries);
# history.mk: since the 1996 boundary redefinition the airport is in
# Ilinden municipality; terminal counter addressed Mralino, Ilinden.
# "Petrovec" is the airport's conventional name (nearest village).
check('fix-1128-ilinden', plink.get('1128') == 'mk:municipality:ilinden',
      str(plink.get('1128')))
# --- UPU MKD anchors (07/2019 profile examples) ---
check('upu-1020-karpos', plink.get('1020') == 'mk:municipality:karpos',
      str(plink.get('1020')))
check('upu-1310-kumanovo', plink.get('1310') == 'mk:municipality:kumanovo',
      str(plink.get('1310')))
check('upu-2314-vinica', plink.get('2314') == 'mk:municipality:vinica',
      str(plink.get('2314')))
# --- cross-block keeps (office village outside the block capital) ---
keeps = {
         '2333': 'cesinovo-oblesevo',  # Cesinovo in the 233x Berovo block
         '2315': 'berovo',  # Rusinovo in the 231x Vinica block
         '2301': 'cesinovo-oblesevo',  # Oblesevo in the 230x Kocani block
         '2304': 'makedonska-kamenica',
         '2305': 'zrnovci',
         '2332': 'berovo',  # Budinarci
         '7313': 'bitola',  # 731x Resen block, Bitola villages
         '7314': 'bitola',
         '7204': 'bitola',  # 72xx Demir Hisar block, Bitola villages
         '7205': 'bitola',
         '2411': 'vasilevo',  # 241x Strumica block, Vasilevo villages
         '2412': 'vasilevo',
         '2416': 'vasilevo',
         '2432': 'strumica',  # 243x Bosilovo block, Strumica village
         '1316': 'rankovce',  # 131x Kumanovo block, Rankovce village
         '1332': 'rankovce',  # 133x Kriva Palanka block, Rankovce village
         '1408': 'veles',
         '1423': 'kavadarci',  # 142x Rosoman/Gradsko block
         '1424': 'kavadarci',
         '1425': 'kavadarci',  # Vatasa
         '1426': 'kavadarci',
         '1427': 'kavadarci',
         '1484': 'bogdanci',  # 148x Gevgelija block
         '1488': 'bogdanci',
         '1485': 'dojran',
         '1487': 'dojran',
         '1492': 'dojran',  # Stari Dojran border crossing
         '2202': 'karbinci',  # Krupiste in the 22xx Stip block
         '2205': 'stip',  # Tri Cesmi
         '2208': 'lozovo',  # Lozovo in the 22xx block
         '2220': 'sveti-nikole',
         '2225': 'sveti-nikole',
         '2227': 'sveti-nikole',
}
# --- GeoNames-homonym keeps (GN coords point at the wrong twin; the
# official unit name + village municipality win) ---
keeps.update({
         '1054': 'sopiste',  # Rakotinci (GN: Rakitnica/Demir Hisar)
         '1235': 'vrapciste',  # Negotino-Polosko (GN: Negotino town)
         '2434': 'novo-selo',  # Novo Selo Strumicko (GN coords off)
         '2436': 'novo-selo',  # Drazevo (GN: Dracevo-Skopje)
         '6260': 'kicevo',  # Rastanski Pat, Kicevo (GN coords off)
         '6306': 'ohrid',  # Leskoec Ohridski (GN: Leskoec/Resen)
         '1010': 'cair',  # Skopje-Cair (GN: city centroid)
         '1020': 'karpos',  # Skopje-Karpos (GN coords off)
         '1040': 'gazi-baba',  # Skopje-Madzari (GN: city centroid)
})
# --- border-crossing keeps (unit is the crossing, municipality of site) ---
keeps.update({
         '1061': 'cucer-sandevo',  # Blace
         '1259': 'debar',  # Blato
         '1318': 'staro-nagoricane',  # Pelince
         '1319': 'kumanovo',  # Tabanovce
         '1331': 'kriva-palanka',  # Deve Bair
         '1482': 'gevgelija',  # Bogorodica
         '2321': 'delcevo',  # Delcevo
         '2437': 'novo-selo',  # Novo Selo
         '6324': 'ohrid',  # Sveti Naum
         '6340': 'struga',  # Cafasan
         '7226': 'bitola',  # Medzitlija
         '7321': 'resen',  # Stenje
})
# --- Skopje branch keeps (naselba/street -> city municipality) ---
keeps.update({
         '1101': 'centar',  # Orce Nikolov
         '1103': 'centar',  # Sudska palata
         '1104': 'kisela-voda',  # Ivan Kozarov
         '1105': 'karpos',  # Skopje 5 Karpos
         '1106': 'centar',  # Kej 13 Noemvri GTC
         '1107': 'aerodrom',  # J. Sandanski, nas. Aerodrom
         '1108': 'gazi-baba',  # Avtokomanda
         '1109': 'centar',  # Majka Tereza
         '1110': 'karpos',  # Kozle
         '1111': 'centar',  # Univerzalna sala
         '1112': 'centar',  # Ramstor
         '1113': 'gazi-baba',  # 15 Korpus, Carina
         '1114': 'centar',  # Mito Hadzivasilev
         '1115': 'butel',  # nas. Butel
         '1116': 'kisela-voda',  # Rasadnik
         '1117': 'cair',  # Topansko Pole
         '1118': 'gazi-baba',  # Madzari
         '1119': 'karpos',  # Taftalidze 2
         '1120': 'kisela-voda',  # Crnice
         '1121': 'aerodrom',  # Lisice
         '1122': 'karpos',  # Kapistec
         '1123': 'butel',  # Radisani
         '1125': 'centar',  # MVR
         '1126': 'aerodrom',  # nas. Aerodrom
         '1129': 'kisela-voda',  # Dracevo
         '1130': 'centar',  # Belasica, Skopje Fair
         '1131': 'centar',  # Beverli Hils
         '1132': 'cair',  # Bitpazar
         '1133': 'gjorce-petrov',  # Volkovo
         '1134': 'aerodrom',  # Novo Lisice
         '1136': 'gazi-baba',  # Zelezarnica
         '1138': 'suto-orizari',  # nas. Suto Orizari
         '1139': 'karpos',  # Taftalidze
         '1140': 'kisela-voda',  # nas. 11 Oktomvri
         '1141': 'gazi-baba',  # Hipodrom
         '1142': 'butel',  # nas. Sever (Skopje Sever)
})
for pc, slug in keeps.items():
    want = f'mk:municipality:{slug}'
    check(f'keep-{pc}-{slug}', plink.get(pc) == want, str(plink.get(pc)))
# --- drops: absent by design ---
dropped = [
    '1137',  # Skopje 37: in the 2016 list, commune uncertain (Krste
             # Misirkov bb with no naselba; the boulevard straddles
             # Cair/Centar: 1132 Bitpazar -> Cair, 1103 court -> Centar)
    '7515',  # Novo Lagovo: mk-wiki/wikidata only; absent from the
             # 2005/2016/2018 official lists and GeoNames (post-2018?)
    # 2005-listed, retired before 2016 (GeoNames still carries them):
    '1434', '6245', '6256', '6259', '7213', '7214', '7224', '7242',
    '7316', '7506', '7508',
    # 2005-listed, retired before 2016 (other): 1046 Cresovo (marked
    # closed in 2005), 1124 Skopje 24, 1253 Lazaropole seasonal,
    # 1490 Bogorodica, 6103 Ohrid 3.
    '1046', '1124', '1253', '1490', '6103',
    # Post-2016 openings (2018 official list; vintage hold, not gaps):
    '1012', '1013', '1014', '1065', '1127', '1135', '1205', '1208',
    '1245', '1246', '1329', '1336', '1340', '1404', '1412', '1432',
    '1486', '2103', '2311', '2334', '2405', '2406', '2422',
]
have_codes = {c['code'] for c in codes}
for pc in dropped:
    check(f'drop-{pc}', pc not in have_codes)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
