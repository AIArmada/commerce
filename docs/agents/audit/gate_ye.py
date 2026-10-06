import csv, os, sys
# Yemen gate. M6 revisit: verify-only, zero data changes. Tree =
# ISO 3166-2:YE (22 L1: 21 governorates + SA municipality) x List
# of districts of Yemen (333/333 names, wiki " district" suffix
# stripped). Codeless: UPU yemEn (03/2005) has no postcode section,
# Sep-2025 UPU list carries Yemen on do-not-require, GeoNames ships
# no YE postal dump (404), List-of-postal-codes says "no codes".
# Run from repo root: python3 /tmp/geo-verify/M6/YE/gate_ye.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/yemen-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/yemen-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/yemen-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-355', len(rows) == 355, str(len(rows)))
check('governorates-21', sum(1 for r in rows if r['type'] == 'governorate') == 21)
check('municipalities-1', sum(1 for r in rows if r['type'] == 'municipality') == 1)
check('districts-333', sum(1 for r in rows if r['type'] == 'district') == 333)
# ISO 3166-2:YE (BGN/PCGN diacritics kept ASCII in provider English;
# SU Socotra split from Hadhramaut Dec 2013; SA is the municipality).
iso = {'ye:governorate:abyan': ('Abyan', 'AB', 'governorate'),
 'ye:governorate:adan': ('Adan', 'AD', 'governorate'),
 'ye:governorate:amran': ('Amran', 'AM', 'governorate'),
 "ye:governorate:al-bayda": ("Al Bayda'", 'BA', 'governorate'),
 "ye:governorate:ad-dali": ("Ad Dali'", 'DA', 'governorate'),
 'ye:governorate:dhamar': ('Dhamar', 'DH', 'governorate'),
 'ye:governorate:hadhramaut': ('Hadhramaut', 'HD', 'governorate'),
 'ye:governorate:hajjah': ('Hajjah', 'HJ', 'governorate'),
 'ye:governorate:al-hudaydah': ('Al Hudaydah', 'HU', 'governorate'),
 'ye:governorate:ibb': ('Ibb', 'IB', 'governorate'),
 'ye:governorate:al-jawf': ('Al Jawf', 'JA', 'governorate'),
 'ye:governorate:lahij': ('Lahij', 'LA', 'governorate'),
 "ye:governorate:marib": ("Ma'rib", 'MA', 'governorate'),
 'ye:governorate:al-mahrah': ('Al Mahrah', 'MR', 'governorate'),
 'ye:governorate:al-mahwit': ('Al Mahwit', 'MW', 'governorate'),
 'ye:governorate:raymah': ('Raymah', 'RA', 'governorate'),
 'ye:municipality:amanat-al-asimah': ('Amanat Al Asimah', 'SA', 'municipality'),
 'ye:governorate:saada': ('Saada', 'SD', 'governorate'),
 'ye:governorate:shabwah': ('Shabwah', 'SH', 'governorate'),
 "ye:governorate:sanaa": ("Sana'a", 'SN', 'governorate'),
 'ye:governorate:socotra': ('Socotra', 'SU', 'governorate'),
 "ye:governorate:taizz": ("Ta'izz", 'TA', 'governorate')}
for sid, (name, code, typ) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['type'] == typ and r['level'] == '1'
          and not r['parent_source_id'], str(r))
