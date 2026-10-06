import csv, sys
from collections import Counter
# Taiwan gate. Pins the B19 worker pass (390 areas: 22 L1 + 368
# L2; 365 codes / 368 links; areas LF, postal pair pure CRLF): L1 ISO 3166-2:TW alpha-3
# 22/22 exact. THIRTY-ONE mis-parents fixed: 18 Chiayi-county
# townships (MOI 10010xxx) moved city->county (CYI 20->2, CYQ
# 0->18), 13 Hsinchu-county townships (MOI 10004xxx) moved
# city->county (HSZ 16->3, HSQ 0->13) — 4 signals per cell
# (Chunghwa Post operator menu js + twzipcode-data npm + en.wiki
# infoboxes + Wikidata P131). Postal links CLEAN, zero moves
# (0/368 twz-diff, 368/368 WD zip match): 300 = 3 Hsinchu-city
# districts (North primary), 600 = 2 Chiayi-city districts
# (East primary). Prefix-flag 0/368 NOT a defect: L1 codes are
# ISO alpha-3, L2 are MOI 8-digit — cross-scheme check can never
# match; within-scheme MOI-5-prefix is clean. Holds: disputed
# islands 290/817/819 correctly unbundled; WD extras + Alishan
# type kept; wiki-townlist diffs are parser artefacts.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_tw.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/taiwan-address-areas.csv'
C = f'{G}/taiwan-postal-codes.csv'
L = f'{G}/taiwan-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
raw_c = open(C, 'rb').read()
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 366)
raw_l = open(L, 'rb').read()
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 369)
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-390', len(rows) == 390, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-22', sum(1 for r in rows if r['level'] == '1') == 22)
check('L2-368', sum(1 for r in rows if r['level'] == '2') == 368)
iso = {'changhua': 'CHA', 'chiayi': 'CYI', 'chiayi-county': 'CYQ',
       'hsinchu-county': 'HSQ', 'hsinchu': 'HSZ', 'hualien': 'HUA',
       'yilan': 'ILA', 'keelung': 'KEE', 'kaohsiung': 'KHH',
       'kinmen': 'KIN', 'lienchiang': 'LIE', 'miaoli': 'MIA',
       'nantou': 'NAN', 'new-taipei': 'NWT', 'penghu': 'PEN',
       'pingtung': 'PIF', 'taoyuan': 'TAO', 'tainan': 'TNN',
       'taipei': 'TPE', 'taitung': 'TTT', 'taichung': 'TXG',
       'yunlin': 'YUN'}
have_iso = {r['source_id'].split(':')[-1]: r['code'] for r in rows if r['level'] == '1'}
check('iso-22', all(have_iso.get(s) == c for s, c in iso.items()) and len(iso) == 22)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
have = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
check('CYI-2', have['tw:city:chiayi'] == 2, str(have['tw:city:chiayi']))
check('CYQ-18', have['tw:county:chiayi-county'] == 18, str(have['tw:county:chiayi-county']))
check('HSZ-3', have['tw:city:hsinchu'] == 3, str(have['tw:city:hsinchu']))
check('HSQ-13', have['tw:county:hsinchu-county'] == 13, str(have['tw:county:hsinchu-county']))
check('fix-zhubei', byid.get('tw:county_administered_city:zhubei-city', {}).get('parent_source_id') == 'tw:county:hsinchu-county')
check('fix-taibao', byid.get('tw:county_administered_city:taibao-city', {}).get('parent_source_id') == 'tw:county:chiayi-county')
check('fix-jianshi', byid.get('tw:mountain_indigenous_township:jianshi-township', {}).get('parent_source_id') == 'tw:county:hsinchu-county')
check('fix-alishan', byid.get('tw:mountain_indigenous_township:alishan-township', {}).get('parent_source_id') == 'tw:county:chiayi-county')
# Within-scheme MOI 5-digit-prefix per parent (the real invariant).
from collections import defaultdict
pref = defaultdict(set)
for r in rows:
    if r['level'] == '2':
        pref[r['parent_source_id']].add(r['code'][:5])
check('CYQ-prefix-10010', pref['tw:county:chiayi-county'] == {'10010'}, str(pref['tw:county:chiayi-county']))
check('HSQ-prefix-10004', pref['tw:county:hsinchu-county'] == {'10004'}, str(pref['tw:county:hsinchu-county']))
check('CYI-prefix-10020', pref['tw:city:chiayi'] == {'10020'}, str(pref['tw:city:chiayi']))
check('HSZ-prefix-10018', pref['tw:city:hsinchu'] == {'10018'}, str(pref['tw:city:hsinchu']))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-365', len(codes) == 365, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-368', len(legs) == 368, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('shared-300', sorted(bycode['300']) == [('tw:district:hsinchu:east-district', 'false'), ('tw:district:hsinchu:north-district', 'true'), ('tw:district:xiangshan-district', 'false')])
check('shared-600', sorted(bycode['600']) == [('tw:district:chiayi:east-district', 'true'), ('tw:district:chiayi:west-district', 'false')])
check('zip-302-zhubei', bycode['302'] == [('tw:county_administered_city:zhubei-city', 'true')])
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
