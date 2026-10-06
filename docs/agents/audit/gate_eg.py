import csv, os, sys
from collections import Counter
# Egypt gate. M7 revisit: tree verify-only, zero data changes; postcode
# scope adjudicated as a live-system gap (NOT codeless, NOT buildable).
# Tree == HDX COD-AB egy_admin_boundaries.xlsx (CAPMAS 20170421) 365/365
# rows: p-codes, names, per-governorate counts all exact, incl. the 14
# genuine EGNN00 Zemam residuals the derived GitHub set drops. L1 = 27 ISO
# 3166-2:EG codes with common-English display names (COD keeps its own
# L1 spellings: Sharkia/Kalyoubia/Behera/Menia/Assiut/Suhag/Fayoum).
# Postcode: UPU require-list Aug-2026 carries Egypt with DUAL length
# entries (5 + 7; formats 99999 + 9999999); egyEn profile 07/2023
# documents live 7-digit PP/L/NN/CC below locality (3759914 Giza,
# 1062261 New Valley) while addressed usage is still 5-digit (Mohandessin
# 12655, Maadi 11728) - a transition, both recorded. No 2-signal
# allocation source exists (details in verdict.md), so no overlay ships;
# the -yet checks below force any future build to update this gate.
# Run from repo root: python3 /tmp/geo-verify/M7/EG/gate_eg.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/egypt-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/egypt-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/egypt-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-392', len(rows) == 392, str(len(rows)))
check('governorates-27', sum(1 for r in rows if r['type'] == 'governorate') == 27)
check('districts-365', sum(1 for r in rows if r['type'] == 'district') == 365)
# ISO 3166-2:EG 27/27 (common-English display names).
iso = {
 'eg:governorate:alexandria': ('Alexandria', 'ALX'),
 'eg:governorate:aswan': ('Aswan', 'ASN'),
 'eg:governorate:asyut': ('Asyut', 'AST'),
 'eg:governorate:red-sea': ('Red Sea', 'BA'),
 'eg:governorate:beheira': ('Beheira', 'BH'),
 'eg:governorate:beni-suef': ('Beni Suef', 'BNS'),
 'eg:governorate:cairo': ('Cairo', 'C'),
 'eg:governorate:dakahlia': ('Dakahlia', 'DK'),
 'eg:governorate:damietta': ('Damietta', 'DT'),
 'eg:governorate:faiyum': ('Faiyum', 'FYM'),
 'eg:governorate:gharbia': ('Gharbia', 'GH'),
 'eg:governorate:giza': ('Giza', 'GZ'),
 'eg:governorate:ismailia': ('Ismailia', 'IS'),
 'eg:governorate:south-sinai': ('South Sinai', 'JS'),
 'eg:governorate:qalyubia': ('Qalyubia', 'KB'),
 'eg:governorate:kafr-el-sheikh': ('Kafr El-Sheikh', 'KFS'),
 'eg:governorate:qena': ('Qena', 'KN'),
 'eg:governorate:luxor': ('Luxor', 'LX'),
 'eg:governorate:minya': ('Minya', 'MN'),
 'eg:governorate:monufia': ('Monufia', 'MNF'),
 'eg:governorate:matrouh': ('Matrouh', 'MT'),
 'eg:governorate:port-said': ('Port Said', 'PTS'),
 'eg:governorate:sohag': ('Sohag', 'SHG'),
 'eg:governorate:sharqia': ('Sharqia', 'SHR'),
 'eg:governorate:north-sinai': ('North Sinai', 'SIN'),
 'eg:governorate:suez': ('Suez', 'SUZ'),
 'eg:governorate:new-valley': ('New Valley', 'WAD'),
}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and not r['parent_source_id'], str(r))
# COD-AB EGNN00 adm1-pcode -> ISO governorate mapping (27).
pcmap = {'EG01': 'C', 'EG02': 'ALX', 'EG03': 'PTS', 'EG04': 'SUZ',
         'EG11': 'DT', 'EG12': 'DK', 'EG13': 'SHR', 'EG14': 'KB',
         'EG15': 'KFS', 'EG16': 'GH', 'EG17': 'MNF', 'EG18': 'BH',
         'EG19': 'IS', 'EG21': 'GZ', 'EG22': 'BNS', 'EG23': 'FYM',
         'EG24': 'MN', 'EG25': 'AST', 'EG26': 'SHG', 'EG27': 'KN',
         'EG28': 'ASN', 'EG29': 'LX', 'EG31': 'BA', 'EG32': 'WAD',
         'EG33': 'MT', 'EG34': 'SIN', 'EG35': 'JS'}
