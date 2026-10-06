import csv, re, sys
from collections import Counter
# Spain gate. Pins the B9 fix-and-fill pass (11068 codes / 11094 links,
# 26 duals) plus the ISO 3166-2:ES tree (17 communities + Ceuta/Melilla
# + 50 provinces). Oracles: Correos poblationalnucleus API (full 11150
# exact-hit sweep + all-province municipalities sweep, 7861 munis), INE
# Callejero, CartoCiudad, Nominatim, Wikidata P281, UPU ESP profile
# (Wayback 2018), GeoNames ES.zip. Run from repo root:
# python3 /tmp/geo-verify/B9/ES/gate_es.py
A = './packages/addressing/resources/geography/spain-address-areas.csv'
C = './packages/addressing/resources/geography/spain-postal-codes.csv'
L = './packages/addressing/resources/geography/spain-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
try:
    rows = list(csv.DictReader(open(A)))
    codes = list(csv.DictReader(open(C)))
    links = list(csv.DictReader(open(L)))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
# --- tree: 17 communities + 2 cities + 50 provinces (ISO 3166-2:ES) ---
check('areas-69', len(rows) == 69, str(len(rows)))
check('communities-17', sum(1 for r in rows if r['type'] == 'autonomous_community') == 17)
check('cities-2', sum(1 for r in rows if r['type'] == 'autonomous_city') == 2)
check('provinces-50', sum(1 for r in rows if r['type'] == 'province') == 50)
iso_l1 = {'es:autonomous_community:andalusia': ('Andalusia', 'AN'),
          'es:autonomous_community:aragon': ('Aragon', 'AR'),
          'es:autonomous_community:catalonia': ('Catalonia', 'CT'),
          'es:autonomous_community:madrid': ('Madrid', 'MD'),
          'es:autonomous_community:basque-country': ('Basque Country', 'PV'),
          'es:autonomous_city:ceuta': ('Ceuta', 'CE'),
          'es:autonomous_city:melilla': ('Melilla', 'ML')}
for sid, (name, code) in iso_l1.items():
    r = byid.get(sid)
    check(f'iso-l1-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
iso_p = {'es:province:almeria': ('Almería', 'AL', 'andalusia'),
         'es:province:huesca': ('Huesca', 'HU', 'aragon'),
         'es:province:zaragoza': ('Zaragoza', 'Z', 'aragon'),
         'es:province:barcelona': ('Barcelona', 'B', 'catalonia'),
         'es:province:madrid': ('Madrid', 'M', 'madrid'),
         'es:province:araba': ('Araba', 'VI', 'basque-country'),
         'es:province:bizkaia': ('Bizkaia', 'BI', 'basque-country'),
         'es:province:gipuzkoa': ('Gipuzkoa', 'SS', 'basque-country')}
for sid, (name, code, par) in iso_p.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '2'
          and r['parent_source_id'] == f'es:autonomous_community:{par}', str(r))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
