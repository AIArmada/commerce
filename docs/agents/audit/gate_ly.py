import csv, os, sys
# Libya gate. No postcode system. M5 revisit: verify-only, zero
# data changes. Run from repo root: python3 docs/agents/audit/gate_ly.py
A = './packages/addressing/resources/geography/libya-address-areas.csv'
C = './packages/addressing/resources/geography/libya-postal-codes.csv'
L = './packages/addressing/resources/geography/libya-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
byid = {r['source_id']: r for r in rows}
check('areas-122', len(rows) == 122, str(len(rows)))
check('popularates-22', sum(1 for r in rows if r['type'] == 'popularate') == 22)
check('baladiyas-100', sum(1 for r in rows if r['type'] == 'baladiya') == 100)
# ISO 3166-2:LY codes (22 popularates, 2007 system, current per
# Newsletter II-2). Display follows the ISO en reference where it
# exists (Derna, Murqub, Sirte, Tripoli, Zawiya, Nuqat al Khams,
# Wadi al Hayaa); the districts oracle prints the Arabic-name forms
# (Darnah, Marqab, Surt, Tarabulus, Az Zawiyah, An Nuqat al Khams).
# 'Wadi al Shatii' matches the oracle article title (ISO en prints
# 'Wadi ash Shati'); 'Jafara/Jufra/Kufra' drop the terminal -h of
# the ISO romanizations Al Jafarah/Al Jufrah/Al Kufrah.
iso = {'ly:popularate:al-butnan': ('Al Butnan', 'BU'),
 'ly:popularate:al-wahat': ('Al Wahat', 'WA'),
 'ly:popularate:benghazi': ('Benghazi', 'BA'),
 'ly:popularate:derna': ('Derna', 'DR'),
 'ly:popularate:ghat': ('Ghat', 'GT'),
 'ly:popularate:jabal-al-akhdar': ('Jabal al Akhdar', 'JA'),
 'ly:popularate:jabal-al-gharbi': ('Jabal al Gharbi', 'JG'),
 'ly:popularate:jafara': ('Jafara', 'JI'),
 'ly:popularate:jufra': ('Jufra', 'JU'),
 'ly:popularate:kufra': ('Kufra', 'KF'),
 'ly:popularate:marj': ('Marj', 'MJ'),
 'ly:popularate:misrata': ('Misrata', 'MI'),
 'ly:popularate:murqub': ('Murqub', 'MB'),
 'ly:popularate:murzuq': ('Murzuq', 'MQ'),
 'ly:popularate:nalut': ('Nalut', 'NL'),
 'ly:popularate:nuqat-al-khams': ('Nuqat al Khams', 'NQ'),
 'ly:popularate:sabha': ('Sabha', 'SB'),
 'ly:popularate:sirte': ('Sirte', 'SR'),
 'ly:popularate:tripoli': ('Tripoli', 'TB'),
 'ly:popularate:wadi-al-hayaa': ('Wadi al Hayaa', 'WD'),
 'ly:popularate:wadi-al-shatii': ('Wadi al Shatii', 'WS'),
 'ly:popularate:zawiya': ('Zawiya', 'ZA')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Full popularate -> baladiyas table: the operational 100-set
# (OCHA COD 2017 ADMIN-3 sheet: identical 100 p-codes + mantika
# parents; COD-AB v01 corroborates 78/78 place rows with zero
# diffs; IOM DTM R8/R51/MR58/MR60 all assert 100 municipalities).
table = {'ly:popularate:derna': ['Derna', 'Umm arrazam',
    'Alqubba', 'Alqayqab', 'Labriq'],
 'ly:popularate:marj': ['Jardas Alabeed', 'Almarj', 'Assahel'],
 'ly:popularate:benghazi': ['Alabyar', 'Toukra', 'Suloug',
    'Benghazi', 'Gemienis'],
 'ly:popularate:al-butnan': ['Emsaed', 'Bir Alashhab', 'Tobruk'],
 'ly:popularate:al-wahat': ['Ejkherra', 'Jalu', 'Aujala',
    'Ejdabia', 'Marada', 'Albrayga'],
 'ly:popularate:jabal-al-akhdar': ['Shahhat', 'Albayda'],
 'ly:popularate:kufra': ['Alkufra', 'Tazirbu'],
 'ly:popularate:sirte': ['Khaleej Assidra', 'Hrawa', 'Sirt'],
 'ly:popularate:nalut': ['Ghadamis', 'Alharaba', 'Kabaw',
    'Alhawamid', 'Nalut', 'Wazin', 'Daraj', 'Baten Aljabal'],
 'ly:popularate:murqub': ['Alkhums', 'Msallata', 'Qasr Akhyar',
    'Garabolli', 'Tarhuna'],
 'ly:popularate:tripoli': ['Suq Aljumaa', 'Tajoura', 'Ain Zara',
    'Tripoli', 'Abusliem', 'Hai Alandalus'],
 'ly:popularate:jafara': ['Sidi Assayeh', 'Suq Alkhamees',
    'Qasr Bin Ghasheer', 'Espeaa', 'Swani Bin Adam', 'Janzour',
    'Al Aziziya', 'Al Maya', 'Azzahra'],
 'ly:popularate:zawiya': ['Azzawya', 'Surman', 'Gharb Azzawya',
    'Janoub Azzawya'],
 'ly:popularate:misrata': ['Misrata', 'Zliten', 'Abu Qurayn',
    'Bani Waleed'],
 'ly:popularate:nuqat-al-khams': ['Al Ajaylat', 'Sabratha',
    'Zwara', 'Aljmail', 'Rigdaleen', 'Ziltun'],
 'ly:popularate:jabal-al-gharbi': ['Nesma', 'Azzintan',
    'Alasabaa', 'Al Qalaa', 'Yefren', 'Ghiryan', 'Kikkla',
    'Arrajban', 'Jadu', 'Arrhaibat', 'Arrayayna', 'Ashshgega',
    'Ashshwayrif', 'Thaher Aljabal'],
 'ly:popularate:jufra': ['Aljufra'],
 'ly:popularate:wadi-al-shatii': ['Brak', 'Edri',
    'Algurdha Ashshati'],
 'ly:popularate:sabha': ['Sebha', 'Albawanees'],
 'ly:popularate:wadi-al-hayaa': ['Bint Bayya', 'Alghrayfa',
    'Ubari'],
 'ly:popularate:ghat': ['Ghat'],
 'ly:popularate:murzuq': ['Alsharguiya', 'Algatroun',
    'Taraghin', 'Murzuq', 'Wadi Etba']}
ok = True
for pop, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == pop)
    if have != sorted(names):
        print('FAIL popularate', pop, have); fails.append(f'popularate {pop}'); ok = False