badmap = []
for r in rows:
    if r['level'] != '2': continue
    g = byid.get(r['parent_source_id'], {})
    if pcmap.get(r['code'][:4]) != g.get('code'): badmap.append(r['code'])
check('pcode-parent-map-365', not badmap, str(badmap[:5]))
# Full governorate -> districts table per COD-AB (pcode, name).
table = {
 # ALX Alexandria (19)
 'eg:governorate:alexandria': [('EG0200', 'Zemam Out'), ('EG0201', 'Muntazah'), ('EG0202', 'Al Raml'), ('EG0203', 'Sidi Gabir'), ('EG0204', 'Bab Sharqi'), ('EG0205', 'Muharam Bik'), ('EG0206', 'Al Attarin'), ('EG0207', 'Al Manshiyya'), ('EG0208', 'Karmuz'), ('EG0209', 'A L Labban'), ('EG0210', 'Al Gumruk'), ('EG0211', 'Mina Al-Basal'), ('EG0212', 'Al Dikhila'), ('EG0213', 'Al Amreia'), ('EG0214', 'Burg al-Arab'), ('EG0215', 'Port Alexandria Police Department'), ('EG0216', 'Burg Al-Arab City'), ('EG0217', 'Kesm than Al Raml'), ('EG0218', 'North Coast')],
 # ASN Aswan (10)
 'eg:governorate:aswan': [('EG2800', 'Zemam Out'), ('EG2801', 'Aswan'), ('EG2802', 'Aswan'), ('EG2803', 'Adfu'), ('EG2804', 'Kum Umbu'), ('EG2805', 'Nasr'), ('EG2806', 'Daraw'), ('EG2807', 'Abu Simbel'), ('EG2808', 'Aswan City'), ('EG2809', 'Tushaka')],
 # AST Asyut (15)
 'eg:governorate:asyut': [('EG2500', 'Zemam Out'), ('EG2501', 'Kesm Awal Assuit'), ('EG2502', 'Kesm Than Assuit'), ('EG2503', 'Assuit'), ('EG2504', 'Abnub'), ('EG2505', 'Abu Tig'), ('EG2506', 'Al- Badari'), ('EG2507', 'Sahil Silim'), ('EG2508', 'Al-Ghanayem'), ('EG2509', 'Al-Qusia'), ('EG2510', 'Dayrut'), ('EG2511', 'Sidfa'), ('EG2512', 'Manfalut'), ('EG2513', 'Alfath'), ('EG2514', 'Assuit City')],
 # BA Red Sea (8)
 'eg:governorate:red-sea': [('EG3101', 'Hurghada 1'), ('EG3102', 'Qusir'), ('EG3103', 'Safaga'), ('EG3104', 'Marsa Alam'), ('EG3105', 'Ras Gharib'), ('EG3106', 'Shallatin'), ('EG3107', 'Halayib'), ('EG3108', 'Hurghada 2')],
 # BH Beheira (19)
 'eg:governorate:beheira': [('EG1800', 'Zemam Out'), ('EG1801', 'Damanhur'), ('EG1802', 'Damanhur'), ('EG1803', 'Abu-l-Matamir'), ('EG1804', 'Abu Hummus'), ('EG1805', 'Al-Dilingat'), ('EG1806', 'Al-Mahmudiyya'), ('EG1807', 'Itay Al-Barud'), ('EG1808', 'Hush Isa'), ('EG1809', 'Rashid'), ('EG1810', 'Shubra Khit'), ('EG1811', 'Kafr Al-Dawwar'), ('EG1812', 'Kafr Al-Dawwar'), ('EG1813', 'Kum Hamada'), ('EG1814', 'Wadi Al-NatrUn'), ('EG1815', 'Al-Rahmaniyya'), ('EG1816', 'Idku'), ('EG1817', 'Nubariyya West'), ('EG1818', 'Badr')],
 # BNS Beni Suef (10)
 'eg:governorate:beni-suef': [('EG2200', 'Zemam Out'), ('EG2201', 'Bani Swayf'), ('EG2202', 'Bani Swayf'), ('EG2203', 'Bani Swayf City'), ('EG2204', 'Al Fashn'), ('EG2205', 'Al Wasta'), ('EG2206', 'Ahnasya'), ('EG2207', 'Biba'), ('EG2208', 'Sumusta'), ('EG2209', 'Nasir')],
 # C Cairo (42)
 'eg:governorate:cairo': [('EG0100', 'Zemam Out'), ('EG0101', 'Al Tibbin'), ('EG0102', 'Hilwan'), ('EG0103', '15 Mayu'), ('EG0104', 'Maadi'), ('EG0105', 'Tura'), ('EG0106', 'Misr Al-Qadima'), ('EG0107', 'Sayyida Zainab'), ('EG0108', 'Al Khalifa'), ('EG0109', 'Abdin'), ('EG0110', 'Muski'), ('EG0111', 'Qasr Al-Nile'), ('EG0112', 'Bulaq'), ('EG0113', 'Al Azbakiyya'), ('EG0114', 'Al Darb al-Ahmar'), ('EG0115', 'Gamaliyya'), ('EG0116', 'Bab Al-Shariyya'), ('EG0117', 'Al Zahir'), ('EG0118', 'Al Sharabiyya'), ('EG0119', 'Shubra'), ('EG0120', 'Rud Al-Farag'), ('EG0121', 'Al Sahil'), ('EG0122', 'Al Wayli'), ('EG0123', 'Hadaiq Al-Qubba'), ('EG0124', 'Al Zaytun'), ('EG0125', 'Al Matariyya'), ('EG0126', 'Nasr City'), ('EG0127', 'Madinat Nasr-2'), ('EG0128', 'Misr al-Gadida'), ('EG0129', 'Nuzha'), ('EG0130', 'Ain Shams'), ('EG0131', 'Zawiyya Al-Hamra'), ('EG0132', 'Al Salam'), ('EG0133', 'Zamalik'), ('EG0134', 'Minshat Nasir'), ('EG0135', 'Basatin'), ('EG0136', 'Marg'), ('EG0137', 'New Cairo-1'), ('EG0138', 'New Cairo-2'), ('EG0139', 'New Cairo-3'), ('EG0140', 'Shroq'), ('EG0141', 'Badr')],
 # DK Dakahlia (21)
 'eg:governorate:dakahlia': [('EG1201', 'El Mansora 1'), ('EG1202', 'E Mansora 2'), ('EG1203', 'El Mansora'), ('EG1204', 'Aga'), ('EG1205', 'Sinbillawin'), ('EG1206', 'Matariyya'), ('EG1207', 'Manzala'), ('EG1208', 'Bilqas'), ('EG1209', 'Dikirnis'), ('EG1210', 'Shirbin'), ('EG1211', 'Talkha'), ('EG1212', 'Mit Ghamr'), ('EG1213', 'Mit Ghamr'), ('EG1214', 'Minya Al-Nasr'), ('EG1215', 'Gamaliyya'), ('EG1216', 'Tamy Al-Amdid'), ('EG1217', 'MIt Salsil'), ('EG1218', 'Bany Abeed'), ('EG1219', 'Mahalet Demna'), ('EG1220', 'Gamsa'), ('EG1221', 'Nebro')],
 # DT Damietta (9)
 'eg:governorate:damietta': [('EG1101', 'Dumyat 1'), ('EG1102', 'Dumyat'), ('EG1103', 'Fariskur'), ('EG1104', 'Kafr Sad'), ('EG1105', 'Dumyat Al-Gadida'), ('EG1106', 'Ras al-Bar'), ('EG1107', 'Zarqa'), ('EG1108', 'Police Department Port of Damietta'), ('EG1109', 'Dumyat 2')],
 # FYM Faiyum (9)
 'eg:governorate:faiyum': [('EG2300', 'Zemam Out'), ('EG2301', 'Fayyum'), ('EG2302', 'Fayyum'), ('EG2303', 'Abshaway'), ('EG2304', 'Atsa'), ('EG2305', 'Sinuras'), ('EG2306', 'Tamya'), ('EG2307', 'Yousef El sadeq'), ('EG2308', 'Fayyum City')],
 # GH Gharbia (12)
 'eg:governorate:gharbia': [('EG1601', 'Tanta 1'), ('EG1602', 'Tanta 2'), ('EG1603', 'Tanta'), ('EG1604', 'Santa'), ('EG1605', 'El Mahalla El Kobra 1'), ('EG1606', 'El Mahalla El Kobra 2'), ('EG1607', 'El Mahalla El Kobra'), ('EG1608', 'Basyun'), ('EG1609', 'Zifta'), ('EG1610', 'Samannud'), ('EG1611', 'Qutur'), ('EG1612', 'Kafr Al-Zayyat')],
 # GZ Giza (22)
 'eg:governorate:giza': [('EG2100', 'Zemam Out'), ('EG2101', 'Imbaba'), ('EG2102', 'Al-Aguza'), ('EG2103', 'DuqqI'), ('EG2104', 'Giza'), ('EG2105', 'Bulaq Al-DakrUr'), ('EG2106', 'Al-Ahram'), ('EG2107', '6 October-1'), ('EG2108', 'Hwamdeia'), ('EG2109', 'Giza'), ('EG2110', 'Badrashain'), ('EG2111', 'Saf'), ('EG2112', 'Ayat'), ('EG2113', 'Imbaba'), ('EG2114', 'Bahariya Oasis'), ('EG2115', 'Atfeh'), ('EG2116', 'Auseem'), ('EG2117', 'Waraq'), ('EG2118', 'Umraniyya'), ('EG2119', 'Shaykh Zayed'), ('EG2120', 'Kardasa'), ('EG2121', '6 October-2')],
 # IS Ismailia (8)
 'eg:governorate:ismailia': [('EG1901', 'Ismailiyya 1'), ('EG1902', 'Ismailiyya 2'), ('EG1903', 'Ismailiyya 3'), ('EG1904', 'Ismailiyya'), ('EG1905', 'Tal al-Kabir, al-'), ('EG1906', 'Qantara Gharb, al-'), ('EG1907', 'Fayid'), ('EG1908', 'Qantara Sharq, al-')],
 # JS South Sinai (8)
 'eg:governorate:south-sinai': [('EG3501', 'Al-Tur'), ('EG3502', 'Ras Sidr'), ('EG3503', 'Abu Radis'), ('EG3504', 'Sant Katrin'), ('EG3505', 'Sharm el-Sheikh'), ('EG3506', 'Dahab'), ('EG3507', 'Nuweiba'), ('EG3508', 'Taba')],
 # KB Qalyubia (15)
 'eg:governorate:qalyubia': [('EG1400', 'Zemam Out'), ('EG1401', 'Banha'), ('EG1402', 'Banha'), ('EG1403', 'Al Khanka'), ('EG1404', 'Qanatir Al-Khayriyya'), ('EG1405', 'Shibin al-Qanatir'), ('EG1406', 'Shubra Al-Khayma 1'), ('EG1407', 'Shubra Al-Khayma 2'), ('EG1408', 'Tukh'), ('EG1409', 'Qalyub'), ('EG1410', 'Qalyub'), ('EG1411', 'Kafr Shukr'), ('EG1412', 'Khsos'), ('EG1413', 'Abour'), ('EG1414', 'Qaha')],
 # KFS Kafr El-Sheikh (12)
 'eg:governorate:kafr-el-sheikh': [('EG1501', 'Kafr Al-Shaykh'), ('EG1502', 'Kafr Al-Shaykh'), ('EG1503', 'Burullus'), ('EG1504', 'Biyalu'), ('EG1505', 'Disuq'), ('EG1506', 'Disuq'), ('EG1507', 'Sidi Salim'), ('EG1508', 'Fuwwa'), ('EG1509', 'Qillin'), ('EG1510', 'Mitubas'), ('EG1511', 'Al Hamul'), ('EG1512', 'Riyad')],
 # KN Qena (14)
 'eg:governorate:qena': [('EG2700', 'Zemam Out'), ('EG2701', 'Qina'), ('EG2702', 'Qina'), ('EG2703', 'Abu Tisht'), ('EG2704', 'Armant'), ('EG2705', 'Isna'), ('EG2706', 'Dishna'), ('EG2707', 'Qus'), ('EG2708', 'Nag Hammadi'), ('EG2709', 'Naqada'), ('EG2710', 'Farshut'), ('EG2711', 'Qift'), ('EG2712', 'Al Waqf'), ('EG2713', 'Qina City')],
 # LX Luxor (4)
 'eg:governorate:luxor': [('EG2900', 'Zemam Out'), ('EG2901', 'Luxor'), ('EG2902', 'Luxor'), ('EG2903', 'Tiba police station')],
 # MN Minya (13)
 'eg:governorate:minya': [('EG2400', 'Zemam Out'), ('EG2401', 'Kesm Al-minya'), ('EG2402', 'Markz Al Minya'), ('EG2403', 'New Minya'), ('EG2404', 'Markz Abu Qurqas'), ('EG2405', 'Markz Al Idwa'), ('EG2406', 'Markz Bani Mazar'), ('EG2407', 'Markz Dir Mawas'), ('EG2408', 'Markz Samalut'), ('EG2409', 'Markz Matay'), ('EG2410', 'Markz Maghagha'), ('EG2411', 'Kesm Mallawi'), ('EG2412', 'Markz Mallawi')],
 # MNF Monufia (12)
 'eg:governorate:monufia': [('EG1701', 'Shibin al-Kum'), ('EG1702', 'Shibin al-Kum'), ('EG1703', 'Ashmun'), ('EG1704', 'Al-Bagur'), ('EG1705', 'Al-Shuhada'), ('EG1706', 'Birkat Al-Sab'), ('EG1707', 'Tala'), ('EG1708', 'Quwisna'), ('EG1709', 'Minuf'), ('EG1710', 'Sirs Al-Layyana City'), ('EG1711', 'Sadat City'), ('EG1712', 'Minuf City')],
 # MT Matrouh (8)
 'eg:governorate:matrouh': [('EG3301', 'Marsa Matruh'), ('EG3302', 'Al-Hammam'), ('EG3303', 'Salloum'), ('EG3304', 'Daba'), ('EG3305', 'Sidi Barani'), ('EG3306', 'Siwa'), ('EG3307', 'Alamn'), ('EG3308', 'North Coast')],
 # PTS Port Said (12)
 'eg:governorate:port-said': [('EG0301', 'Al-Sharq'), ('EG0302', 'Al-Arab'), ('EG0303', 'Al-Munakh'), ('EG0304', 'Port Fuad'), ('EG0305', 'Al-Dawahy'), ('EG0306', 'Al-Ganoub'), ('EG0307', 'Al-Zohour'), ('EG0308', 'Port Fuad 2'), ('EG0309', 'Mubark-Sharq Tafrea'), ('EG0310', 'Al-Manasra'), ('EG0311', 'Al-Ganoub 2'), ('EG0312', 'Police Department Port Said Port')],
 # SHG Sohag (20)
 'eg:governorate:sohag': [('EG2600', 'Zemam Out'), ('EG2601', 'Suhag'), ('EG2602', 'Suhag-2'), ('EG2603', 'Suhag'), ('EG2604', 'Akhmim'), ('EG2605', 'Al-Balyana'), ('EG2606', 'Al-Maragha'), ('EG2607', 'Al-Minshat'), ('EG2608', 'Dar al-Salam'), ('EG2609', 'Girga'), ('EG2610', 'Girga'), ('EG2611', 'Guhayna Al-Gharbiyya'), ('EG2612', 'Saqulta'), ('EG2613', 'Tama'), ('EG2614', 'Tahta'), ('EG2615', 'Kesm Tahta'), ('EG2616', 'Kawther'), ('EG2617', 'Al Usayrat'), ('EG2618', 'Akhmim City'), ('EG2619', 'Suhag City')],
 # SHR Sharqia (22)
 'eg:governorate:sharqia': [('EG1300', 'Zemam Out'), ('EG1301', 'Zaqaziq 1'), ('EG1302', 'Zaqaziq 2'), ('EG1303', 'Zaqaziq'), ('EG1304', 'Abu Hammad'), ('EG1305', 'Abu Kabir'), ('EG1306', 'Al-Husayniya'), ('EG1307', 'Al-Salhiyya'), ('EG1308', 'Bilbis'), ('EG1309', '10 Ramadan 1'), ('EG1310', 'Dyarb Nigm'), ('EG1311', 'Faqus'), ('EG1312', 'Faqus'), ('EG1313', 'Kafr Saqr'), ('EG1314', 'Minya al-Qamh'), ('EG1315', 'Hihya'), ('EG1316', 'Mashtul Al-Suq'), ('EG1317', 'El-Ibrahimiya'), ('EG1318', 'Al-Qanayat'), ('EG1319', 'Awlad Saqr'), ('EG1320', 'Qurin'), ('EG1321', '10 Ramadan 2')],
 # SIN North Sinai (11)
 'eg:governorate:north-sinai': [('EG3401', 'El Arish 1'), ('EG3402', 'El Arish 2'), ('EG3403', 'El Arish 3'), ('EG3404', 'El Arish 4'), ('EG3405', 'Bir Al-Abd'), ('EG3406', 'Al-Hasna'), ('EG3407', 'Nakhl'), ('EG3408', 'Shaykh Zuwayd'), ('EG3409', 'Rafah'), ('EG3410', 'Rummana'), ('EG3411', 'Qasima')],
 # SUZ Suez (6)
 'eg:governorate:suez': [('EG0401', 'Suez'), ('EG0402', 'Al-Arbiin'), ('EG0403', 'Ataqa'), ('EG0404', 'Faysal'), ('EG0405', 'Al-Ganayin'), ('EG0406', 'Port Suez Police Department')],
 # WAD New Valley (4)
 'eg:governorate:new-valley': [('EG3201', 'Al-Kharga Oasis'), ('EG3202', 'A-Dakhla Oasis'), ('EG3203', 'Al Farafra Oasis'), ('EG3204', 'Paris Paris')],
}
ok = True
for gov, pairs in table.items():
    have = sorted((r['code'], r['name']) for r in rows if r['parent_source_id'] == gov)
    if have != sorted(pairs):
        print('FAIL gov', gov); fails.append(f'gov {gov}'); ok = False