# Full governorate -> districts table per List-of-districts-of-Yemen
# oracle (wiki link labels carry a " district" suffix, stripped here;
# Socotra parens "(eastern/western part...)" likewise). M6 fix: the
# oracle's double spaces in 'Al  Hawtah' / 'Al  Makha' were typos;
# canonical district articles use single spaces ('Al Hawtah' /
# 'Al Makha'), which the CSV now carries.
table = {
 'ye:governorate:abyan': ['Ahwar', 'Al Mahfad', "Al Wade'a", 'Jayshan',
    'Khanfir', 'Lawdar', 'Mudiyah', 'Rasad', 'Sarar', 'Sibah', 'Zingibar'],
 'ye:governorate:adan': ['Al Buraiqa', 'Al Mansura', 'Crater', 'Dar Sad',
    'Khur Maksar', 'Mualla', 'Sheikh Othman', 'Tawahi'],
 'ye:governorate:amran': ['Al Ashah', 'Al Madan', 'Al Qaflah', 'Amran',
    'As Sawd', 'As Sudah', 'Bani Suraim', 'Dhi Bin', 'Habur Zulaymah',
    'Harf Sufyan', 'Huth', 'Iyal Surayh', 'Jabal Iyal Yazid', 'Khamir',
    'Kharif', 'Maswar', 'Raydah', 'Shaharah', 'Suwayr', 'Thula'],
 "ye:governorate:al-bayda": ["Al A'rsh", 'Al Bayda', 'Al Bayda City',
    'Al Malagim', 'Al Quraishyah', 'Ar Ryashyyah', 'As Sawadiyah',
    "As Sawma'ah", 'Ash Sharyah', 'At Taffah', 'Az Zahir', "Dhi Na'im",
    'Maswarah', 'Mukayras', "Na'man", "Nati'", "Rada'", 'Radman Al Awad',
    'Sabah', "Wald Rabi'"],
 "ye:governorate:ad-dali": ["Ad Dhale'e", 'Al Azariq', 'Al Husha',
    'Al Hussein', "Ash Shu'ayb", 'Damt', 'Jahaf', 'Juban', "Qa'atabah"],
 'ye:governorate:dhamar': ['Al Hada', 'Al Manar', 'Anss', 'Dawran Aness',
    'Dhamar City', 'Jabal Ash sharq', 'Jahran', 'Maghirib Ans',
    "Mayfa'at Anss", 'Utmah', 'Wusab Al Ali', 'Wusab As Safil'],
 'ye:governorate:hadhramaut': ['Ad Dis', "Adh Dhlia'ah", 'Al Abr',
    'Al Qaf', 'Al Qatn', 'Amd', 'Ar Raydah Wa Qusayar', 'As Sawm',
    'Ash Shihr', 'Brom Mayfa', "Daw'an", 'Ghayl Ba Wazir',
    'Ghayl Bin Yamin', "Hagr As Sai'ar", 'Hajr', 'Hawrah', 'Huraidhah',
    'Mukalla', 'Mukalla City', 'Rakhyah', 'Rumah', 'Sah', 'Sayun',
    'Shibam', 'Tarim', 'Thamud', 'Yabuth', 'Zamakh wa Manwakh'],
 'ye:governorate:hajjah': ['Abs', 'Aflah Al Yaman', 'Aflah Ash Shawm',
    'Al Jamimah', 'Al Maghrabah', 'Al Mahabishah', 'Al Miftah',
    'Ash Shaghadirah', 'Ash Shahil', 'Aslem', 'Bakil Al Mir',
    'Bani Al Awam', "Bani Qa'is", 'Hajjah', 'Hajjah City', 'Harad',
    'Hayran', 'Khayran Al Muharraq', "Ku'aydinah", 'Kuhlan Affar',
    'Kuhlan Ash Sharaf', 'Kushar', 'Mabyan', 'Midi', 'Mustaba',
    'Najrah', 'Qafl Shamer', 'Qarah', 'Sharas', 'Wadhrah', 'Washhah'],
 'ye:governorate:al-hudaydah': ['Ad Dahi', 'Ad Durayhimi', 'Al Garrahi',
    'Al Hajjaylah', 'Al Hali', 'Al Hawak', 'Al Khawkhah',
    'Al Mansuriyah', "Al Marawi'ah", 'Al Mighlaf', 'Al Mina',
    'Al Munirah', 'Al Qanawis', 'Alluheyah', 'As Salif', 'As Sukhnah',
    'At Tuhayat', 'Az Zaydiyah', 'Az Zuhrah', 'Bajil', 'Bayt al-Faqih',
    'Bura', 'Hays', "Jabal Ra's", 'Kamaran', 'Zabid'],
 'ye:governorate:ibb': ['Al Dhihar', 'Al Makhadir', 'Al Mashannah',
    'Al Qafr', 'Al Udayn', 'An Nadirah', 'Ar Radmah', 'As Sabrah',
    'As Saddah', 'As Sayyani', "Ash Sha'ir", "Ba'dan", 'Dhi As Sufal',
    'Far Al Udayn', 'Hazm Al Udayn', 'Hubaysh', 'Ibb', 'Jiblah',
    'Mudhaykhirah', 'Yarim'],
 'ye:governorate:al-jawf': ['Al Ghayl', 'Al Hazm', 'Al Humaydat',
    'Al Khalq', 'Al Maslub', 'Al Matammah', 'Al Maton', 'Az Zahir',
    'Bart Al Anan', "Khabb wa ash Sha'af", 'Kharab Al Marashi',
    'Rajuzah'],
 'ye:governorate:lahij': ['Al Hawtah', 'Al Had',
    'Al Madaribah Wa Al Arah', 'Al Maflahy', 'Al Maqatirah', 'Al Milah',
    'Al Musaymir', 'Al Qabbaytah', 'Habil Jabr', 'Halimayn', 'Radfan',
    'Tuban', 'Tur Al Bahah', "Yafa'a", 'Yahr'],
 'ye:governorate:marib': ['Al Abdiyah', 'Al Jubah', 'Bidbadah', 'Harib',
    'Harib Al Qaramish', 'Jabal Murad', 'Mahliyah', 'Majzar', 'Marib',
    'Marib City', 'Medghal', 'Raghwan', 'Rahabah', 'Sirwah'],
 'ye:governorate:al-mahrah': ['Al Ghaydah', 'Al Masilah', 'Hat', 'Hawf',
    'Huswain', "Man'ar", 'Qishn', 'Sayhut', 'Shahan'],
 'ye:governorate:al-mahwit': ['Al Khabt', 'Al Mahwait', 'Al Mahwait City',
    'Ar Rujum', 'At Tawilah', "Bani Sa'd", 'Hufash', 'Milhan',
    'Shibam Kawkaban'],
 'ye:governorate:raymah': ['Al Jabin', 'Al Jafariyah', 'As Salafiyah',
    "Bilad At Ta'am", 'Kusmah', 'Mazhar'],
 'ye:municipality:amanat-al-asimah': ['Al Wahdah', 'As Sabain',
    "Assafi'yah", 'At Tahrir', "Ath'thaorah", "Az'zal", 'Bani Al Harith',
    "Ma'ain", 'Old City', "Shu'aub"],
 'ye:governorate:saada': ['Al Dhaher', 'Al Hashwah', 'As Safra', 'Baqim',
    'Ghamr', 'Haydan', "Kitaf wa Al Boqe'e", 'Majz', 'Monabbih',
    'Qatabir', 'Razih', "Sa'adah", 'Sahar', 'Saqayn', "Shada'a"],
 'ye:governorate:shabwah': ['Ain', 'Al Talh', 'Ar Rawdah', 'Arma',
    'As Said', 'Ataq', 'Bayhan', 'Dhar', 'Habban', 'Hatib', 'Jardan',
    "Mayfa'a", 'Merkhah Al Ulya', 'Merkhah As Sufla', 'Nisab', 'Rudum',
    'Usaylan'],
 'ye:governorate:sanaa': ['Al Haymah Ad Dakhiliyah',
    'Al Haymah Al Kharijiyah', 'Al Husn', 'Arhab', 'Attyal',
    'Bani Dhabyan', 'Bani Hushaysh', 'Bani Matar', 'Bilad Ar Rus',
    'Hamdan', 'Jihanah', 'Khwlan', 'Manakhah', 'Nihm', "Sa'fan",
    'Sanhan'],
 'ye:governorate:socotra': ['Hidaybu', 'Qulensya wa Abd al Kuri'],
 'ye:governorate:taizz': ['Al Makha', "Al Ma'afer", 'Al Mawasit',
    'Al Misrakh', 'Al Mudhaffar', 'Al Qahirah', "Al Wazi'iyah", 'As Silw',
    'Ash Shamayatayn', "At Ta'iziyah", 'Dhubab', 'Dimnat Khadir',
    'Hayfan', 'Jabal Habashy', 'Maqbanah', "Mashra'a Wa Hadnan",
    'Mawiyah', 'Mawza', 'Sabir Al Mawadim', 'Salh', "Same'a",
    "Shara'b Ar Rawnah", "Shara'b As Salam"],
}
ok = True
for gov, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == gov)
    if have != sorted(names):
        print('FAIL gov', gov, have); fails.append(f'gov {gov}'); ok = False
if ok: print('PASS all 22 governorate mappings (333 districts)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