if ok: print('PASS all 22 popularate mappings (100 baladiyas)')
# P-code prefix consistency: each baladiya LYrrmmss must sit under
# the popularate whose mantika segment mm it carries (Tobruk LY0104
# -> Al Butnan, Ejdabia LY0105 -> Al Wahat, Zwara LY0215 ->
# Nuqat al Khams, Ubari LY0320 -> Wadi al Hayaa).
mant = {'LY0101': 'ly:popularate:derna', 'LY0102': 'ly:popularate:marj',
 'LY0103': 'ly:popularate:benghazi',
 'LY0104': 'ly:popularate:al-butnan',
 'LY0105': 'ly:popularate:al-wahat',
 'LY0106': 'ly:popularate:jabal-al-akhdar',
 'LY0107': 'ly:popularate:kufra', 'LY0208': 'ly:popularate:sirte',
 'LY0209': 'ly:popularate:nalut',
 'LY0210': 'ly:popularate:murqub',
 'LY0211': 'ly:popularate:tripoli',
 'LY0212': 'ly:popularate:jafara',
 'LY0213': 'ly:popularate:zawiya',
 'LY0214': 'ly:popularate:misrata',
 'LY0215': 'ly:popularate:nuqat-al-khams',
 'LY0216': 'ly:popularate:jabal-al-gharbi',
 'LY0317': 'ly:popularate:jufra',
 'LY0318': 'ly:popularate:wadi-al-shatii',
 'LY0319': 'ly:popularate:sabha',
 'LY0320': 'ly:popularate:wadi-al-hayaa',
 'LY0321': 'ly:popularate:ghat',
 'LY0322': 'ly:popularate:murzuq'}
bad = [r['source_id'] for r in rows if r['type'] == 'baladiya'
       and mant.get(r['code'][:6]) != r['parent_source_id']]
check('pcode-prefix-parents', not bad, str(bad[:3]))
codes = [r['code'] for r in rows if r['code']]
check('codes-unique', len(codes) == len(set(codes)), str(len(codes)))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Verdict none: UPU lbyEn (02/2012) shows a codeless TRIPOLI
# address with no postcode section; Sep-2025 UPU list carries
# Libya (State of) on do-not-require (absent from the Aug-2026
# require list); GeoNames has no LY postal dump (404).
check('no-codes-file', not os.path.exists(C))
check('no-links-file', not os.path.exists(L))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