if ok: print('PASS all 27 governorate mappings (365 districts)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
dup = [c for c, n in Counter(r['code'] for r in rows if r['level'] == '2').items() if n > 1]
check('pcodes-unique-365', not dup, str(dup[:3]))
# Twin/pair anchors: Luxor qism+markaz, Sohag/Qina COD rows, police units.
check('luxor-twins', byid.get('eg:district:luxor', {}).get('code') == 'EG2901'
      and byid.get('eg:district:luxor:luxor', {}).get('code') == 'EG2902')
check('suhag-cod', byid.get('eg:district:suhag', {}).get('code') == 'EG2601')
check('qina-cod', byid.get('eg:district:qina', {}).get('code') == 'EG2701')
check('police-units', byid.get('eg:district:port-suez-police-department', {}).get('code') == 'EG0406'
      and byid.get('eg:district:tiba-police-station', {}).get('code') == 'EG2903')
check('zemam-14', sum(1 for r in rows if r['code'].endswith('00')) == 14)
# No overlay files ship yet: live-system gap, not codeless. A future
# build must flip these checks deliberately (7-digit PP/L/NN/CC scheme).
check('no-codes-file-yet', not os.path.exists(C))
check('no-links-file-yet', not os.path.exists(L))
print('FAILURES:', fails) if fails else print('ALL PASS')
sys.exit(1 if fails else 0)