check('l1-no-parents', all(r['parent_source_id'] == '' for r in rows if r['level'] == '1'))
# --- postal counts: 11068 codes / 11094 links (CRLF) ---
check('codes-11068', len(codes) == 11068, str(len(codes)))
check('links-11094', len(links) == 11094, str(len(links)))
check('country-ES', all(c['country_code'] == 'ES' for c in codes))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5digit', not bad, str(bad[:3]))
check('codes-unique', len({c['code'] for c in codes}) == 11068)
check('codes-sorted', [c['code'] for c in codes] == sorted(c['code'] for c in codes))
lkeys = [(l['postcode'], l['area_source_id']) for l in links]
check('links-sorted', lkeys == sorted(lkeys))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
badrel = [l for l in links if l['relationship_type'] != 'served_by' or l['is_primary'] not in ('true', 'false')]
check('link-shape', not badrel, str(badrel[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 11068 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('every-code-linked', {c['code'] for c in codes} == {l['postcode'] for l in links})
counts = Counter(l['postcode'] for l in links)
duals = sorted(pc for pc, c in counts.items() if c == 2)
check('duals-26', duals == ['01118', '01211', '01427', '03657', '08281', '13110', '13249', '14113', '16612', '18312',
      '22583', '22584', '22808', '26212', '26528', '28190', '28600', '33554', '34492', '39232', '39250',
      '39419', '43421', '44591', '45216', '50686'], str(duals))
check('no-triples', all(c <= 2 for c in counts.values()))
# --- per-prefix code counts (full 52-row table) ---
expect = {'01': 81, '02': 131, '03': 215, '04': 168, '05': 148, '06': 216,
 '07': 163, '08': 397, '09': 216, '10': 246, '11': 123, '12': 134,
 '13': 130, '14': 141, '15': 388, '16': 183, '17': 248, '18': 203,
 '19': 181, '20': 110, '21': 106, '22': 275, '23': 175, '24': 419,
 '25': 301, '26': 132, '27': 463, '28': 316, '29': 164, '30': 207,
 '31': 265, '32': 298, '33': 404, '34': 132, '35': 147, '36': 384,
 '37': 276, '38': 220, '39': 214, '40': 205, '41': 156, '42': 99,
 '43': 212, '44': 211, '45': 232, '46': 302, '47': 195, '48': 143,
 '49': 300, '50': 276, '51': 8, '52': 9}
have = Counter(c['code'][:2] for c in codes)
for px, n in expect.items():
    check(f'prefix-{px}-{n}', have[px] == n, str(have[px]))
check('prefix-sum-11068', sum(have.values()) == 11068, str(sum(have.values())))
# --- per-code pins: clusters + duals + fixed cells ---
plink = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
secl = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'false'}
anchors = {
 '28001': 'es:province:madrid', '28004': 'es:province:madrid',
 '28050': 'es:province:madrid', '28071': 'es:province:madrid',
 '28412': 'es:province:madrid',
 '08001': 'es:province:barcelona', '08002': 'es:province:barcelona',
 '08070': 'es:province:barcelona', '08970': 'es:province:barcelona',
 '51001': 'es:autonomous_city:ceuta', '52001': 'es:autonomous_city:melilla',
 # cross-prefix single primaries (kept)
 '14449': 'es:province:ciudad-real', '22806': 'es:province:zaragoza',
 '26127': 'es:province:soria',
 # single-source holds (unchanged singles)
 '18538': 'es:province:granada', '23296': 'es:province:jaen',
 '45217': 'es:province:toledo',
 # added codes
 '01070': 'es:province:araba', '09117': 'es:province:burgos',
 '21431': 'es:province:huelva', '24359': 'es:province:leon',
 '50221': 'es:province:zaragoza',
 # moved primaries
 '28310': 'es:province:madrid', '42269': 'es:province:soria',
 '06691': 'es:province:caceres'}
for pc, want in anchors.items():
    check(f'anchor-{pc}', plink.get(pc) == want, str(plink.get(pc)))
dualexp = {'01118': ('es:province:araba', 'es:province:burgos'),
 '01211': ('es:province:araba', 'es:province:burgos'),
 '01427': ('es:province:araba', 'es:province:burgos'),
 '03657': ('es:province:alicante', 'es:province:murcia'),
 '08281': ('es:province:barcelona', 'es:province:lleida'),
 '13110': ('es:province:ciudad-real', 'es:province:badajoz'),
 '13249': ('es:province:ciudad-real', 'es:province:albacete'),
 '14113': ('es:province:cordoba', 'es:province:sevilla'),
 '16612': ('es:province:cuenca', 'es:province:albacete'),
 '18312': ('es:province:granada', 'es:province:cordoba'),
 '22583': ('es:province:huesca', 'es:province:lleida'),
 '22584': ('es:province:huesca', 'es:province:lleida'),
 '22808': ('es:province:huesca', 'es:province:zaragoza'),
 '26212': ('es:province:la-rioja', 'es:province:burgos'),
 '26528': ('es:province:la-rioja', 'es:province:zaragoza'),
 '28190': ('es:province:guadalajara', 'es:province:madrid'),
 '28600': ('es:province:madrid', 'es:province:toledo'),
 '33554': ('es:province:asturias', 'es:province:cantabria'),
 '34492': ('es:province:palencia', 'es:province:burgos'),
 '39232': ('es:province:cantabria', 'es:province:burgos'),
 '39250': ('es:province:cantabria', 'es:province:palencia'),
 '39419': ('es:province:cantabria', 'es:province:palencia'),
 '43421': ('es:province:tarragona', 'es:province:barcelona'),
 '44591': ('es:province:teruel', 'es:province:zaragoza'),
 '45216': ('es:province:toledo', 'es:province:madrid'),
 '50686': ('es:province:zaragoza', 'es:province:navarra')}
for pc, (p, s) in dualexp.items():
    check(f'dual-{pc}', plink.get(pc) == p and secl.get(pc) == s,
          f'p={plink.get(pc)} s={secl.get(pc)}')
# dropped legs are gone and the widowed codes are single-primary
for pc in ['28189', '28310', '42269']:
    check(f'single-{pc}', counts.get(pc) == 1 and plink.get(pc) is not None, str(counts.get(pc)))
check('no-28189-GU', ('28189', 'es:province:guadalajara') not in [(l['postcode'], l['area_source_id']) for l in links])
# removed codes are absent from both files
REM = ['03115', '03459', '03519', '03589', '06012', '07608', '08805',
 '09233', '09285', '09360', '10150', '10396', '11200', '11574', '12512',
 '13434', '15591', '16112', '16147', '19131', '26226', '27795', '27844',
 '28279', '28339', '28419', '28459', '28513', '28851', '28870', '29395',
 '30070', '30071', '30080', '30196', '30535', '32774', '33566', '33588',
 '33599', '33627', '33673', '33692', '33721', '33724', '33732', '33736',
 '33737', '33748', '33799', '33837', '33838', '33912', '34006', '34130',
 '34260', '36281', '36282', '36339', '37139', '37197', '37198', '37207',
 '37269', '37458', '37467', '37479', '37491', '37608', '37715', '38411',
 '40299', '42147', '42154', '42175', '42350', '43518', '43729', '44470',
 '45489', '46249', '47018', '49290', '50176', '50411', '50592', '50596']
code_set = {c['code'] for c in codes}
link_set = {l['postcode'] for l in links}
missing = [pc for pc in REM if pc in code_set or pc in link_set]
check('removed-87-absent', not missing, str(missing[:5]))
# --- EOL + trailing newlines (areas LF; codes/links CRLF) ---
rawA, rawC, rawL = open(A, 'rb').read(), open(C, 'rb').read(), open(L, 'rb').read()
check('areas-lf-only', rawA.count(b'\r') == 0 and rawA.count(b'\n') == 70,
      f"cr={rawA.count(bytes([13]))} lf={rawA.count(bytes([10]))}")
check('codes-crlf-11069', rawC.count(b'\r\n') == 11069 and rawC.count(b'\r') == 11069 and rawC.count(b'\n') == 11069,
      f"cr={rawC.count(bytes([13]))} lf={rawC.count(bytes([10]))}")
check('links-crlf-11095', rawL.count(b'\r\n') == 11095 and rawL.count(b'\r') == 11095 and rawL.count(b'\n') == 11095,
      f"cr={rawL.count(bytes([13]))} lf={rawL.count(bytes([10]))}")
check('areas-trailing-nl', rawA.endswith(b'\n') and not rawA.endswith(b'\r\n'))
check('codes-trailing-crlf', rawC.endswith(b'\r\n'))
check('links-trailing-crlf', rawL.endswith(b'\r\n'))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
