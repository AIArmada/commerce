import csv, re, sys
from collections import Counter
A = './packages/addressing/resources/geography/djibouti-address-areas.csv'
C = './packages/addressing/resources/geography/djibouti-postal-codes.csv'
L = './packages/addressing/resources/geography/djibouti-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-26', len(rows) == 26, str(len(rows)))
check('codes-10', len(codes) == 10, str(len(codes)))
check('links-10', len(links) == 10, str(len(links)))
check('regions-5', sum(1 for r in rows if r['type'] == 'region') == 5)
check('city-1', sum(1 for r in rows if r['type'] == 'city') == 1)
check('subpref-20', sum(1 for r in rows if r['type'] == 'subprefecture') == 20)
# ISO 3166-2:DJ L1 codes (DJ = city, rest regions).
iso = {'dj:region:ali-sabieh': 'AS', 'dj:region:arta': 'AR',
       'dj:region:dikhil': 'DI', 'dj:city:djibouti': 'DJ',
       'dj:region:obock': 'OB', 'dj:region:tadjourah': 'TA'}
for sid, code in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', r is not None and r['code'] == code, str(r))
# Per-parent counts. lac-assal sits under Tadjourah per 4 signals
# (2024 census placement, UPU 77601 routing, GeoNames admin1 DJ-05,
# Sagallo settlement); zero sources put it in Arta.
for p, n in [('dj:region:ali-sabieh', 3), ('dj:region:arta', 1),
             ('dj:region:dikhil', 4), ('dj:city:djibouti', 1),
             ('dj:region:obock', 4), ('dj:region:tadjourah', 7)]:
    have = [r for r in rows if r['parent_source_id'] == p]
    check(f'{p.split(":")[-1]}-{n}', len(have) == n, str(len(have)))
# Deliberate deviations: town-article Adailou (flats: Adaylou/Adayllou),
# French Lac Assal (map + UPU), district-title Tadjoura / Khor Angar.
check('adailou-spelling', byid['dj:subprefecture:adailou']['name'] == 'Adailou')
check('adailou-tadjourah', byid['dj:subprefecture:adailou']['parent_source_id'] == 'dj:region:tadjourah')
check('lac-assal-spelling', byid['dj:subprefecture:lac-assal']['name'] == 'Lac Assal')
check('lac-assal-tadjourah', byid['dj:subprefecture:lac-assal']['parent_source_id'] == 'dj:region:tadjourah')
check('tadjoura-subpref', byid['dj:subprefecture:tadjoura']['name'] == 'Tadjoura')
check('khor-angar', byid['dj:subprefecture:khor-angar']['name'] == 'Khor Angar')
check("dadda-to", byid['dj:subprefecture:dadda-to']['name'] == "Dadda'to")
check('holhol-ali-sabieh', byid['dj:subprefecture:holhol']['parent_source_id'] == 'dj:region:ali-sabieh')
# UPU DJI profile (05/2020) exact 10-code set; 5 digits, 77 prefix.
have_codes = sorted(c['code'] for c in codes)
want = ['77101', '77102', '77103', '77104', '77105', '77201',
        '77301', '77401', '77501', '77601']
check('upu-10-set', have_codes == want, str(have_codes))
bad = [c for c in have_codes if not re.match(r'^77[1-6]\d{2}$', c)]
check('code-format', not bad, str(bad[:3]))
# Link anchors: 5 city codes -> Djibouti subpref, 5 ville codes -> capitals.
# 77102-77104 single-evidence (UPU table only); rest dual-confirmed.
bycode = {l['postcode']: l['area_source_id'] for l in links}
anchors = {'77101': 'dj:subprefecture:djibouti', '77102': 'dj:subprefecture:djibouti',
           '77103': 'dj:subprefecture:djibouti', '77104': 'dj:subprefecture:djibouti',
           '77105': 'dj:subprefecture:djibouti', '77201': 'dj:subprefecture:arta',
           '77301': 'dj:subprefecture:ali-sabieh', '77401': 'dj:subprefecture:dikhil',
           '77501': 'dj:subprefecture:obock', '77601': 'dj:subprefecture:tadjoura'}
for pc, sid in anchors.items():
    check(f'{pc}-{sid.split(":")[-1]}', bycode.get(pc) == sid, str(bycode.get(pc)))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
check('exactly-one-primary', len(prim) == 10 and all(c == 1 for c in prim.values()))
# Thin by design: rural routes via capital codes (UPU examples), so only
# the 6 capital subprefs link. 6/20 is complete coverage, not a gap.
linked = sorted(set(bycode.values()))
check('thin-6-capitals', linked == sorted(
    ['dj:subprefecture:djibouti', 'dj:subprefecture:arta', 'dj:subprefecture:ali-sabieh',
     'dj:subprefecture:dikhil', 'dj:subprefecture:obock', 'dj:subprefecture:tadjoura']), str(linked))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
