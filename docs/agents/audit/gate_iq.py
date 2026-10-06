import csv, re, sys
from collections import Counter
# Iraq gate. M5 revisit: 11 link retargets (348 codes stay 1:1, no
# multis). Fresh 348-row Mapanet re-pull agrees the code set exactly;
# overrides re-adjudicated with Nominatim fwd/revgeo + Mapanet pins +
# Arabic labels + governorate articles. Run from repo root:
# python3 docs/agents/audit/gate_iq.py
A = './packages/addressing/resources/geography/iraq-address-areas.csv'
C = './packages/addressing/resources/geography/iraq-postal-codes.csv'
L = './packages/addressing/resources/geography/iraq-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 19 governorates + 119 districts ---
check('areas-138', len(rows) == 138, str(len(rows)))
check('governorates-19', sum(1 for r in rows if r['type'] == 'governorate') == 19)
check('districts-119', sum(1 for r in rows if r['type'] == 'district') == 119)
iso = {'iq:governorate:al-anbar': ('Al Anbar', 'AN'),
 'iq:governorate:erbil': ('Erbil', 'AR'),
 'iq:governorate:basra': ('Basra', 'BA'),
 'iq:governorate:babylon': ('Babylon', 'BB'),
 'iq:governorate:baghdad': ('Baghdad', 'BG'),
 'iq:governorate:dohuk': ('Dohuk', 'DA'),
 'iq:governorate:diyala': ('Diyala', 'DI'),
 'iq:governorate:dhi-qar': ('Dhi Qar', 'DQ'),
 'iq:governorate:karbala': ('Karbala', 'KA'),
 'iq:governorate:kirkuk': ('Kirkuk', 'KI'),
 'iq:governorate:maysan': ('Maysan', 'MA'),
 'iq:governorate:al-muthanna': ('Al Muthanna', 'MU'),
 'iq:governorate:najaf': ('Najaf', 'NA'),
 'iq:governorate:nineveh': ('Nineveh', 'NI'),
 'iq:governorate:al-qadisiyyah': ('Al-Qadisiyyah', 'QA'),
 'iq:governorate:saladin': ('Saladin', 'SD'),
 'iq:governorate:sulaymaniyah': ('Sulaymaniyah', 'SU'),
 'iq:governorate:wasit': ('Wasit', 'WA')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
# Halabja: KRG governorate 2014, federally recognised 2025-04-14; HL
# follows UK government usage pending ISO assignment. IQ-KR is a
# region, not a governorate, and ships no row.
check('halabja-HL', byid['iq:governorate:halabja']['code'] == 'HL')
check('no-KR-row', all(r['code'] != 'KR' for r in rows))
table = {
 'iq:governorate:al-anbar': ["Al-Qa'im", 'Anah', 'Ar-Rutba', 'Fallujah',
    'Haditha', 'Hīt', 'Ramadi', 'Rawah'],
 'iq:governorate:al-muthanna': ['Al-Khidhir', 'Al-Rumaitha', 'Al-Salman',
    'Al-Samawa'],
 'iq:governorate:al-qadisiyyah': ['Afaq', 'Al-Shamiya', 'Diwaniya',
    'Hamza'],
 'iq:governorate:babylon': ['Al-Mahawil', 'Al-Musayab', 'Hashimiya',
    'Hilla'],
 'iq:governorate:baghdad': ['Abu Ghraib', 'Al Tarmia', 'Al-Adhamiyah',
    'Al-Karkh', "Al-Mada'in", 'Al-Rasafa', 'Kadhimiya', 'Mahmudiya',
    'Sadr City 1', 'Sadr City 2'],
 'iq:governorate:basra': ['Abu Al-Khaseeb', 'Al-Midaina', 'Al-Qurna',
    'Al-Zubair', 'Basrah', 'al-Faw'],
 'iq:governorate:dhi-qar': ['Al-Chibayish', "Al-Rifa'i", 'Al-Shatra',
    'Nassriya', 'Suq Al-Shoyokh'],
 'iq:governorate:diyala': ['Al-Khalis', 'Al-Muqdadiya', "Ba'quba",
    'Baladrooz', 'Khanaqin', 'Kifri'],
 'iq:governorate:dohuk': ['Akre', 'Amadiya', 'Bardarash', 'Dahuk',
    'Shekhan', 'Sumel', 'Zakho'],
 'iq:governorate:erbil': ['Choman', 'Erbil', 'Erbil Countryside',
    'Koisanjaq', 'Mergasur', 'Shaqlawa', 'Soran', 'Taqtaq'],
 'iq:governorate:halabja': ['Bamo', 'Byara', 'Halabja', 'Khurmal',
    'Sirwan'],
 'iq:governorate:karbala': ['Ain Al-Tamur', 'Al-Hindiya', 'Kerbala'],
 'iq:governorate:kirkuk': ['Al-Dibs', 'Al-Hawiga', 'Daquq', 'Kirkuk'],
 'iq:governorate:maysan': ['Al-Kahla', 'Al-Maimouna', 'Al-Mejar Al-Kabi',
    'Ali Al-Gharbi', 'Amara', "Qal'at Saleh"],
 'iq:governorate:najaf': ['Al-Manathera', 'Al-Meshkhab', 'Kufa', 'Najaf'],
 'iq:governorate:nineveh': ["Al-Ba'aj", 'Al-Hamdaniya', 'Hatra',
    'Makhmur', 'Mosul', 'Sinjar', 'Tel Afar', 'Tel Keppe'],
 'iq:governorate:saladin': ['Al-Daur', 'Al-Shirqat', 'Baiji', 'Balad',
    'Dujail', 'Samarra', 'Tikrit', 'Tooz'],
 'iq:governorate:sulaymaniyah': ['Chamchamal', 'Darbandokeh', 'Dokan',
    'Kalar', 'Mawat', 'Penjwin', 'Pshdar', 'Qaradagh', 'Rania',
    'Saidsadiq', 'Sharazoor', 'Sharbazher', 'Sulaymaniya'],
 'iq:governorate:wasit': ['Al-Aziziyah', 'Al-Hai', "Al-Na'maniya",
    'Al-Suwaira', 'Badra', 'Kut'],
}
ok = True
for par, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == par)
    if have != sorted(names):
        print('FAIL parent', par, have); fails.append(f'parent {par}'); ok = False
