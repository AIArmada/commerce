import csv, re, sys
# Oman geography gate. Pins the M3 fill state (99 codes / 99 links) plus the
# verified 11-governorate / 63-wilayat tree. Run from repo root:
# python3 docs/agents/audit/gate_om.py
A = './packages/addressing/resources/geography/oman-address-areas.csv'
C = './packages/addressing/resources/geography/oman-postal-codes.csv'
L = './packages/addressing/resources/geography/oman-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: ISO 3166-2:OM + Provinces-of-Oman oracle ---
check('areas-74', len(rows) == 74, str(len(rows)))
check('governorates-11', sum(1 for r in rows if r['type'] == 'governorate') == 11)
check('wilayats-63', sum(1 for r in rows if r['type'] == 'wilayat') == 63)
iso = {'ad-dakhiliyah': 'DA', 'ad-dhahirah': 'ZA',
       'al-batinah-north': 'BS', 'al-batinah-south': 'BJ',
       'al-buraimi': 'BU', 'al-wusta': 'WU',
       'ash-sharqiyah-north': 'SS', 'ash-sharqiyah-south': 'SJ',
       'dhofar': 'ZU', 'muscat': 'MA', 'musandam': 'MU'}
for slug, code in iso.items():
    r = byid.get(f'om:governorate:{slug}')
    check(f'iso-{code}', bool(r) and r['code'] == code and r['level'] == '1', str(r))
kids = {'ad-dakhiliyah': 9, 'ad-dhahirah': 3, 'al-batinah-north': 6,
        'al-batinah-south': 6, 'al-buraimi': 3, 'al-wusta': 4,
        'ash-sharqiyah-north': 7, 'ash-sharqiyah-south': 5, 'dhofar': 10,
        'muscat': 6, 'musandam': 4}
for gov, n in kids.items():
    have = [r for r in rows if r['parent_source_id'] == f'om:governorate:{gov}']
    check(f'gov-{gov}-{n}', len(have) == n, str(len(have)))
# Post-2022 wilayats + Buraimi split (2006): Dhahirah keeps Ibri/Yanqul/Dhank.
for slug, gov in [('jebel-akhdar', 'ad-dakhiliyah'), ('sinaw', 'ash-sharqiyah-north'),
                  ('al-buraimi', 'al-buraimi'), ('as-sunaynah', 'al-buraimi'),
                  ('mahdah', 'al-buraimi'), ('ibri', 'ad-dhahirah'),
                  ('yanqul', 'ad-dhahirah'), ('dhank', 'ad-dhahirah')]:
    check(f'parent-{slug}', byid[f'om:wilayat:{slug}']['parent_source_id'] == f'om:governorate:{gov}')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- overlay counts (fill state) ---
