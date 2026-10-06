import csv, re, sys
from collections import Counter
# Tunisia gate. M6 revisit: fix-and-fill +174 codes/+176 links (969/982).
# Tree verified exact vs ISO 3166-2:TN (24 codes) + Delegations-of-Tunisia
# (279/279 names). Fresh 4,860-row Mapanet TN re-pull code-set == the
# Postal-codes-in-Tunisia JSON 969/969; WPC holds 918 (subset). The old
# 795-set under-linked at r2-match granularity (the 174 lived under
# transliteration-mismatched r2s); fills attributed by Mapanet r2 (old gov
# codes remapped) with WPC-town agreement + La Poste Mar-2025 roster (29)
# + Nominatim/OSM (seats, namesakes, Ouest offices). All 9 old multis
# re-adjudicated: primaries kept (ties/singles never move); 2 new multis
# (1008 5v1 Medina, 2089 3v1 Kram). Skips: 7 official-newer codes
# (single-signal), UPU-only 8129 (in no directory).
# Run from repo root: python3 /tmp/geo-verify/M6/TN/gate_tn.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/tunisia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/tunisia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/tunisia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-303', len(rows) == 303, str(len(rows)))
check('governorates-24', sum(1 for r in rows if r['type'] == 'governorate') == 24)
check('delegations-279', sum(1 for r in rows if r['type'] == 'delegation') == 279)
# ISO 3166-2:TN (article/diacritic display deviations deliberate:
# L'Ariana, La Manouba, Le Kef articles dropped; Kebili/Medenine unaccented).
iso = {'11': 'Tunis', '12': 'Ariana', '13': 'Ben Arous', '14': 'Manouba',
 '21': 'Nabeul', '22': 'Zaghouan', '23': 'Bizerte', '31': 'B\u00e9ja',
 '32': 'Jendouba', '33': 'Kef', '34': 'Siliana', '41': 'Kairouan',
 '42': 'Kasserine', '43': 'Sidi Bouzid', '51': 'Sousse', '52': 'Monastir',
 '53': 'Mahdia', '61': 'Sfax', '71': 'Gafsa', '72': 'Tozeur',
 '73': 'Kebili', '81': 'Gab\u00e8s', '82': 'Medenine', '83': 'Tataouine'}
for code, name in iso.items():
    got = [r for r in rows if r['type'] == 'governorate' and r['code'] == code]
    check(f'gov-{code}', len(got) == 1 and got[0]['name'] == name
          and got[0]['level'] == '1' and not got[0]['parent_source_id'],
          str(got))