if ok: print('PASS all 19 parent mappings (119 districts)')
# Makhmur sits under Nineveh only: the Districts-of-Iraq oracle lists
# 120 rows with Makhmur double-counted under a contested Erbil claim;
# the page's own contest note puts it under federal/Nineveh control.
check('makhmur-nineveh-only',
      byid['iq:district:makhmur']['parent_source_id']
      == 'iq:governorate:nineveh')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 348 codes / 348 links, all L1, zero multis ---
check('codes-348', len(codes) == 348, str(len(codes)))
check('links-348', len(links) == 348, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 348 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
check('no-multis', all(c == 1 for c in counts.values()))
check('all-l1', all(l['area_source_id'].split(':')[1] == 'governorate'
      for l in links))
# --- per-governorate primary counts (post M5 retargets) ---
expect = {'iq:governorate:al-anbar': 21, 'iq:governorate:al-muthanna': 8,
 'iq:governorate:al-qadisiyyah': 17, 'iq:governorate:babylon': 19,
 'iq:governorate:baghdad': 73, 'iq:governorate:basra': 30,
 'iq:governorate:dhi-qar': 16, 'iq:governorate:diyala': 20,
 'iq:governorate:dohuk': 10, 'iq:governorate:erbil': 18,
 'iq:governorate:halabja': 2, 'iq:governorate:karbala': 4,
 'iq:governorate:kirkuk': 16, 'iq:governorate:maysan': 12,
 'iq:governorate:najaf': 7, 'iq:governorate:nineveh': 32,
 'iq:governorate:saladin': 13, 'iq:governorate:sulaymaniyah': 18,
 'iq:governorate:wasit': 12}
have = Counter(l['area_source_id'] for l in links
               if l['is_primary'] == 'true')
for sid, n in expect.items():
    check(f"count-{sid.split(':')[-1]}-{n}", have[sid] == n,
          str(have[sid]))
# --- cross-block pins: every code outside its home 3-digit block ---
# home blocks: 100 baghdad, 310 anbar, 320 diyala, 340 saladin,
# 360 kirkuk, 410 nineveh, 420 dohuk, 440 erbil, 460 suli+halabja,
# 510 babylon, 520 wasit, 540 najaf, 560 karbala, 580 qadisiyyah,
# 610 basra, 620 maysan, 640 dhi-qar, 660 muthanna.
home = {'iq:governorate:baghdad': '100', 'iq:governorate:al-anbar': '310',
 'iq:governorate:diyala': '320', 'iq:governorate:saladin': '340',
 'iq:governorate:kirkuk': '360', 'iq:governorate:nineveh': '410',
 'iq:governorate:dohuk': '420', 'iq:governorate:erbil': '440',
 'iq:governorate:sulaymaniyah': '460', 'iq:governorate:halabja': '460',
 'iq:governorate:babylon': '510', 'iq:governorate:wasit': '520',
 'iq:governorate:najaf': '540', 'iq:governorate:karbala': '560',
 'iq:governorate:al-qadisiyyah': '580', 'iq:governorate:basra': '610',
 'iq:governorate:maysan': '620', 'iq:governorate:dhi-qar': '640',
 'iq:governorate:al-muthanna': '660'}
link = {l['postcode']: l['area_source_id'] for l in links}
x = sorted(pc for pc, sid in link.items()
           if not pc.startswith(home[sid]))
check('xblock-14', x == ['31020', '34004', '42004', '42012', '44015',
      '44016', '44018', '44020', '44021', '44022', '46015', '46017',
      '46022', '58012'], str(x))
# adjudicated keeps: Abu Ghraib/Salman Pak are Baghdad-district towns
# with neighbour-block codes; Aqre/Sheekhan/Kalak are disputed-territory
# ties held at Nineveh; Mishtiqa is unlocatable (centroid pin, zero
# Nominatim hits, no article) so its Nineveh link stays as an unresolved
# tie; Mekhmour/Debca/Quwair are Makhmur-district towns; Alton Copri is
# Dibis-district Kirkuk; Kwaisanjaq/Koya are Koya-district Erbil; Kifri
# is Diyala-district; Al Suwaira 58012 pins to Suwayra-district Wasit.
keep = {'31020': 'iq:governorate:baghdad',
 '34004': 'iq:governorate:baghdad',
 '42004': 'iq:governorate:nineveh',
 '42012': 'iq:governorate:nineveh',
 '44015': 'iq:governorate:nineveh',
 '44016': 'iq:governorate:nineveh',
 '44018': 'iq:governorate:nineveh',
 '44020': 'iq:governorate:nineveh',
 '44021': 'iq:governorate:nineveh',
 '44022': 'iq:governorate:kirkuk',
 '46015': 'iq:governorate:erbil',
 '46017': 'iq:governorate:erbil',
 '46022': 'iq:governorate:diyala',
 '58012': 'iq:governorate:wasit'}
for pc, sid in keep.items():
    check(f'xpin-{pc}', link.get(pc) == sid, str(link.get(pc)))
# M5 moves back in-block: Latifiya is a Mahmudiya-district (Baghdad)
# subdistrict, not Babylon; Qal'at Diza is Pshdar-district Suli, not
# Erbil; Sharazor/Penjaween/Said Sadiq declined Halabja and stay Suli;
# Helabcha is Halabja town (dup of 46018); Shafiiyya is Diwaniya-district.
moved = {'10080': 'iq:governorate:baghdad',
 '46016': 'iq:governorate:sulaymaniyah',
 '46005': 'iq:governorate:sulaymaniyah',
 '46007': 'iq:governorate:sulaymaniyah',
 '46008': 'iq:governorate:sulaymaniyah',
 '46006': 'iq:governorate:halabja',
 '58014': 'iq:governorate:al-qadisiyyah'}
for pc, sid in moved.items():
    check(f'moved-{pc}', link.get(pc) == sid, str(link.get(pc)))
# UPU irqEn anchors: 61002 Basra office delivery; 61102 is a PO-box-only
# example with no town row in any directory (out of scope, not a gap).
check('upu-61002', link.get('61002') == 'iq:governorate:basra')
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