check('codes-99', len(codes) == 99, str(len(codes)))
check('links-99', len(links) == 99, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{3}$', c['code'])]
check('code-format-3digit', not bad, str(bad[:3]))
check('codes-sorted', [c['code'] for c in codes] == sorted(c['code'] for c in codes))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
nonw = [l for l in links if not l['area_source_id'].startswith('om:wilayat:')]
check('all-links-L2', not nonw, str(nonw[:3]))
from collections import Counter
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 99 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('no-secondaries', all(l['is_primary'] == 'true' for l in links))
# --- per-wilayat primary counts (58 covered, 5 codeless) ---
expect = {'seeb': 8, 'muttrah': 7, 'salalah': 7, 'bawshar': 6, 'sohar': 4,
          'al-musanaah': 3, 'ibri': 3, 'barka': 2, 'jalan-bani-bu-ali': 2,
          'mirbat': 2, 'nizwa': 2, 'saham': 2, 'samail': 2, 'sur': 2,
          'suwayq': 2, 'taqah': 2, 'al-mudhaibi': 2, 'adam': 1, 'al-amarat': 1,
          'al-awabi': 1, 'al-buraimi': 1, 'al-hamra': 1, 'al-jazer': 1,
          'al-kamil-wal-wafi': 1, 'al-khaburah': 1, 'al-qabil': 1,
          'as-sunaynah': 1, 'bahla': 1, 'bidbid': 1, 'bidiya': 1, 'bukha': 1,
          'dema-wa-thaieen': 1, 'dhalkut': 1, 'dhank': 1, 'dibba': 1,
          'haima': 1, 'ibra': 1, 'izki': 1, 'jalan-bani-bu-hassan': 1,
          'jebel-akhdar': 1, 'khasab': 1, 'liwa': 1, 'madha': 1, 'mahdah': 1,
          'manah': 1, 'masirah': 1, 'muqshin': 1, 'muscat': 1, 'nakhal': 1,
          'qurayyat': 1, 'rakhyut': 1, 'rustaq': 1, 'sadah': 1, 'shinas': 1,
          'sinaw': 1, 'thumrait': 1, 'wadi-bani-khaled': 1, 'yanqul': 1,
          'al-mazyona': 0, 'duqm': 0, 'mahout': 0,
          'shalim-and-the-hallaniyat-islands': 0, 'wadi-al-maawil': 0}
have = Counter()
for l in links:
    if l['is_primary'] == 'true':
        have[l['area_source_id'].split(':')[-1]] += 1
for slug, n in expect.items():
    check(f'count-{slug}-{n}', have[slug] == n, str(have[slug]))
plink = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
def pin(pc, slug):
    want = f'om:wilayat:{slug}'
    check(f'pin-{pc}-{slug}', plink.get(pc) == want, str(plink.get(pc)))
# UPU 01/2026 anchors.
pin('112', 'muttrah'); pin('133', 'bawshar'); pin('311', 'sohar')
# Muscat locality resolutions.
for pc, slug in [('111', 'seeb'), ('114', 'muttrah'), ('115', 'bawshar'),
                 ('116', 'bawshar'), ('117', 'muttrah'), ('118', 'bawshar'),
                 ('126', 'muttrah'), ('130', 'bawshar'), ('131', 'muttrah'),
                 ('132', 'seeb'), ('134', 'bawshar')]:
    pin(pc, slug)
# Adjudicated / weak rows (see carry-forwards): 127 Muttrah over the OSM
# Bawshar polygon (Yandex + LEI + ROP-station + sakan + dubizzle); 129 Seeb
# and 213 Salalah are OSM/road-only weak keeps.
pin('127', 'muttrah'); pin('129', 'seeb'); pin('213', 'salalah')
# Dhofar suburb + new-wilayat resolutions.
for pc, slug in [('212', 'salalah'), ('219', 'taqah'), ('221', 'mirbat'),
                 ('621', 'jebel-akhdar'), ('418', 'sinaw')]:
    pin(pc, slug)
# Batinah village resolutions (citypopulation census + OSM).
for pc, slug in [('313', 'al-musanaah'), ('314', 'al-musanaah'),
                 ('316', 'suwayq'), ('321', 'sohar'), ('322', 'sohar'),
                 ('327', 'sohar'), ('328', 'barka'), ('329', 'saham')]:
    pin(pc, slug)
# Sharqiyah / Dhahirah / Dakhiliyah resolutions. 423/424 split: PF+NTD beat
# the YBK 423-dupe. 517/518 follow current Buraimi admin over the stale PF
# "Ad Dhahirah" region label. 615 is Samail, not Nizwa.
for pc, slug in [('419', 'al-qabil'), ('422', 'jalan-bani-bu-ali'),
                 ('423', 'al-mudhaibi'), ('424', 'dema-wa-thaieen'),
                 ('515', 'ibri'), ('516', 'ibri'), ('517', 'as-sunaynah'),
                 ('518', 'mahdah'), ('615', 'samail'), ('616', 'nizwa')]:
    pin(pc, slug)
# Exclusions: 100 is addressed-only (no office allocation); 138/zng-x00 are
# single-source; zng branch IDs are not postcodes.
for pc in ['100', '138', '400', '500', '600', '700', '800', '169', '168']:
    check(f'excluded-{pc}', pc not in plink, str(plink.get(pc)))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
