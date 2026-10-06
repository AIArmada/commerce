import csv, re, sys
from collections import Counter
# Namibia gate. Pins the B14 fix pass (2 name-only renames, slugs stable:
# Karas -> U+01C1 U+01C1 Karas per the GG5261 rename + ISO 3166-2:NA +
# WP; Okorukambe -> Okarukambe per GG5261 x2 + ECN 2024 advert) plus the
# verify-only remainder: 14 regions + 121 constituencies, 149-code NamPost
# poster overlay transcribed exactly (149/149 codes + attributions).
# Oracles: NamPost postcode poster PDF (149 offices), WP transcription +
# constituency list, GG5261 delimitation proclamation, ECN 2024 advert
# (full 121 enumeration), ECN 2020 post-election report, citypopulation
# census pages. Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_na.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/namibia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/namibia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/namibia-postal-code-areas.csv'
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
# --- tree: 14 regions + 121 constituencies ---
check('areas-135', len(rows) == 135, str(len(rows)))
check('regions-14', sum(1 for r in rows if r['type'] == 'region') == 14)
check('constituencies-121', sum(1 for r in rows if r['type'] == 'constituency') == 121)
check('l1-14', sum(1 for r in rows if r['level'] == '1') == 14)
check('l2-121', sum(1 for r in rows if r['level'] == '2') == 121)
iso = {'na:region:zambezi': ('Zambezi', 'CA'), 'na:region:erongo': ('Erongo', 'ER'),
       'na:region:hardap': ('Hardap', 'HA'), 'na:region:karas': ('ǁKaras', 'KA'),
       'na:region:kavango-east': ('Kavango East', 'KE'),
       'na:region:khomas': ('Khomas', 'KH'), 'na:region:kunene': ('Kunene', 'KU'),
       'na:region:kavango-west': ('Kavango West', 'KW'),
       'na:region:otjozondjupa': ('Otjozondjupa', 'OD'),
       'na:region:omaheke': ('Omaheke', 'OH'), 'na:region:oshana': ('Oshana', 'ON'),
       'na:region:omusati': ('Omusati', 'OS'), 'na:region:oshikoto': ('Oshikoto', 'OT'),
       'na:region:ohangwena': ('Ohangwena', 'OW')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'region-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
check('karas-clicks', byid.get('na:region:karas', {}).get('name') == 'ǁKaras')
per_region = {'na:region:ohangwena': 12, 'na:region:omusati': 12,
              'na:region:oshana': 11, 'na:region:oshikoto': 11,
              'na:region:khomas': 10, 'na:region:hardap': 8,
              'na:region:kavango-west': 8, 'na:region:zambezi': 8,
              'na:region:erongo': 7, 'na:region:kunene': 7,
              'na:region:omaheke': 7, 'na:region:otjozondjupa': 7,
              'na:region:karas': 7, 'na:region:kavango-east': 6}
got = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
for sid, n in per_region.items():
    check(f'l2-{sid.split(":")[-1]}', got.get(sid) == n, f'{got.get(sid)} != {n}')
check('okarukambe', byid.get('na:constituency:okorukambe', {}).get('name') == 'Okarukambe',
      str(byid.get('na:constituency:okorukambe')))
check('tondoro', byid.get('na:constituency:tondoro', {}).get('parent_source_id')
      == 'na:region:kavango-west', str(byid.get('na:constituency:tondoro')))
check('oshikunde', byid.get('na:constituency:oshikunde', {}).get('parent_source_id')
      == 'na:region:ohangwena', str(byid.get('na:constituency:oshikunde')))
spots = {'na:constituency:daures': ('Dâures', 'na:region:erongo'),
         'na:constituency:naminus': ('ǃNamiǂNûs', 'na:region:karas'),
         'na:constituency:moses-garoeb': ('Moses ǁGaroëb', 'na:region:khomas'),
         'na:constituency:ncamagoro': ('Ncamagoro', 'na:region:kavango-west'),
         'na:constituency:okatyali': ('Okatyali', 'na:region:oshana'),
         'na:constituency:sibbinda': ('Sibbinda', 'na:region:zambezi'),
         'na:constituency:omuthiyagwiipundi': ('Omuthiyagwiipundi', 'na:region:oshikoto'),
         'na:constituency:nehale-lyampingana': ('Nehale lyaMpingana', 'na:region:oshikoto')}
for sid, (name, par) in spots.items():
    r = byid.get(sid)
    check(f'spot-{sid.split(":")[-1]}', bool(r) and r['name'] == name
          and r['parent_source_id'] == par, str(r))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 149 codes / 149 links, all L1 region ---
check('codes-149', len(codes) == 149, str(len(codes)))
check('links-149', len(links) == 149, str(len(links)))
check('codes-NA', all(c['country_code'] == 'NA' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('codes-range', min(clist) == '10000' and max(clist) == '23017',
      f'{min(clist)}..{max(clist)}')
check('all-l1', all(byid[l['area_source_id']]['level'] == '1' for l in links))
check('all-primary', all(l['is_primary'] == 'true' for l in links))
check('zero-duals', len({l['postcode'] for l in links}) == 149)
prefix = {'10': 'na:region:khomas', '11': 'na:region:omaheke',
          '12': 'na:region:otjozondjupa', '13': 'na:region:erongo',
          '14': 'na:region:oshikoto', '15': 'na:region:oshana',
          '16': 'na:region:omusati', '17': 'na:region:ohangwena',
          '18': 'na:region:kavango-west', '19': 'na:region:kavango-east',
          '20': 'na:region:zambezi', '21': 'na:region:kunene',
          '22': 'na:region:hardap', '23': 'na:region:karas'}
badpx = [l['postcode'] for l in links
         if l['area_source_id'] != prefix.get(l['postcode'][:2])]
check('prefix-rule-149', not badpx, str(badpx[:3]))
exp_leg = {'na:region:khomas': 35, 'na:region:omaheke': 8,
           'na:region:otjozondjupa': 13, 'na:region:erongo': 15,
           'na:region:oshikoto': 11, 'na:region:oshana': 6,
           'na:region:omusati': 10, 'na:region:ohangwena': 10,
           'na:region:kavango-west': 2, 'na:region:kavango-east': 2,
           'na:region:zambezi': 5, 'na:region:kunene': 6,
           'na:region:hardap': 9, 'na:region:karas': 17}
have = Counter(l['area_source_id'] for l in links)
for sid, n in exp_leg.items():
    check(f'legs-{sid.split(":")[-1]}', have.get(sid) == n,
          f'{have.get(sid)} != {n}')
anchors = {'10000': 'na:region:khomas', '10005': 'na:region:khomas',
           '11001': 'na:region:omaheke', '13001': 'na:region:erongo',
           '17001': 'na:region:ohangwena', '18001': 'na:region:kavango-west',
           '19001': 'na:region:kavango-east', '20001': 'na:region:zambezi',
           '21001': 'na:region:kunene', '22001': 'na:region:hardap',
           '23001': 'na:region:karas', '23017': 'na:region:karas'}
pin = {l['postcode']: l['area_source_id'] for l in links}
for code, sid in anchors.items():
    check(f'anchor-{code}', pin.get(code) == sid, str(pin.get(code)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
