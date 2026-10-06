import csv, re, sys
# Lebanon geography gate. Pins the CORRECTED state from the M1 revisit
# (688 codes / 701 links). Run from repo root: python3 docs/agents/audit/gate_lb.py
A = './packages/addressing/resources/geography/lebanon-address-areas.csv'
C = './packages/addressing/resources/geography/lebanon-postal-codes.csv'
L = './packages/addressing/resources/geography/lebanon-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: Districts-of-Lebanon oracle (9 govs + 25 cazas) ---
check('areas-34', len(rows) == 34, str(len(rows)))
check('governorates-9', sum(1 for r in rows if r['type'] == 'governorate') == 9)
check('cazas-25', sum(1 for r in rows if r['type'] == 'caza') == 25)
kids = {'akkar': 1, 'baalbek-hermel': 2, 'beirut': 0, 'beqaa': 3,
        'keserwan-jbeil': 2, 'mount-lebanon': 4, 'nabatieh': 4,
        'north': 6, 'south': 3}
for gov, n in kids.items():
    have = [r for r in rows if r['parent_source_id'] == f'lb:governorate:{gov}']
    check(f'gov-{gov}-{n}', len(have) == n, str(len(have)))
check('KJ-provisional', byid['lb:governorate:keserwan-jbeil']['code'] == 'KJ')
check('ISO8-codes', sorted(r['code'] for r in rows if r['type'] == 'governorate') ==
      ['AK', 'AS', 'BA', 'BH', 'BI', 'JA', 'JL', 'KJ', 'NA'])
# --- counts (corrected state) ---
check('codes-688', len(codes) == 688, str(len(codes)))
check('links-701', len(links) == 701, str(len(links)))
bad = [c['code'] for c in codes
       if not (re.match(r'^\d{4}$', c['code']) or c['code'] == '1107-2090')]
check('code-format', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
from collections import Counter
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 688 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
# --- per-area primary counts (corrected) ---
expect = {'akkar': 69, 'aley': 26, 'baabda': 25, 'baalbek': 44, 'batroun': 34,
          'beirut': 12, 'bint-jbeil': 22, 'bsharri': 17, 'byblos': 46,
          'chouf': 49, 'hasbaya': 6, 'hermel': 5, 'jezzine': 30,
          'keserwan': 27, 'koura': 22, 'marjeyoun': 6, 'matn': 44,
          'miniyeh-danniyeh': 31, 'nabatieh': 19, 'rashaya': 14, 'sidon': 37,
          'tripoli': 0, 'tyre': 37, 'western-beqaa': 19, 'zahle': 26,
          'zgharta': 21}
have = Counter()
for l in links:
    if l['is_primary'] == 'true':
        have[l['area_source_id'].split(':')[-1]] += 1
for slug, n in expect.items():
    check(f'count-{slug}-{n}', have[slug] == n, str(have[slug]))
# --- Beirut L1 set (no caza) ---
bl = sorted(l['postcode'] for l in links
            if l['area_source_id'] == 'lb:governorate:beirut')
check('beirut-12', bl == ['1100', '1101', '1102', '1103', '1104', '1105',
      '1106', '1107', '2020', '2030', '2050', '2832'], str(bl))
# --- hyphen keep: exactly one hyphen code, Hazmiyeh -> Baabda ---
hy = [c['code'] for c in codes if '-' in c['code']]
check('one-hyphen', hy == ['1107-2090'], str(hy))
check('hyphen-baabda', [l['area_source_id'] for l in links
      if l['postcode'] == '1107-2090'] == ['lb:caza:baabda'])
spaced = [c['code'] for c in codes if ' ' in c['code']]
check('no-spaced-codes', not spaced, str(spaced[:3]))
# --- Hermel fill: 5 new codes ---
for pc in ['8123', '8128', '8151', '8173', '8242']:
    got = [(l['area_source_id'], l['is_primary']) for l in links
           if l['postcode'] == pc]
    check(f'hermel-{pc}', got == [('lb:caza:hermel', 'true')], str(got))
# --- dual links: 12 codes, exact P+s ---
duals = {'3868': ('koura', ['bsharri']), '3914': ('bsharri', ['koura']),
         '4143': ('batroun', ['koura']), '4841': ('matn', ['keserwan']),
         '5203': ('aley', ['baabda']), '5428': ('aley', ['chouf']),
         '5722': ('chouf', ['aley']), '6292': ('sidon', ['jezzine']),
         '6776': ('jezzine', ['hasbaya']), '7352': ('marjeyoun', ['bint-jbeil']),
         '8119': ('baalbek', ['hermel']),
         '8226': ('baalbek', ['western-beqaa', 'zahle'])}
counts = Counter(l['postcode'] for l in links)
check('multi-codes-12', sorted(pc for pc, c in counts.items() if c > 1) ==
      sorted(duals), str(sorted(pc for pc, c in counts.items() if c > 1)))
for pc, (p, ss) in duals.items():
    got = sorted((l['area_source_id'].split(':')[-1], l['is_primary'])
                 for l in links if l['postcode'] == pc)
    want = sorted([(p, 'true')] + [(s, 'false') for s in ss])
    check(f'dual-{pc}', got == want, str(got))
# --- 18 retarget anchors (corrected primaries) ---
fixes = {'1835': 'zahle', '1855': 'rashaya', '3018': 'miniyeh-danniyeh',
         '3514': 'miniyeh-danniyeh', '3769': 'bsharri', '3911': 'bsharri',
         '4215': 'batroun', '4362': 'byblos', '4384': 'byblos',
         '5649': 'chouf', '6642': 'jezzine', '6710': 'jezzine',
         '6851': 'tyre', '6875': 'tyre', '6893': 'tyre',
         '7121': 'nabatieh', '7150': 'jezzine', '7192': 'nabatieh'}
plink = {l['postcode']: l['area_source_id'] for l in links
         if l['is_primary'] == 'true'}
for pc, slug in fixes.items():
    want = f'lb:caza:{slug}'
    check(f'fix-{pc}-{slug}', plink.get(pc) == want, str(plink.get(pc)))
# --- corroborated keep anchors (one+ per unchanged caza) ---
keeps = {'1001': 'aley', '1304': 'zgharta', '1341': 'miniyeh-danniyeh',
         '1400': 'batroun', '1645': 'tyre', '1741': 'bint-jbeil',
         '1801': 'zahle', '1851': 'western-beqaa', '1861': 'rashaya',
         '3103': 'akkar', '3223': 'akkar', '3606': 'miniyeh-danniyeh',
         '3730': 'zgharta', '3915': 'bsharri', '4240': 'batroun',
         '4307': 'byblos', '4407': 'byblos', '5277': 'baabda',
         '5420': 'aley', '6234': 'sidon', '6662': 'jezzine',
         '7104': 'nabatieh', '7464': 'marjeyoun', '7532': 'hasbaya',
         '8215': 'baalbek', '8356': 'baalbek'}
for pc, slug in keeps.items():
    want = f'lb:caza:{slug}'
    check(f'keep-{pc}-{slug}', plink.get(pc) == want, str(plink.get(pc)))
check('tripoli-zero', 'lb:caza:tripoli' not in
      {l['area_source_id'] for l in links})
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
