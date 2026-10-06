import csv, re, sys
from collections import Counter
# Laos gate. Pins the B15 fix pass: Meun code 10-11 -> 10-13, Savannakhet
# 13-10/13-14/13-15 identity fixes (Xonbuly / Xayphoothong / Phalanxay),
# Xaisomboun 18-02/18-03/18-05 rotation (Thathom / Longchaeng / Longxan),
# Vientiane-prefecture qualifier strips (Sangthong, Mayparkngum), and the
# postal zone fixes (Champasak 16010 -> 16000, Xaisomboun 10000 -> 18000):
# 166 areas (18 L1 + 148 L2), 26 codes, 148 legs.
# Oracles: COD-AB v01 (2019-11-12) p-codes, LSB 2015 census village
# shapefile (district usid + uuid geocodes + village names), LSB/UNFPA
# district projections report (code-ordered tables), EPL official postcode
# API (7,781 village rows, 2025-01-20), Mapanet district pages, wiki
# province articles, citypopulation. Asserts POST-fix state; run from
# repo root: python3 docs/agents/audit/gate_la.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/laos-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/laos-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/laos-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
def is_crlf(raw):
    return b'\r\n' in raw and b'\r' not in raw.replace(b'\r\n', b'') and b'\n' not in raw.replace(b'\r\n', b'')
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
check('codes-CRLF', is_crlf(raw_c))
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF', is_crlf(raw_l))
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
# --- tree: 18 L1 + 148 L2 ---
check('areas-166', len(rows) == 166, str(len(rows)))
check('l1-18', sum(1 for r in rows if r['level'] == '1') == 18)
check('l2-148', sum(1 for r in rows if r['level'] == '2') == 148)
check('prefecture-1', sum(1 for r in rows if r['type'] == 'prefecture') == 1)
check('provinces-17', sum(1 for r in rows if r['type'] == 'province') == 17)
iso = {'la:prefecture:vientiane': ('Vientiane', 'VT'),
       'la:province:attapeu': ('Attapeu', 'AT'),
       'la:province:bokeo': ('Bokeo', 'BK'),
       'la:province:bolikhamsai': ('Bolikhamsai', 'BL'),
       'la:province:champasak': ('Champasak', 'CH'),
       'la:province:houaphanh': ('Houaphanh', 'HO'),
       'la:province:khammouane': ('Khammouane', 'KH'),
       'la:province:luang-namtha': ('Luang Namtha', 'LM'),
       'la:province:luang-prabang': ('Luang Prabang', 'LP'),
       'la:province:oudomxay': ('Oudomxay', 'OU'),
       'la:province:phongsaly': ('Phongsaly', 'PH'),
       'la:province:sainyabuli': ('Sainyabuli', 'XA'),
       'la:province:salavan': ('Salavan', 'SL'),
       'la:province:savannakhet': ('Savannakhet', 'SV'),
       'la:province:sekong': ('Sekong', 'XE'),
       'la:province:vientiane': ('Vientiane', 'VI'),
       'la:province:xaisomboun': ('Xaisomboun', 'XS'),
       'la:province:xiangkhouang': ('Xiangkhouang', 'XI')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'l1-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
per_l2 = {'la:prefecture:vientiane': 9, 'la:province:phongsaly': 7,
          'la:province:luang-namtha': 5, 'la:province:oudomxay': 7,
          'la:province:bokeo': 5, 'la:province:luang-prabang': 12,
          'la:province:houaphanh': 10, 'la:province:sainyabuli': 11,
          'la:province:xiangkhouang': 7, 'la:province:vientiane': 11,
          'la:province:bolikhamsai': 7, 'la:province:khammouane': 10,
          'la:province:savannakhet': 15, 'la:province:salavan': 8,
          'la:province:sekong': 4, 'la:province:champasak': 10,
          'la:province:attapeu': 5, 'la:province:xaisomboun': 5}
got = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
for sid, n in per_l2.items():
    check(f'l2-{sid.split(":")[-1]}', got.get(sid) == n, f'{got.get(sid)} != {n}')
# admin codes code-sorted within each parent block (file order)
blocks = {}
for r in rows:
    if r['level'] == '2':
        blocks.setdefault(r['parent_source_id'], []).append(r['code'])
for sid, cs in blocks.items():
    check(f'codesorted-{sid.split(":")[-1]}', cs == sorted(cs), str(cs))
# --- B15 tree fixes ---
fixes = {'la:district:meun': ('Meun', '10-13', 'la:province:vientiane'),
         'la:district:xonbuly': ('Xonbuly', '13-10', 'la:province:savannakhet'),
         'la:district:xayphoothong': ('Xayphoothong', '13-14', 'la:province:savannakhet'),
         'la:district:phalanxay': ('Phalanxay', '13-15', 'la:province:savannakhet'),
         'la:district:thathom': ('Thathom', '18-02', 'la:province:xaisomboun'),
         'la:district:longchaeng': ('Longchaeng', '18-03', 'la:province:xaisomboun'),
         'la:district:longxan': ('Longxan', '18-05', 'la:province:xaisomboun'),
         'la:district:sangthong': ('Sangthong', '1-08', 'la:prefecture:vientiane'),
         'la:district:mayparkngum': ('Mayparkngum', '1-09', 'la:prefecture:vientiane')}
for sid, (name, code, par) in fixes.items():
    r = byid.get(sid)
    check(f'fixed-{sid.split(":")[-1]}', bool(r) and r['name'] == name
          and r['code'] == code and r['parent_source_id'] == par
          and r['level'] == '2', str(r))
for sid in ['la:district:xonaboury', 'la:district:xonboury',
            'la:district:thaphalanxay', 'la:district:sangthong-district',
            'la:district:mayparkngum-district']:
    check(f'oldslug-gone-{sid.split(":")[-1]}', sid not in byid, sid)
# retired VI codes stay absent (COD-AB LA10 gap 11/12)
allcodes = {r['code'] for r in rows if r['level'] == '2'}
check('no-10-11', '10-11' not in allcodes, 'retired; Meun is 10-13')
check('no-10-12', '10-12' not in allcodes, 'retired')
# transliteration holds (common-English spellings kept)
holds = {'la:district:et': 'Et', 'la:district:mok-may': 'Mok May',
         'la:district:sainyabuli': 'Sainyabuli',
         'la:district:luang-prabang': 'Luang Prabang',
         'la:district:hinhurp': 'Hinhurp', 'la:district:hom': 'Hom',
         'la:district:yot-ou': 'Yot Ou', 'la:district:houne': 'Houne',
         'la:district:ta-oy': 'Ta Oy', 'la:district:lao-ngam': 'Lao Ngam'}
for sid, name in holds.items():
    r = byid.get(sid)
    check(f'held-{sid.split(":")[-1]}', bool(r) and r['name'] == name, str(r))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 26 codes / 148 legs, zone model + VTE subs ---
check('codes-26', len(codes) == 26, str(len(codes)))
check('links-148', len(links) == 148, str(len(links)))
check('codes-LA', all(c['country_code'] == 'LA' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('codes-sorted', clist == sorted(clist))
check('no-16010', '16010' not in clist, 'Champasack-district office; unmapped')
check('has-16000', '16000' in clist)
check('has-18000', '18000' in clist)
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
exp_legs = {'01000': 1, '01010': 1, '01030': 1, '01080': 1, '01090': 1,
            '01120': 1, '01140': 1, '01160': 1, '01170': 1, '02000': 7,
            '03000': 5, '04000': 7, '05000': 5, '06000': 12, '07000': 10,
            '08000': 11, '09000': 7, '10000': 11, '11000': 7, '12000': 10,
            '13000': 15, '14000': 8, '15000': 4, '16000': 10, '17000': 5,
            '18000': 5}
for code, n in exp_legs.items():
    check(f'legs-{code}', have.get(code) == n, f'{have.get(code)} != {n}')
# every leg resolves; each district exactly one leg; single-province zones
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
per_area = Counter(l['area_source_id'] for l in links)
check('one-leg-per-district', len(per_area) == 148 and all(v == 1 for v in per_area.values()))
zone_provs = {}
for l in links:
    zone_provs.setdefault(l['postcode'], set()).add(byid[l['area_source_id']]['parent_source_id'])
check('single-province-zones', all(len(v) == 1 for v in zone_provs.values()),
      str({k: v for k, v in zone_provs.items() if len(v) != 1}))
# one primary per zone = capital district
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-zone', all(len(v) == 1 for v in prims.values()) and len(prims) == 26,
      str({k: v for k, v in prims.items() if len(v) != 1}))
anchors = {'01000': 'la:district:chanthabuly', '01010': 'la:district:sikhottabong',
           '01030': 'la:district:sisattanak', '01080': 'la:district:sangthong',
           '01090': 'la:district:mayparkngum', '01120': 'la:district:hadxayfong',
           '01140': 'la:district:naxaithong', '01160': 'la:district:xaysetha',
           '01170': 'la:district:xaythany', '02000': 'la:district:phongsaly',
           '03000': 'la:district:namtha', '04000': 'la:district:xay',
           '05000': 'la:district:houayxay', '06000': 'la:district:luang-prabang',
           '07000': 'la:district:xam-neua', '08000': 'la:district:sainyabuli',
           '09000': 'la:district:pek', '10000': 'la:district:phonhong',
           '11000': 'la:district:pakxan', '12000': 'la:district:thakhek',
           '13000': 'la:district:kaysone-phomvihane', '14000': 'la:district:saravane',
           '15000': 'la:district:la-mam', '16000': 'la:district:pakse',
           '17000': 'la:district:samakkhixay', '18000': 'la:district:anouvong'}
for code, sid in anchors.items():
    check(f'anchor-{code}', prims.get(code) == [sid], str(prims.get(code)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
