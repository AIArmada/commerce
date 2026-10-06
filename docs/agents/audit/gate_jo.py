import csv, re, sys
from collections import Counter
# Jordan gate. Pins the adjudicated state from the Mapanet x GeoNames x
# Photon x DoS-census pass (351 codes / 352 links) plus the M2 tree
# re-verification (12 governorates ISO JO + 51 liwa). M2 revisit:
# verify-only, zero data changes. Run from repo root:
# python3 docs/agents/audit/gate_jo.py
A = './packages/addressing/resources/geography/jordan-address-areas.csv'
C = './packages/addressing/resources/geography/jordan-postal-codes.csv'
L = './packages/addressing/resources/geography/jordan-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 12 governorates + 51 liwa ---
check('areas-63', len(rows) == 63, str(len(rows)))
check('governorates-12', sum(1 for r in rows if r['type'] == 'governorate') == 12)
check('liwa-51', sum(1 for r in rows if r['type'] == 'liwa') == 51)
iso = {'jo:governorate:ajloun': ('Ajloun', 'AJ'), 'jo:governorate:amman': ('Amman', 'AM'),
 'jo:governorate:aqaba': ('Aqaba', 'AQ'), 'jo:governorate:tafilah': ('Tafilah', 'AT'),
 'jo:governorate:zarqa': ('Zarqa', 'AZ'), 'jo:governorate:balqa': ('Balqa', 'BA'),
 'jo:governorate:irbid': ('Irbid', 'IR'), 'jo:governorate:jerash': ('Jerash', 'JA'),
 'jo:governorate:karak': ('Karak', 'KA'), 'jo:governorate:mafraq': ('Mafraq', 'MA'),
 'jo:governorate:madaba': ('Madaba', 'MD'), 'jo:governorate:ma-an': ("Ma'an", 'MN')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1', str(r))
table = {'jo:governorate:ajloun': ['Ajloun Qasabah', 'Kufranjah'],
 'jo:governorate:amman': ["Al-Jami'ah", 'Amman Qasabah', 'Jizah', 'Marka',
    'Muaqqar', 'Naour', 'Quaismeh', 'Sahab', 'Wadi Essier'],
 'jo:governorate:aqaba': ['Aqaba Qasabah', 'Quairah'],
 'jo:governorate:tafilah': ['Bsaira', 'Hasa', 'Tafilah Qasabah'],
 'jo:governorate:zarqa': ['Hashemiyah', 'Russeifa', 'Zarqa Qasabah'],
 'jo:governorate:balqa': ['Ain Al Basha', 'Deir Alla', 'Mahis and Fuhais',
    'Salt Qasabah', 'Shoonah Janoobiyah'],
 'jo:governorate:irbid': ['Aghwar Shamaliyah', 'Bani Kenanah', 'Bani Obeid',
    'Irbid Qasabah', 'Koorah', 'Mazar Shamali', 'Ramtha', 'Taybeh', 'Wastiyyah'],
 'jo:governorate:jerash': ['Jerash Qasabah'],
 'jo:governorate:karak': ['Aghwar Janoobiyah', 'Ayy', "Faqo'e",
    'Karak Qasabah', 'Mazar Janoobee', 'Qasr', 'Qatraneh'],
 'jo:governorate:mafraq': ['Badiah Shamaliyah',
    'Badiah Shamaliyah Gharbiyah', 'Mafraq Qasabah', 'Rwaished'],
 'jo:governorate:madaba': ['Dieban', 'Madaba Qasabah'],
 'jo:governorate:ma-an': ['Huseiniya', "Ma'an Qasabah", 'Petra', 'Shobak']}
ok = True
for gov, names in table.items():
    have = sorted(r['name'] for r in rows if r['parent_source_id'] == gov)
    if have != sorted(names):
        print('FAIL governorate', gov, have); fails.append(f'governorate {gov}'); ok = False
if ok: print('PASS all 12 governorate mappings (51 liwa)')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal counts (adjudicated state) ---
check('codes-351', len(codes) == 351, str(len(codes)))
check('links-352', len(links) == 352, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 351 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
# --- per-liwa primary counts ---
expect = {'aghwar-janoobiyah': 1, 'aghwar-shamaliyah': 1, 'ain-al-basha': 3,
 'ajloun-qasabah': 19, 'al-jami-ah': 13, 'amman-qasabah': 11,
 'aqaba-qasabah': 6, 'ayy': 1, 'badiah-shamaliyah': 8,
 'badiah-shamaliyah-gharbiyah': 4, 'bani-kenanah': 9, 'bani-obeid': 5,
 'deir-alla': 3, 'dieban': 1, 'faqo-e': 2, 'hasa': 1, 'hashemiyah': 5,
 'huseiniya': 1, 'irbid-qasabah': 14, 'jerash-qasabah': 14, 'jizah': 19,
 'karak-qasabah': 14, 'koorah': 11, 'ma-an-qasabah': 11,
 'madaba-qasabah': 12, 'mafraq-qasabah': 5, 'mahis-and-fuhais': 3,
 'marka': 20, 'mazar-janoobee': 15, 'mazar-shamali': 4, 'muaqqar': 4,
 'naour': 9, 'petra': 5, 'qasr': 7, 'qatraneh': 2, 'quairah': 5,
 'quaismeh': 15, 'ramtha': 9, 'russeifa': 4, 'rwaished': 2, 'sahab': 4,
 'salt-qasabah': 13, 'shobak': 5, 'tafilah-qasabah': 6, 'taybeh': 3,
 'wadi-essier': 7, 'wastiyyah': 2, 'zarqa-qasabah': 13}
have = Counter()
for l in links:
    if l['is_primary'] == 'true':
        have[l['area_source_id'].split(':')[-1]] += 1
for slug, n in expect.items():
    check(f'count-{slug}-{n}', have[slug] == n, str(have[slug]))
for slug in ['kufranjah', 'bsaira', 'shoonah-janoobiyah']:
    check(f'zero-{slug}', have[slug] == 0, str(have[slug]))
# --- the single dual link + border adjudication anchors ---
plink = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
got = sorted((l['area_source_id'], l['is_primary']) for l in links if l['postcode'] == '11121')
check('dual-11121', got == [('jo:liwa:amman:al-jami-ah', 'false'),
      ('jo:liwa:amman:wadi-essier', 'true')], str(got))
counts = Counter(l['postcode'] for l in links)
check('multi-codes-1', sorted(pc for pc, c in counts.items() if c > 1) == ['11121'])
anchors = {'71910': 'jo:liwa:ma-an:shobak', '61258': 'jo:liwa:amman:sahab',
 '64710': 'jo:liwa:tafilah:hasa', '25710': 'jo:liwa:mafraq:mafraq-qasabah',
 '11190': 'jo:liwa:amman:amman-qasabah', '11134': 'jo:liwa:amman:marka',
 '11152': 'jo:liwa:amman:quaismeh', '71221': 'jo:liwa:ajloun:ajloun-qasabah',
 '71228': 'jo:liwa:jerash:jerash-qasabah', '61256': 'jo:liwa:amman:quaismeh'}
for pc, want in anchors.items():
    check(f'anchor-{pc}', plink.get(pc) == want, str(plink.get(pc)))
# --- CRLF preservation on postal files ---
for path, label in [(C, 'codes-crlf'), (L, 'links-crlf')]:
    raw = open(path, 'rb').read()
    crlf, lf = raw.count(b'\r\n'), raw.count(b'\n')
    check(label, crlf > 0 and crlf == lf, f'crlf={crlf} lf={lf}')
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