# Full governorate -> delegations table per Delegations-of-Tunisia oracle.
table = {
 # 11 Tunis (21)
 'tn:governorate:tunis': ['Bab El Bhar', 'Bab Souika', 'Carthage', 
    'Cité El Khadra', 'Djebel Jelloud', 'El Hraïria', 'El Kabaria', 
    'El Menzah', 'El Omrane', 'El Omrane Supérieur', 'El Ouardia', 
    'Ettahrir', 'Ezzouhour', 'La Goulette', 'La Marsa', 'Le Bardo', 
    'Le Kram', 'Medina', 'Sidi El Béchir', 'Sidi Hassine', 'Séjoumi'],
 # 12 Ariana (7)
 'tn:governorate:ariana': ['Ariana Ville', 'Cité Ettadhamen', 
    'Kalâat El Andalous', 'La Soukra', 'Mnihla', 'Raoued', 'Sidi Thabet'],
 # 13 Ben Arous (12)
 'tn:governorate:ben-arous': ['Ben Arous', 'Bou Mhel El Bassatine', 
    'El Mourouj', 'Ezzahra', 'Fouchana', 'Hammam Chott', 'Hammam Lif', 
    'Medina Jedida', 'Mohamedia', 'Mornag', 'Mégrine', 'Radès'],
 # 14 Manouba (8)
 'tn:governorate:manouba': ['Borj El Amri', 'Djedeida', 'Douar Hicher', 
    'El Batan', 'Manouba', 'Mornaguia', 'Oued Ellil', 'Tebourba'],
 # 21 Nabeul (16)
 'tn:governorate:nabeul': ['Bou Argoub', 'Béni Khalled', 'Béni Khiar', 
    'Dar Châabane El Fehri', 'El Haouaria', 'El Mida', 'Grombalia', 
    'Hammam Ghezèze', 'Hammamet', 'Korba', 'Kélibia', 'Menzel Bouzelfa', 
    'Menzel Temime', 'Nabeul', 'Soliman', 'Takelsa'],
 # 22 Zaghouan (6)
 'tn:governorate:zaghouan': ['Bir Mcherga', 'El Fahs', 'Nadhour', 'Saouaf', 
    'Zaghouan', 'Zriba'],
 # 23 Bizerte (14)
 'tn:governorate:bizerte': ['Bizerte Nord', 'Bizerte Sud', 'El Alia', 
    'Ghar El Melh', 'Ghezala', 'Joumine', 'Mateur', 'Menzel Bourguiba', 
    'Menzel Jemil', 'Ras Jebel', 'Sejnane', 'Tinja', 'Utique', 'Zarzouna'],
 # 31 Béja (9)
 'tn:governorate:beja': ['Amdoun', 'Béja Nord', 'Béja Sud', 'Goubellat', 
    'Medjez El Bab', 'Nefza', 'Testour', 'Thibar', 'Téboursouk'],
 # 32 Jendouba (9)
 'tn:governorate:jendouba': ['Aïn Draham', 'Balta - Bou Aouane', 'Bou Salem', 
    'Fernana', 'Ghardimaou', 'Jendouba', 'Jendouba Nord', 'Oued Meliz', 
    'Tabarka'],
 # 33 Kef (12)
 'tn:governorate:kef': ['Dahmani', 'El Ksour', 'Jérissa', 'Kalaat Senan', 
    'Kalâat Khasba', 'Kef Est', 'Kef Ouest', 'Nebeur', 'Sakiet Sidi Youssef', 
    'Sers', 'Tajerouine', 'Touiref'],
 # 34 Siliana (11)
 'tn:governorate:siliana': ['Bargou', 'Bou Arada', 'El Aroussa', 'El Krib', 
    'Gaâfour', 'Kesra', 'Makthar', 'Rouhia', 'Sidi Bou Rouis', 
    'Siliana Nord', 'Siliana Sud'],
 # 41 Kairouan (13)
 'tn:governorate:kairouan': ['Aïn Djeloula', 'Bou Hajla', 'Chebika', 
    'Echrarda', 'El Alâa', 'Haffouz', 'Hajeb el Ayoun', 'Kairouan Nord', 
    'Kairouan Sud', 'Menzel Mehiri', 'Nasrallah', 'Oueslatia', 'Sbikha'],
 # 42 Kasserine (13)
 'tn:governorate:kasserine': ['El Ayoun', 'Ezzouhour', 'Foussana', 'Fériana', 
    'Hassi El Ferid', 'Haïdra', 'Jedelienne', 'Kasserine Nord', 
    'Kasserine Sud', 'Majel Bel Abbès', 'Sbeïtla', 'Sbiba', 'Thala'],
 # 43 Sidi Bouzid (14)
 'tn:governorate:sidi-bouzid': ['Bir El Hafey', 'Cebbala Ouled Asker', 
    'El Hichria', 'Essaïda', 'Jilma', 'Meknassy', 'Menzel Bouzaiane', 
    'Mezzouna', 'Ouled Haffouz', 'Regueb', 'Sidi Ali Ben Aoun', 
    'Sidi Bouzid Est', 'Sidi Bouzid Ouest', 'Souk Jedid'],
 # 51 Sousse (16)
 'tn:governorate:sousse': ['Akouda', 'Bouficha', 'Enfida', 'Hammam Sousse', 
    'Hergla', 'Kalâa Kebira', 'Kalâa Seghira', 'Kondar', "M'saken", 
    'Sidi Bou Ali', 'Sidi El Hani', 'Sousse Jawhara', 'Sousse Médina', 
    'Sousse Riadh', 'Sousse Sidi Abdelhamid', 'Zaouiet - Ksibet Thrayet'],
 # 52 Monastir (13)
 'tn:governorate:monastir': ['Bekalta', 'Bembla', 'Beni Hassen', 'Jemmal', 
    'Ksar Hellal', 'Ksibet El Médiouni', 'Moknine', 'Monastir', 'Ouerdanine', 
    'Sahline', 'Sayada - Lamta - Bouhjar', 'Téboulba', 'Zéramdine'],
 # 53 Mahdia (13)
 'tn:governorate:mahdia': ['Bou Merdes', 'Chebba', 'Chorbane', 'El Bradâa', 
    'El Jem', 'Essouassi', 'Hebira', 'Ksour Essef', 'Mahdia', 'Melloulèche', 
    'Ouled Chamekh', 'Rejiche', 'Sidi Alouane'],
 # 61 Sfax (16)
 'tn:governorate:sfax': ['Agareb', 'Bir Ali Ben Khalifa', 'El Amra', 
    'El Hencha', 'Graïba', 'Jebiniana', 'Kerkennah', 'Mahrès', 
    'Menzel Chaker', 'Sakiet Eddaïer', 'Sakiet Ezzit', 'Sfax Ouest', 
    'Sfax Sud', 'Sfax Ville', 'Skhira', 'Thyna'],
 # 71 Gafsa (13)
 'tn:governorate:gafsa': ['Belkhir', 'El Guettar', 'El Ksar', 'Gafsa Nord', 
    'Gafsa Sud', 'Mdhila', 'Moularès', 'Métlaoui', 'Redeyef', 'Sened', 
    'Sidi Aïch', 'Sidi Boubaker', 'Zannouch'],
 # 72 Tozeur (6)
 'tn:governorate:tozeur': ['Degache', 'El Hamma du Jérid', 'Hazoua', 'Nefta', 
    'Tamerza', 'Tozeur'],
 # 73 Kebili (7)
 'tn:governorate:kebili': ['Douz Nord', 'Douz Sud', 'Faouar', 'Kébili Nord', 
    'Kébili Sud', 'Rjim Maatoug', 'Souk Lahad'],
 # 81 Gabès (13)
 'tn:governorate:gabes': ['Dkhilet Toujane', 'El Hamma', 'Gabès Médina', 
    'Gabès Ouest', 'Gabès Sud', 'Ghannouch', 'Habib Thameur Bouatouch', 
    'Mareth', 'Matmata', 'Menzel El Habib', 'Métouia', 'Nouvelle Matmata', 
    'Oudhref'],
 # 82 Medenine (9)
 'tn:governorate:medenine': ['Ben Gardane', 'Beni Khedache', 'Djerba Ajim', 
    'Djerba Houmt Souk', 'Djerba Midoun', 'Médenine Nord', 'Médenine Sud', 
    'Sidi Makhlouf', 'Zarzis'],
 # 83 Tataouine (8)
 'tn:governorate:tataouine': ['Beni Mehira', 'Bir Lahmar', 'Dehiba', 
    'Ghomrassen', 'Remada', 'Smâr', 'Tataouine Nord', 'Tataouine Sud'],
}
ok = True
for gov, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == gov)
    if have != sorted(names):
        print('FAIL gov', gov, have); fails.append(f'gov {gov}'); ok = False
