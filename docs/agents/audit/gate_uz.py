import csv, re, sys
from collections import Counter
# Uzbekistan gate. M6: tree 14 L1 + 175 tumans + 31 cities vs
# ISO 3166-2:UZ + ru.wiki admin-division page (stat.uz SOATO) +
# en.wiki district lists (163 region tumans match; 12 Tashkent
# districts + 31 regional cities match); postal 2140/2140 vs
# Postcodebase full crawl (183 zones, 2963 rows) + MITC decree
# draft IHL-1909/22-2 (1886 codes) + Mapanet re-crawl (2795
# codes): 2137/2140 corroborated, 29/30 sampled attributions
# agree. M6 FIX: 120501-120505 Sardoba-t -> Oqoltin-t (decree
# Sardoba PAB scope + OSM). Gaps: 71 L2 (466 PCB+decree fills +
# 171 hub mains inventoried for follow-up, NOT filled here).
# Run from repo root:
# python3 docs/agents/audit/gate_uz.py
A = './packages/addressing/resources/geography/uzbekistan-address-areas.csv'
C = './packages/addressing/resources/geography/uzbekistan-postal-codes.csv'
L = './packages/addressing/resources/geography/uzbekistan-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 12 regions + 1 republic + 1 L1 city + 175 tumans + 31 cities ---
check('areas-220', len(rows) == 220, str(len(rows)))
check('regions-12', sum(1 for r in rows if r['type'] == 'region') == 12)
check('republics-1', sum(1 for r in rows if r['type'] == 'republic') == 1)
check('cities-32', sum(1 for r in rows if r['type'] == 'city') == 32)
check('tumans-175', sum(1 for r in rows if r['type'] == 'tuman') == 175)
check('l1-14', sum(1 for r in rows if r['level'] == '1') == 14)
check('l2-206', sum(1 for r in rows if r['level'] == '2') == 206)
iso = {'uz:region:andijan': ('Andijan', 'AN'),
 'uz:region:bukhara': ('Bukhara', 'BU'),
 'uz:region:fergana': ('Fergana', 'FA'),
 'uz:region:jizzakh': ('Jizzakh', 'JI'),
 'uz:region:namangan': ('Namangan', 'NG'),
 'uz:region:navoiy': ('Navoiy', 'NW'),
 'uz:region:qashqadaryo': ('Qashqadaryo', 'QA'),
 'uz:republic:karakalpakstan': ('Karakalpakstan', 'QR'),
 'uz:region:samarqand': ('Samarqand', 'SA'),
 'uz:region:sirdaryo': ('Sirdaryo', 'SI'),
 'uz:region:surxondaryo': ('Surxondaryo', 'SU'),
 'uz:city:tashkent-city': ('Tashkent City', 'TK'),
 'uz:region:tashkent-region': ('Tashkent Region', 'TO'),
 'uz:region:xorazm': ('Xorazm', 'XO')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
table = {
 'uz:city:tashkent-city': [['Bektemir', 'tuman'], ['Chilonzor', 'tuman'], ['Mirobod', 'tuman'], ["Mirzo Ulug'bek", 'tuman'], ['Olmazor', 'tuman'], ['Shayxontoxur', 'tuman'], ["Sirg'ali", 'tuman'], ['Uchtepa', 'tuman'], ['Yakkasaroy', 'tuman'], ['Yangihayot', 'tuman'], ['Yashnobod', 'tuman'], ['Yunusobod', 'tuman']],
 'uz:region:andijan': [['Andijon', 'city'], ['Andijon', 'tuman'], ['Asaka', 'tuman'], ['Baliqchi', 'tuman'], ["Bo'ston", 'tuman'], ['Buloqboshi', 'tuman'], ['Izboskan', 'tuman'], ['Jalaquduq', 'tuman'], ['Marhamat', 'tuman'], ["Oltinko'l", 'tuman'], ['Paxtaobod', 'tuman'], ["Qo'rg'ontepa", 'tuman'], ['Shahrixon', 'tuman'], ["Ulug'nor", 'tuman'], ["Xo'jaobod", 'tuman'], ['Xonobod', 'city']],
 'uz:region:bukhara': [['Buxoro', 'city'], ['Buxoro', 'tuman'], ["G'ijduvon", 'tuman'], ['Jondor', 'tuman'], ['Kogon', 'city'], ['Kogon', 'tuman'], ['Olot', 'tuman'], ['Peshku', 'tuman'], ["Qorako'l", 'tuman'], ['Qorovulbozor', 'tuman'], ['Romitan', 'tuman'], ['Shofirkon', 'tuman'], ['Vobkent', 'tuman']],
 'uz:region:fergana': [['Beshariq', 'tuman'], ["Bog'dod", 'tuman'], ['Buvayda', 'tuman'], ["Dang'ara", 'tuman'], ["Farg'ona", 'city'], ["Farg'ona", 'tuman'], ['Furqat', 'tuman'], ["Marg'ilon", 'city'], ["O'zbekiston", 'tuman'], ['Oltiariq', 'tuman'], ["Qo'qon", 'city'], ["Qo'shtepa", 'tuman'], ['Quva', 'tuman'], ['Quvasoy', 'city'], ['Rishton', 'tuman'], ["So'x", 'tuman'], ['Toshloq', 'tuman'], ["Uchko'prik", 'tuman'], ['Yozyovon', 'tuman']],
 'uz:region:jizzakh': [['Arnasoy', 'tuman'], ['Baxmal', 'tuman'], ["Do'stlik", 'tuman'], ['Forish', 'tuman'], ["G'allaorol", 'tuman'], ['Jizzax', 'city'], ["Mirzacho'l", 'tuman'], ['Paxtakor', 'tuman'], ['Sharof Rashidov', 'tuman'], ['Yangiobod', 'tuman'], ['Zafarobod', 'tuman'], ['Zarbdor', 'tuman'], ['Zomin', 'tuman']],
 'uz:region:namangan': [['Chortoq', 'tuman'], ['Chust', 'tuman'], ['Kosonsoy', 'tuman'], ['Mingbuloq', 'tuman'], ['Namangan', 'city'], ['Namangan', 'tuman'], ['Norin', 'tuman'], ['Pop', 'tuman'], ["To'raqo'rg'on", 'tuman'], ["Uchqo'rg'on", 'tuman'], ['Uychi', 'tuman'], ["Yangiqo'rg'on", 'tuman']],
 'uz:region:navoiy': [["G'ozg'on", 'city'], ['Karmana', 'tuman'], ['Konimex', 'tuman'], ['Navbahor', 'tuman'], ['Navoiy', 'city'], ['Nurota', 'tuman'], ['Qiziltepa', 'tuman'], ['Tomdi', 'tuman'], ['Uchquduq', 'tuman'], ['Xatirchi', 'tuman'], ['Zarafshon', 'city']],
 'uz:region:qashqadaryo': [['Chiroqchi', 'tuman'], ['Dehqonobod', 'tuman'], ["G'uzor", 'tuman'], ['Kasbi', 'tuman'], ['Kitob', 'tuman'], ["Ko'kdala", 'tuman'], ['Koson', 'tuman'], ['Mirishkor', 'tuman'], ['Muborak', 'tuman'], ['Nishon', 'tuman'], ['Qamashi', 'tuman'], ['Qarshi', 'city'], ['Qarshi', 'tuman'], ['Shahrisabz', 'city'], ['Shahrisabz', 'tuman'], ["Yakkabog'", 'tuman']],
 'uz:region:samarqand': [["Bulung'ur", 'tuman'], ['Ishtixon', 'tuman'], ['Jomboy', 'tuman'], ["Kattaqo'rg'on", 'city'], ["Kattaqo'rg'on", 'tuman'], ['Narpay', 'tuman'], ['Nurobod', 'tuman'], ['Oqdaryo', 'tuman'], ["Pastdarg'om", 'tuman'], ['Paxtachi', 'tuman'], ['Payariq', 'tuman'], ["Qo'shrabot", 'tuman'], ['Samarqand', 'city'], ['Samarqand', 'tuman'], ['Toyloq', 'tuman'], ['Urgut', 'tuman']],
 'uz:region:sirdaryo': [['Boyovut', 'tuman'], ['Guliston', 'city'], ['Guliston', 'tuman'], ['Mirzaobod', 'tuman'], ['Oqoltin', 'tuman'], ['Sardoba', 'tuman'], ['Sayxunobod', 'tuman'], ['Shirin', 'city'], ['Sirdaryo', 'tuman'], ['Xovos', 'tuman'], ['Yangiyer', 'city']],
 'uz:region:surxondaryo': [['Angor', 'tuman'], ['Bandixon', 'tuman'], ['Boysun', 'tuman'], ['Denov', 'tuman'], ["Jarqo'rg'on", 'tuman'], ['Muzrabot', 'tuman'], ['Oltinsoy', 'tuman'], ['Qiziriq', 'tuman'], ["Qumqo'rg'on", 'tuman'], ['Sariosiyo', 'tuman'], ['Sherobod', 'tuman'], ["Sho'rchi", 'tuman'], ['Termiz', 'city'], ['Termiz', 'tuman'], ['Uzun', 'tuman']],
 'uz:region:tashkent-region': [['Angren', 'city'], ['Bekobod', 'city'], ['Bekobod', 'tuman'], ["Bo'ka", 'tuman'], ["Bo'stonliq", 'tuman'], ['Chinoz', 'tuman'], ['Chirchiq', 'city'], ['Nurafshon', 'city'], ["O'rtachirchiq", 'tuman'], ['Ohangaron', 'city'], ['Ohangaron', 'tuman'], ['Olmaliq', 'city'], ["Oqqo'rg'on", 'tuman'], ['Parkent', 'tuman'], ['Piskent', 'tuman'], ['Qibray', 'tuman'], ['Quyichirchiq', 'tuman'], ['Toshkent', 'tuman'], ["Yangiyo'l", 'city'], ["Yangiyo'l", 'tuman'], ['Yuqorichirchiq', 'tuman'], ['Zangiota', 'tuman']],
 'uz:region:xorazm': [["Bog'ot", 'tuman'], ['Gurlan', 'tuman'], ['Hazorasp', 'tuman'], ["Qo'shko'pir", 'tuman'], ['Shovot', 'tuman'], ["Tuproqqal'a", 'tuman'], ['Urganch', 'city'], ['Urganch', 'tuman'], ['Xiva', 'city'], ['Xiva', 'tuman'], ['Xonqa', 'tuman'], ['Yangiariq', 'tuman'], ['Yangibozor', 'tuman']],
 'uz:republic:karakalpakstan': [['Amudaryo', 'tuman'], ['Beruniy', 'tuman'], ["Bo'zatov", 'tuman'], ['Chimboy', 'tuman'], ["Ellikqal'a", 'tuman'], ['Kegeyli', 'tuman'], ["Mo'ynoq", 'tuman'], ['Nukus', 'city'], ['Nukus', 'tuman'], ["Qanliko'l", 'tuman'], ["Qo'ng'irot", 'tuman'], ["Qorao'zak", 'tuman'], ['Shumanay', 'tuman'], ['Taxiatosh', 'tuman'], ["Taxtako'pir", 'tuman'], ["To'rtko'l", 'tuman'], ["Xo'jayli", 'tuman']],
}
ok = True
for par, names in table.items():
    have = sorted([r['name'], r['type']] for r in rows
                  if r['parent_source_id'] == par)
    if have != sorted(names):
        print('FAIL parent', par); fails.append(f'parent {par}'); ok = False
if ok: print('PASS all 14 parent mappings (206 L2)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# 17 tuman/city twins share a parent; Tashkent city spans L1/L2 types.
twins = sum(1 for r in rows if r['type'] == 'city' and r['level'] == '2'
            and any(x['name'] == r['name'] and x['type'] == 'tuman'
                    and x['parent_source_id'] == r['parent_source_id']
                    for x in rows))
check('twins-17', twins == 17, str(twins))
# --- postal: 2140 codes / 2140 links, all primary, no multis ---
check('codes-2140', len(codes) == 2140, str(len(codes)))
check('links-2140', len(links) == 2140, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{6}$', c['code'])]
check('code-format-6n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 2140 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('all-primary', all(l['is_primary'] == 'true' for l in links))
code_set = {c['code'] for c in codes}
link_set = {l['postcode'] for l in links}
check('codes-align-links', code_set == link_set)
check('no-dupes', len(code_set) == 2140)
# --- one prefix per L1; Tashkent city coded at L1 ---
prefix = {'uz:city:tashkent-city': '10', 'uz:region:tashkent-region': '11',
 'uz:region:sirdaryo': '12', 'uz:region:jizzakh': '13',
 'uz:region:samarqand': '14', 'uz:region:fergana': '15',
 'uz:region:namangan': '16', 'uz:region:andijan': '17',
 'uz:region:qashqadaryo': '18', 'uz:region:surxondaryo': '19',
 'uz:region:bukhara': '20', 'uz:region:navoiy': '21',
 'uz:region:xorazm': '22', 'uz:republic:karakalpakstan': '23'}
badp = []
for l in links:
    a = byid[l['area_source_id']]
    par = a['parent_source_id'] if a['level'] == '2' else a['source_id']
    if l['postcode'][:2] != prefix[par]:
        badp.append((l['postcode'], l['area_source_id']))
check('prefix-per-l1', not badp, str(badp[:3]))
check('tashkent-l1-162', sum(1 for l in links
      if l['area_source_id'] == 'uz:city:tashkent-city') == 162)
l1counts = {'uz:city:tashkent-city': 162, 'uz:region:andijan': 156,
 'uz:region:bukhara': 150, 'uz:region:fergana': 165,
 'uz:region:jizzakh': 128, 'uz:region:namangan': 171,
 'uz:region:navoiy': 109, 'uz:region:qashqadaryo': 204,
 'uz:region:samarqand': 206, 'uz:region:sirdaryo': 107,
 'uz:region:surxondaryo': 161, 'uz:region:tashkent-region': 156,
 'uz:region:xorazm': 127, 'uz:republic:karakalpakstan': 138}
have1 = Counter()
for l in links:
    a = byid[l['area_source_id']]
    par = a['parent_source_id'] if a['level'] == '2' else a['source_id']
    have1[par] += 1
for par, n in l1counts.items():
    check(f'l1count-{par.split(":")[-1]}', have1[par] == n, str(have1[par]))
link1 = {l['postcode']: l['area_source_id'] for l in links}
# UPU anchors: 100000/100123 Tashkent HQ, 220605 Qo'shko'pir
# (UPU Kashkupyrskiy rayon), city mains 140100/190100/230100.
check('upu-100000', link1.get('100000') == 'uz:city:tashkent-city')
check('upu-100123', link1.get('100123') == 'uz:city:tashkent-city')
check('upu-220605', link1.get('220605') == 'uz:tuman:xorazm:qoshkopir')
check('main-140100', link1.get('140100') == 'uz:city:samarqand:samarqand')
check('main-190100', link1.get('190100') == 'uz:city:surxondaryo:termiz')
check('main-230100', link1.get('230100') == 'uz:city:karakalpakstan:nukus')
# M6 FIX pins: Sardoba PAB serves Oqoltin-t (decree + OSM).
for pc in ['120501', '120502', '120503', '120504', '120505']:
    check(f'fix-{pc}', link1.get(pc) == 'uz:tuman:sirdaryo:oqoltin',
          str(link1.get(pc)))
check('sardoba-7', sum(1 for l in links if l['area_source_id']
      == 'uz:tuman:sirdaryo:sardoba') == 7)
# Tricky zone mappings (Postcodebase-corroborated).
for pc, sid in [('170401', 'uz:tuman:andijan:boston'),
    ('120301', 'uz:tuman:sirdaryo:guliston'),
    ('170614', 'uz:tuman:andijan:andijon'),
    ('120401', 'uz:tuman:sirdaryo:sayxunobod'),
    ('190710', 'uz:tuman:surxondaryo:oltinsoy'),
    ('210302', 'uz:tuman:navoiy:tomdi'),
    ('210303', 'uz:tuman:navoiy:tomdi'),
    ('220901', 'uz:city:xorazm:xiva'),
    ('220902', 'uz:tuman:xorazm:xiva'),
    ('220912', 'uz:tuman:xorazm:xiva'),
    ('120708', 'uz:tuman:sirdaryo:xovos')]:
    check(f'map-{pc}', link1.get(pc) == sid, str(link1.get(pc)))
# Karakuduk: the Mapanet Navoiy 120708 row was the anomaly (dropped);
# the kept 120708 is Xovos-t (prefix-correct); the real Navoiy
# Qoraquduq 210708 sits in the follow-up fill inventory.
check('no-navoiy-120708', not [l for l in links
      if l['postcode'] == '120708' and 'navoiy' in l['area_source_id']])
# 140101 (UPU Samarqand example + decree + PCB) is a known gap:
# deliberately absent until the fill pass (must not silently appear).
check('known-absent-140101', '140101' not in link1)
# --- per-area primary counts ---
expect = {
 'uz:city:andijan:andijon': 25,
 'uz:city:bukhara:buxoro': 21,
 'uz:city:bukhara:kogon': 5,
 'uz:city:fergana:fargona': 20,
 'uz:city:fergana:margilon': 14,
 'uz:city:fergana:qoqon': 12,
 'uz:city:fergana:quvasoy': 11,
 'uz:city:jizzakh:jizzax': 10,
 'uz:city:karakalpakstan:nukus': 1,
 'uz:city:namangan:namangan': 28,
 'uz:city:navoiy:navoiy': 9,
 'uz:city:qashqadaryo:qarshi': 18,
 'uz:city:samarqand:kattaqorgon': 7,
 'uz:city:samarqand:samarqand': 1,
 'uz:city:sirdaryo:guliston': 6,
 'uz:city:sirdaryo:yangiyer': 5,
 'uz:city:surxondaryo:termiz': 1,
 'uz:city:tashkent-city': 162,
 'uz:city:tashkent-region:angren': 22,
 'uz:city:tashkent-region:bekobod': 13,
 'uz:city:tashkent-region:chirchiq': 17,
 'uz:city:tashkent-region:olmaliq': 9,
 'uz:city:xorazm:urganch': 8,
 'uz:city:xorazm:xiva': 1,
 'uz:tuman:andijan:andijon': 23,
 'uz:tuman:andijan:asaka': 18,
 'uz:tuman:andijan:baliqchi': 20,
 'uz:tuman:andijan:boston': 9,
 'uz:tuman:andijan:buloqboshi': 8,
 'uz:tuman:andijan:marhamat': 14,
 'uz:tuman:andijan:oltinkol': 14,
 'uz:tuman:andijan:qorgontepa': 17,
 'uz:tuman:andijan:ulugnor': 8,
 'uz:tuman:bukhara:buxoro': 14,
 'uz:tuman:bukhara:gijduvon': 22,
 'uz:tuman:bukhara:jondor': 13,
 'uz:tuman:bukhara:kogon': 9,
 'uz:tuman:bukhara:olot': 9,
 'uz:tuman:bukhara:qorakol': 14,
 'uz:tuman:bukhara:romitan': 13,
 'uz:tuman:bukhara:shofirkon': 16,
 'uz:tuman:bukhara:vobkent': 14,
 'uz:tuman:fergana:beshariq': 22,
 'uz:tuman:fergana:bogdod': 20,
 'uz:tuman:fergana:dangara': 18,
 'uz:tuman:fergana:furqat': 12,
 'uz:tuman:fergana:qoshtepa': 16,
 'uz:tuman:fergana:quva': 20,
 'uz:tuman:jizzakh:arnasoy': 6,
 'uz:tuman:jizzakh:baxmal': 23,
 'uz:tuman:jizzakh:dostlik': 7,
 'uz:tuman:jizzakh:forish': 18,
 'uz:tuman:jizzakh:gallaorol': 27,
 'uz:tuman:jizzakh:mirzachol': 8,
 'uz:tuman:jizzakh:paxtakor': 8,
 'uz:tuman:jizzakh:sharof-rashidov': 16,
 'uz:tuman:jizzakh:yangiobod': 5,
 'uz:tuman:karakalpakstan:amudaryo': 19,
 'uz:tuman:karakalpakstan:beruniy': 21,
 'uz:tuman:karakalpakstan:chimboy': 15,
 'uz:tuman:karakalpakstan:ellikqala': 20,
 'uz:tuman:karakalpakstan:kegeyli': 12,
 'uz:tuman:karakalpakstan:moynoq': 8,
 'uz:tuman:karakalpakstan:nukus': 10,
 'uz:tuman:karakalpakstan:qanlikol': 6,
 'uz:tuman:karakalpakstan:qongirot': 18,
 'uz:tuman:karakalpakstan:qoraozak': 8,
 'uz:tuman:namangan:chortoq': 15,
 'uz:tuman:namangan:chust': 17,
 'uz:tuman:namangan:kosonsoy': 12,
 'uz:tuman:namangan:mingbuloq': 12,
 'uz:tuman:namangan:namangan': 15,
 'uz:tuman:namangan:pop': 22,
 'uz:tuman:namangan:toraqorgon': 20,
 'uz:tuman:namangan:uchqorgon': 14,
 'uz:tuman:namangan:uychi': 16,
 'uz:tuman:navoiy:karmana': 10,
 'uz:tuman:navoiy:konimex': 8,
 'uz:tuman:navoiy:navbahor': 12,
 'uz:tuman:navoiy:nurota': 21,
 'uz:tuman:navoiy:qiziltepa': 16,
 'uz:tuman:navoiy:tomdi': 7,
 'uz:tuman:navoiy:uchquduq': 7,
 'uz:tuman:navoiy:xatirchi': 19,
 'uz:tuman:qashqadaryo:chiroqchi': 39,
 'uz:tuman:qashqadaryo:dehqonobod': 16,
 'uz:tuman:qashqadaryo:guzor': 22,
 'uz:tuman:qashqadaryo:kasbi': 18,
 'uz:tuman:qashqadaryo:kitob': 18,
 'uz:tuman:qashqadaryo:koson': 20,
 'uz:tuman:qashqadaryo:muborak': 7,
 'uz:tuman:qashqadaryo:qamashi': 23,
 'uz:tuman:qashqadaryo:qarshi': 23,
 'uz:tuman:samarqand:bulungur': 15,
 'uz:tuman:samarqand:ishtixon': 29,
 'uz:tuman:samarqand:jomboy': 16,
 'uz:tuman:samarqand:kattaqorgon': 1,
 'uz:tuman:samarqand:narpay': 19,
 'uz:tuman:samarqand:nurobod': 14,
 'uz:tuman:samarqand:oqdaryo': 20,
 'uz:tuman:samarqand:pastdargom': 34,
 'uz:tuman:samarqand:qoshrabot': 18,
 'uz:tuman:samarqand:samarqand': 32,
 'uz:tuman:sirdaryo:boyovut': 22,
 'uz:tuman:sirdaryo:guliston': 9,
 'uz:tuman:sirdaryo:mirzaobod': 12,
 'uz:tuman:sirdaryo:oqoltin': 5,
 'uz:tuman:sirdaryo:sardoba': 7,
 'uz:tuman:sirdaryo:sayxunobod': 13,
 'uz:tuman:sirdaryo:sirdaryo': 17,
 'uz:tuman:sirdaryo:xovos': 11,
 'uz:tuman:surxondaryo:angor': 17,
 'uz:tuman:surxondaryo:bandixon': 2,
 'uz:tuman:surxondaryo:boysun': 17,
 'uz:tuman:surxondaryo:denov': 28,
 'uz:tuman:surxondaryo:jarqorgon': 18,
 'uz:tuman:surxondaryo:oltinsoy': 13,
 'uz:tuman:surxondaryo:qiziriq': 12,
 'uz:tuman:surxondaryo:qumqorgon': 16,
 'uz:tuman:surxondaryo:sariosiyo': 20,
 'uz:tuman:surxondaryo:sherobod': 17,
 'uz:tuman:tashkent-region:boka': 15,
 'uz:tuman:tashkent-region:chinoz': 16,
 'uz:tuman:tashkent-region:ohangaron': 14,
 'uz:tuman:tashkent-region:oqqorgon': 12,
 'uz:tuman:tashkent-region:quyichirchiq': 20,
 'uz:tuman:tashkent-region:zangiota': 18,
 'uz:tuman:xorazm:bogot': 13,
 'uz:tuman:xorazm:gurlan': 15,
 'uz:tuman:xorazm:hazorasp': 14,
 'uz:tuman:xorazm:qoshkopir': 16,
 'uz:tuman:xorazm:shovot': 13,
 'uz:tuman:xorazm:tuproqqala': 6,
 'uz:tuman:xorazm:urganch': 14,
 'uz:tuman:xorazm:xiva': 13,
 'uz:tuman:xorazm:xonqa': 14,
}
have = Counter(l['area_source_id'] for l in links
               if l['is_primary'] == 'true')
for sid, n in expect.items():
    check(f"count-{sid.split(':')[-1]}-{n}", have[sid] == n,
          str(have[sid]))
# --- 71 L2 codeless post-fix (12 Tashkent tumans by design) ---
gaps = {
 'uz:city:andijan:xonobod',
 'uz:city:navoiy:gozgon',
 'uz:city:navoiy:zarafshon',
 'uz:city:qashqadaryo:shahrisabz',
 'uz:city:sirdaryo:shirin',
 'uz:city:tashkent-region:nurafshon',
 'uz:city:tashkent-region:ohangaron',
 'uz:city:tashkent-region:yangiyol',
 'uz:tuman:andijan:izboskan',
 'uz:tuman:andijan:jalaquduq',
 'uz:tuman:andijan:paxtaobod',
 'uz:tuman:andijan:shahrixon',
 'uz:tuman:andijan:xojaobod',
 'uz:tuman:bukhara:peshku',
 'uz:tuman:bukhara:qorovulbozor',
 'uz:tuman:fergana:buvayda',
 'uz:tuman:fergana:fargona',
 'uz:tuman:fergana:oltiariq',
 'uz:tuman:fergana:ozbekiston',
 'uz:tuman:fergana:rishton',
 'uz:tuman:fergana:sox',
 'uz:tuman:fergana:toshloq',
 'uz:tuman:fergana:uchkoprik',
 'uz:tuman:fergana:yozyovon',
 'uz:tuman:jizzakh:zafarobod',
 'uz:tuman:jizzakh:zarbdor',
 'uz:tuman:jizzakh:zomin',
 'uz:tuman:karakalpakstan:bozatov',
 'uz:tuman:karakalpakstan:shumanay',
 'uz:tuman:karakalpakstan:taxiatosh',
 'uz:tuman:karakalpakstan:taxtakopir',
 'uz:tuman:karakalpakstan:tortkol',
 'uz:tuman:karakalpakstan:xojayli',
 'uz:tuman:namangan:norin',
 'uz:tuman:namangan:yangiqorgon',
 'uz:tuman:qashqadaryo:kokdala',
 'uz:tuman:qashqadaryo:mirishkor',
 'uz:tuman:qashqadaryo:nishon',
 'uz:tuman:qashqadaryo:shahrisabz',
 'uz:tuman:qashqadaryo:yakkabog',
 'uz:tuman:samarqand:paxtachi',
 'uz:tuman:samarqand:payariq',
 'uz:tuman:samarqand:toyloq',
 'uz:tuman:samarqand:urgut',
 'uz:tuman:surxondaryo:muzrabot',
 'uz:tuman:surxondaryo:shorchi',
 'uz:tuman:surxondaryo:termiz',
 'uz:tuman:surxondaryo:uzun',
 'uz:tuman:tashkent-city:bektemir',
 'uz:tuman:tashkent-city:chilonzor',
 'uz:tuman:tashkent-city:mirobod',
 'uz:tuman:tashkent-city:mirzo-ulugbek',
 'uz:tuman:tashkent-city:olmazor',
 'uz:tuman:tashkent-city:shayxontoxur',
 'uz:tuman:tashkent-city:sirgali',
 'uz:tuman:tashkent-city:uchtepa',
 'uz:tuman:tashkent-city:yakkasaroy',
 'uz:tuman:tashkent-city:yangihayot',
 'uz:tuman:tashkent-city:yashnobod',
 'uz:tuman:tashkent-city:yunusobod',
 'uz:tuman:tashkent-region:bekobod',
 'uz:tuman:tashkent-region:bostonliq',
 'uz:tuman:tashkent-region:ortachirchiq',
 'uz:tuman:tashkent-region:parkent',
 'uz:tuman:tashkent-region:piskent',
 'uz:tuman:tashkent-region:qibray',
 'uz:tuman:tashkent-region:toshkent',
 'uz:tuman:tashkent-region:yangiyol',
 'uz:tuman:tashkent-region:yuqorichirchiq',
 'uz:tuman:xorazm:yangiariq',
 'uz:tuman:xorazm:yangibozor',
}
for sid in gaps:
    check(f'gap-{sid.split(":")[-1]}',
          not [l for l in links if l['area_source_id'] == sid])
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