if ok: print('PASS all 24 governorate mappings (279 delegations)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 969 codes / 982 links (post-fill) ---
check('codes-969', len(codes) == 969, str(len(codes)))
check('links-982', len(links) == 982, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{4}$', c['code'])]
check('code-format-4n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
cset = {c['code'] for c in codes}
lset = {l['postcode'] for l in links}
check('codes-linked-both-ways', cset == lset,
      f'unlinked={sorted(cset - lset)[:3]} dangling={sorted(lset - cset)[:3]}')
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 969 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
multis = sorted(pc for pc, c in counts.items() if c > 1)
check('multis-11', multis == ['1002', '1008', '2035', '2052', '2089',
      '3200', '4100', '7029', '7050', '8014', '8100'], str(multis))
# Full multi map (9 adjudicated keeps + 2 fills; votes in verdict.md).
exp = {
 '1002': [('tn:delegation:cite-el-khadra', 'true'),
          ('tn:delegation:bab-el-bhar', 'false'),
          ('tn:delegation:el-menzah', 'false')],
 '1008': [('tn:delegation:medina', 'true'),
          ('tn:delegation:sidi-el-bechir', 'false')],
 '2035': [('tn:delegation:la-soukra', 'true'),
          ('tn:delegation:cite-el-khadra', 'false')],
 '2052': [('tn:delegation:carthage', 'true'),
          ('tn:delegation:el-hrairia', 'false')],
 '2089': [('tn:delegation:le-kram', 'true'),
          ('tn:delegation:la-goulette', 'false')],
 '3200': [('tn:delegation:tataouine-sud', 'true'),
          ('tn:delegation:tataouine-nord', 'false'),
          ('tn:delegation:smar', 'false')],
 '4100': [('tn:delegation:medenine-sud', 'true'),
          ('tn:delegation:medenine-nord', 'false')],
 '7029': [('tn:delegation:bizerte-nord', 'true'),
          ('tn:delegation:bizerte-sud', 'false')],
 '7050': [('tn:delegation:menzel-bourguiba', 'true'),
          ('tn:delegation:mateur', 'false')],
 '8014': [('tn:delegation:beni-khalled', 'true'),
          ('tn:delegation:grombalia', 'false')],
 '8100': [('tn:delegation:jendouba', 'true'),
          ('tn:delegation:jendouba-nord', 'false')],
}
got = {}
for l in links:
    if l['postcode'] in exp:
        got.setdefault(l['postcode'], []).append((l['area_source_id'], l['is_primary']))
okm = all(sorted(got.get(pc, [])) == sorted(v) for pc, v in exp.items())
check('multi-map-11', okm, str({k: got.get(k) for k in exp if sorted(got.get(k, [])) != sorted(exp[k])}))
pin = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
# UPU tunEn anchors: 1002 Belvedere, 8170 Bou Salem, 3100 Kairouan.
check('pin-1002-khadra', pin.get('1002') == 'tn:delegation:cite-el-khadra')
check('pin-8170-bousalem', pin.get('8170') == 'tn:delegation:bou-salem')
check('pin-3100-kairouan-sud', pin.get('3100') == 'tn:delegation:kairouan-sud')
# Seat fills: 3000 Sfax Ville, 4000 Sousse Medina, 8000 Nabeul.
check('pin-3000-sfax-ville', pin.get('3000') == 'tn:delegation:sfax-ville')
check('pin-4000-sousse-medina', pin.get('4000') == 'tn:delegation:sousse-medina')
check('pin-8000-nabeul', pin.get('8000') == 'tn:delegation:nabeul')
# Namesake fills (La Poste roster + Nominatim echoes).
check('pin-2040-rades', pin.get('2040') == 'tn:delegation:rades')
check('pin-7070-ras-jebel', pin.get('7070') == 'tn:delegation:ras-jebel')
check('pin-1095-sidi-hassine', pin.get('1095') == 'tn:delegation:sidi-hassine')
check('pin-1250-sbeitla', pin.get('1250') == 'tn:delegation:sbeitla')
check('pin-1270-sbiba', pin.get('1270') == 'tn:delegation:sbiba')
check('pin-3110-sbikha', pin.get('3110') == 'tn:delegation:sbikha')
check('pin-7150-tajerouine', pin.get('7150') == 'tn:delegation:tajerouine')
check('pin-8020-soliman', pin.get('8020') == 'tn:delegation:soliman')
check('pin-8080-menzel-temime', pin.get('8080') == 'tn:delegation:menzel-temime')
check('pin-4023-sousse-riadh', pin.get('4023') == 'tn:delegation:sousse-riadh')
# OSM post-office nodes: 3023/3071 Sfax Ouest; singleton anchors.
check('pin-3023-sfax-ouest', pin.get('3023') == 'tn:delegation:sfax-ouest')
check('pin-3071-sfax-ouest', pin.get('3071') == 'tn:delegation:sfax-ouest')
check('pin-2097-boumhel', pin.get('2097') == 'tn:delegation:bou-mhel-el-bassatine')
check('pin-4215-douz-sud', pin.get('4215') == 'tn:delegation:douz-sud')
check('pin-2087-hrairia', pin.get('2087') == 'tn:delegation:el-hrairia')
check('pin-1142-borj-amri', pin.get('1142') == 'tn:delegation:borj-el-amri')
check('pin-6013-el-hamma', pin.get('6013') == 'tn:delegation:el-hamma')
# Deliberate absences: UPU-only 8129 + 7 official-newer single-signals.
check('no-8129', '8129' not in cset)
check('no-newer-7', not ({'1049', '2001', '2002', '2079', '4050',
      '4083', '4162'} & cset))
# Per-area primary counts (258 covered L2; 21 structural codeless).
exp_counts = {
 'tn:delegation:ariana-ville': 5,
 'tn:delegation:cite-ettadhamen': 1,
 'tn:delegation:kalaat-el-andalous': 2,
 'tn:delegation:la-soukra': 3,
 'tn:delegation:mnihla': 1,
 'tn:delegation:raoued': 4,
 'tn:delegation:sidi-thabet': 3,
 'tn:delegation:amdoun': 2,
 'tn:delegation:beja-nord': 2,
 'tn:delegation:beja-sud': 4,
 'tn:delegation:goubellat': 2,
 'tn:delegation:medjez-el-bab': 9,
 'tn:delegation:nefza': 3,
 'tn:delegation:teboursouk': 3,
 'tn:delegation:testour': 4,
 'tn:delegation:thibar': 2,
 'tn:delegation:ben-arous': 2,
 'tn:delegation:bou-mhel-el-bassatine': 1,
 'tn:delegation:el-mourouj': 2,
 'tn:delegation:ezzahra': 2,
 'tn:delegation:fouchana': 2,
 'tn:delegation:hammam-chott': 3,
 'tn:delegation:hammam-lif': 3,
 'tn:delegation:medina-jedida': 2,
 'tn:delegation:megrine': 3,
 'tn:delegation:mohamedia': 1,
 'tn:delegation:mornag': 4,
 'tn:delegation:rades': 4,
 'tn:delegation:bizerte-nord': 7,
 'tn:delegation:bizerte-sud': 7,
 'tn:delegation:el-alia': 3,
 'tn:delegation:ghar-el-melh': 3,
 'tn:delegation:ghezala': 1,
 'tn:delegation:joumine': 2,
 'tn:delegation:mateur': 2,
 'tn:delegation:menzel-bourguiba': 3,
 'tn:delegation:menzel-jemil': 3,
 'tn:delegation:ras-jebel': 7,
 'tn:delegation:sejnane': 3,
 'tn:delegation:tinja': 1,
 'tn:delegation:utique': 5,
 'tn:delegation:zarzouna': 1,
 'tn:delegation:dkhilet-toujane': 0,
 'tn:delegation:el-hamma': 7,
 'tn:delegation:gabes-medina': 6,
 'tn:delegation:gabes-ouest': 3,
 'tn:delegation:gabes-sud': 7,
 'tn:delegation:ghannouch': 1,
 'tn:delegation:habib-thameur-bouatouch': 0,
 'tn:delegation:mareth': 12,
 'tn:delegation:matmata': 3,
 'tn:delegation:menzel-el-habib': 1,
 'tn:delegation:metouia': 4,
 'tn:delegation:nouvelle-matmata': 4,
 'tn:delegation:oudhref': 0,
 'tn:delegation:belkhir': 3,
 'tn:delegation:el-guettar': 5,
 'tn:delegation:el-ksar': 4,
 'tn:delegation:gafsa-nord': 2,
 'tn:delegation:gafsa-sud': 7,
 'tn:delegation:mdhila': 2,
 'tn:delegation:metlaoui': 4,
 'tn:delegation:moulares': 3,
 'tn:delegation:redeyef': 3,
 'tn:delegation:sened': 5,
 'tn:delegation:sidi-aich': 1,
 'tn:delegation:sidi-boubaker': 0,
 'tn:delegation:zannouch': 0,
 'tn:delegation:ain-draham': 5,
 'tn:delegation:balta-bou-aouane': 2,
 'tn:delegation:bou-salem': 4,
 'tn:delegation:fernana': 5,
 'tn:delegation:ghardimaou': 3,
 'tn:delegation:jendouba': 7,
 'tn:delegation:jendouba-nord': 3,
 'tn:delegation:oued-meliz': 4,
 'tn:delegation:tabarka': 6,
 'tn:delegation:ain-djeloula': 0,
 'tn:delegation:bou-hajla': 4,
 'tn:delegation:chebika': 5,
 'tn:delegation:echrarda': 2,
 'tn:delegation:el-alaa': 3,
 'tn:delegation:haffouz': 2,
 'tn:delegation:hajeb-el-ayoun': 1,
 'tn:delegation:kairouan-nord': 6,
 'tn:delegation:kairouan-sud': 8,
 'tn:delegation:menzel-mehiri': 0,
 'tn:delegation:nasrallah': 5,
 'tn:delegation:oueslatia': 3,
 'tn:delegation:sbikha': 7,
 'tn:delegation:el-ayoun': 2,
 'tn:delegation:ezzouhour': 1,
 'tn:delegation:feriana': 6,
 'tn:delegation:foussana': 4,
 'tn:delegation:haidra': 3,
 'tn:delegation:hassi-el-ferid': 3,
 'tn:delegation:jedelienne': 2,
 'tn:delegation:kasserine-nord': 4,
 'tn:delegation:kasserine-sud': 1,
 'tn:delegation:majel-bel-abbes': 4,
 'tn:delegation:sbeitla': 7,
 'tn:delegation:sbiba': 3,
 'tn:delegation:thala': 6,
 'tn:delegation:douz-nord': 0,
 'tn:delegation:douz-sud': 6,
 'tn:delegation:faouar': 2,
 'tn:delegation:kebili-nord': 9,
 'tn:delegation:kebili-sud': 8,
 'tn:delegation:rjim-maatoug': 0,
 'tn:delegation:souk-lahad': 7,
 'tn:delegation:dahmani': 1,
 'tn:delegation:el-ksour': 2,
 'tn:delegation:jerissa': 1,
 'tn:delegation:kalaat-khasba': 2,
 'tn:delegation:kalaat-senan': 3,
 'tn:delegation:kef-est': 6,
 'tn:delegation:kef-ouest': 1,
 'tn:delegation:nebeur': 4,
 'tn:delegation:sakiet-sidi-youssef': 2,
 'tn:delegation:sers': 1,
 'tn:delegation:tajerouine': 5,
 'tn:delegation:touiref': 0,
 'tn:delegation:bou-merdes': 3,
 'tn:delegation:chebba': 1,
 'tn:delegation:chorbane': 3,
 'tn:delegation:el-bradaa': 0,
 'tn:delegation:el-jem': 3,
 'tn:delegation:essouassi': 5,
 'tn:delegation:hebira': 3,
 'tn:delegation:ksour-essef': 7,
 'tn:delegation:mahdia': 9,
 'tn:delegation:mellouleche': 2,
 'tn:delegation:ouled-chamekh': 2,
 'tn:delegation:rejiche': 0,
 'tn:delegation:sidi-alouane': 5,
 'tn:delegation:borj-el-amri': 2,
 'tn:delegation:djedeida': 4,
 'tn:delegation:douar-hicher': 1,
 'tn:delegation:el-batan': 1,
 'tn:delegation:manouba': 2,
 'tn:delegation:mornaguia': 5,
 'tn:delegation:oued-ellil': 2,
 'tn:delegation:tebourba': 4,
 'tn:delegation:ben-gardane': 6,
 'tn:delegation:beni-khedache': 6,
 'tn:delegation:djerba-ajim': 5,
 'tn:delegation:djerba-houmt-souk': 13,
 'tn:delegation:djerba-midoun': 9,
 'tn:delegation:medenine-nord': 4,
 'tn:delegation:medenine-sud': 5,
 'tn:delegation:sidi-makhlouf': 3,
 'tn:delegation:zarzis': 12,
 'tn:delegation:bekalta': 3,
 'tn:delegation:bembla': 5,
 'tn:delegation:beni-hassen': 3,
 'tn:delegation:jemmal': 7,
 'tn:delegation:ksar-hellal': 2,
 'tn:delegation:ksibet-el-mediouni': 4,
 'tn:delegation:moknine': 8,
 'tn:delegation:monastir': 6,
 'tn:delegation:ouerdanine': 3,
 'tn:delegation:sahline': 3,
 'tn:delegation:sayada-lamta-bouhjar': 3,
 'tn:delegation:teboulba': 2,
 'tn:delegation:zeramdine': 4,
 'tn:delegation:beni-khalled': 3,
 'tn:delegation:beni-khiar': 4,
 'tn:delegation:bou-argoub': 3,
 'tn:delegation:dar-chaabane-el-fehri': 2,
 'tn:delegation:el-haouaria': 6,
 'tn:delegation:el-mida': 3,
 'tn:delegation:grombalia': 7,
 'tn:delegation:hammam-ghezeze': 2,
 'tn:delegation:hammamet': 4,
 'tn:delegation:kelibia': 6,
 'tn:delegation:korba': 6,
 'tn:delegation:menzel-bouzelfa': 2,
 'tn:delegation:menzel-temime': 6,
 'tn:delegation:nabeul': 2,
 'tn:delegation:soliman': 5,
 'tn:delegation:takelsa': 1,
 'tn:delegation:agareb': 4,
 'tn:delegation:bir-ali-ben-khalifa': 4,
 'tn:delegation:el-amra': 5,
 'tn:delegation:el-hencha': 4,
 'tn:delegation:graiba': 2,
 'tn:delegation:jebiniana': 6,
 'tn:delegation:kerkennah': 6,
 'tn:delegation:mahres': 2,
 'tn:delegation:menzel-chaker': 3,
 'tn:delegation:sakiet-eddaier': 7,
 'tn:delegation:sakiet-ezzit': 4,
 'tn:delegation:sfax-ouest': 5,
 'tn:delegation:sfax-sud': 12,
 'tn:delegation:sfax-ville': 13,
 'tn:delegation:skhira': 2,
 'tn:delegation:thyna': 0,
 'tn:delegation:bir-el-hafey': 6,
 'tn:delegation:cebbala-ouled-asker': 1,
 'tn:delegation:el-hichria': 0,
 'tn:delegation:essaida': 0,
 'tn:delegation:jilma': 1,
 'tn:delegation:meknassy': 5,
 'tn:delegation:menzel-bouzaiane': 3,
 'tn:delegation:mezzouna': 3,
 'tn:delegation:ouled-haffouz': 3,
 'tn:delegation:regueb': 5,
 'tn:delegation:sidi-ali-ben-aoun': 0,
 'tn:delegation:sidi-bouzid-est': 5,
 'tn:delegation:sidi-bouzid-ouest': 5,
 'tn:delegation:souk-jedid': 2,
 'tn:delegation:bargou': 3,
 'tn:delegation:bou-arada': 1,
 'tn:delegation:el-aroussa': 2,
 'tn:delegation:el-krib': 4,
 'tn:delegation:gaafour': 5,
 'tn:delegation:kesra': 4,
 'tn:delegation:makthar': 3,
 'tn:delegation:rouhia': 2,
 'tn:delegation:sidi-bou-rouis': 3,
 'tn:delegation:siliana-nord': 2,
 'tn:delegation:siliana-sud': 4,
 'tn:delegation:akouda': 2,
 'tn:delegation:bouficha': 3,
 'tn:delegation:enfida': 5,
 'tn:delegation:hammam-sousse': 3,
 'tn:delegation:hergla': 1,
 'tn:delegation:kalaa-kebira': 3,
 'tn:delegation:kalaa-seghira': 1,
 'tn:delegation:kondar': 1,
 'tn:delegation:m-saken': 8,
 'tn:delegation:sidi-bou-ali': 3,
 'tn:delegation:sidi-el-hani': 2,
 'tn:delegation:sousse-jawhara': 4,
 'tn:delegation:sousse-medina': 2,
 'tn:delegation:sousse-riadh': 4,
 'tn:delegation:sousse-sidi-abdelhamid': 0,
 'tn:delegation:zaouiet-ksibet-thrayet': 0,
 'tn:delegation:beni-mehira': 0,
 'tn:delegation:bir-lahmar': 1,
 'tn:delegation:dehiba': 1,
 'tn:delegation:ghomrassen': 7,
 'tn:delegation:remada': 4,
 'tn:delegation:smar': 4,
 'tn:delegation:tataouine-nord': 7,
 'tn:delegation:tataouine-sud': 12,
 'tn:delegation:degache': 6,
 'tn:delegation:el-hamma-du-jerid': 0,
 'tn:delegation:hazoua': 1,
 'tn:delegation:nefta': 2,
 'tn:delegation:tamerza': 3,
 'tn:delegation:tozeur': 7,
 'tn:delegation:bab-el-bhar': 4,
 'tn:delegation:bab-souika': 3,
 'tn:delegation:carthage': 6,
 'tn:delegation:cite-el-khadra': 2,
 'tn:delegation:djebel-jelloud': 2,
 'tn:delegation:el-hrairia': 2,
 'tn:delegation:el-kabaria': 3,
 'tn:delegation:el-menzah': 4,
 'tn:delegation:el-omrane': 1,
 'tn:delegation:el-omrane-superieur': 4,
 'tn:delegation:el-ouardia': 1,
 'tn:delegation:ettahrir': 1,
 'tn:delegation:la-goulette': 2,
 'tn:delegation:la-marsa': 6,
 'tn:delegation:le-bardo': 3,
 'tn:delegation:le-kram': 2,
 'tn:delegation:medina': 3,
 'tn:delegation:sejoumi': 2,
 'tn:delegation:sidi-el-bechir': 2,
 'tn:delegation:sidi-hassine': 1,
 'tn:delegation:tunis:ezzouhour': 0,
 'tn:delegation:bir-mcherga': 5,
 'tn:delegation:el-fahs': 4,
 'tn:delegation:nadhour': 3,
 'tn:delegation:saouaf': 1,
 'tn:delegation:zaghouan': 4,
 'tn:delegation:zriba': 3,
}
have = Counter(l['area_source_id'] for l in links if l['is_primary'] == 'true')
wrong = [sid for sid, n in exp_counts.items() if have.get(sid, 0) != n]
check('per-area-counts-279', not wrong, str(wrong[:5]))
check('covered-258', sum(1 for sid in exp_counts if have.get(sid, 0) > 0) == 258)
codeless = sorted(sid for sid in exp_counts if have.get(sid, 0) == 0)
check('codeless-21', codeless == ['tn:delegation:ain-djeloula',
      'tn:delegation:beni-mehira', 'tn:delegation:dkhilet-toujane',
      'tn:delegation:douz-nord', 'tn:delegation:el-bradaa',
      'tn:delegation:el-hamma-du-jerid', 'tn:delegation:el-hichria',
      'tn:delegation:essaida', 'tn:delegation:habib-thameur-bouatouch',
      'tn:delegation:menzel-mehiri', 'tn:delegation:oudhref',
      'tn:delegation:rejiche', 'tn:delegation:rjim-maatoug',
      'tn:delegation:sidi-ali-ben-aoun', 'tn:delegation:sidi-boubaker',
      'tn:delegation:sousse-sidi-abdelhamid', 'tn:delegation:thyna',
      'tn:delegation:touiref', 'tn:delegation:tunis:ezzouhour',
      'tn:delegation:zannouch',
      'tn:delegation:zaouiet-ksibet-thrayet'], str(codeless))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
