import csv, re, sys
from collections import Counter
# Bangladesh geography gate. Pins the CORRECTED state from the M3 revisit
# (1373 codes / 1373 links: 1349 GeoNames-vintage rows verified + 24 fills).
# Run from repo root: python3 docs/agents/audit/gate_bd.py
A = './packages/addressing/resources/geography/bangladesh-address-areas.csv'
C = './packages/addressing/resources/geography/bangladesh-postal-codes.csv'
L = './packages/addressing/resources/geography/bangladesh-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: ISO 3166-2:BD oracle (8 divisions + 64 districts) ---
check('areas-72', len(rows) == 72, str(len(rows)))
check('divisions-8', sum(1 for r in rows if r['type'] == 'division') == 8)
check('districts-64', sum(1 for r in rows if r['type'] == 'district') == 64)
kids = {'barishal': 6, 'chattogram': 11, 'dhaka': 13, 'khulna': 10,
        'mymensingh': 4, 'rajshahi': 8, 'rangpur': 8, 'sylhet': 4}
for div, n in kids.items():
    have = [r for r in rows if r['parent_source_id'] == f'bd:division:{div}']
    check(f'div-{div}-{n}', len(have) == n, str(len(have)))
check('div-codes-A-H', sorted(r['code'] for r in rows if r['type'] == 'division') ==
      ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'])
iso = {'01': 'bandarban', '02': 'barguna', '03': 'bogura',
       '04': 'brahmanbaria', '05': 'bagerhat', '06': 'barishal',
       '07': 'bhola', '08': 'cumilla', '09': 'chandpur',
       '10': 'chattogram', '11': 'cox-s-bazar', '12': 'chuadanga',
       '13': 'dhaka', '14': 'dinajpur', '15': 'faridpur', '16': 'feni',
       '17': 'gopalganj', '18': 'gazipur', '19': 'gaibandha',
       '20': 'habiganj', '21': 'jamalpur', '22': 'jashore',
       '23': 'jhenaidah', '24': 'joypurhat', '25': 'jhalakathi',
       '26': 'kishoreganj', '27': 'khulna', '28': 'kurigram',
       '29': 'khagrachhari', '30': 'kushtia', '31': 'lakshmipur',
       '32': 'lalmonirhat', '33': 'manikganj', '34': 'mymensingh',
       '35': 'munshiganj', '36': 'madaripur', '37': 'magura',
       '38': 'moulvibazar', '39': 'meherpur', '40': 'narayanganj',
       '41': 'netrokona', '42': 'narsingdi', '43': 'narail',
       '44': 'natore', '45': 'chapai-nawabganj', '46': 'nilphamari',
       '47': 'noakhali', '48': 'naogaon', '49': 'pabna',
       '50': 'pirojpur', '51': 'patuakhali', '52': 'panchagarh',
       '53': 'rajbari', '54': 'rajshahi', '55': 'rangpur',
       '56': 'rangamati', '57': 'sherpur', '58': 'satkhira',
       '59': 'sirajganj', '60': 'sylhet', '61': 'sunamganj',
       '62': 'shariatpur', '63': 'tangail', '64': 'thakurgaon'}
have_iso = {r['code']: r['source_id'].split(':')[-1]
            for r in rows if r['type'] == 'district'}
check('iso-64', have_iso == iso,
      str([k for k in iso if have_iso.get(k) != iso[k]]))
# Deliberate deviation: ISO still lists BD-41 as Netrakona; the official
# portal (netrokona.gov.bd, 200) + Districts-of-Bangladesh oracle use
# Netrokona, which is what we ship.
check('BD41-Netrokona', byid['bd:district:netrokona']['code'] == '41' and
      byid['bd:district:netrokona']['name'] == 'Netrokona')
# Post-2018 spellings present (2018 renames + portal-confirmed forms).
for slug, name in [('barishal', 'Barishal'), ('chattogram', 'Chattogram'),
                   ('bogura', 'Bogura'), ('jashore', 'Jashore'),
                   ('cumilla', 'Cumilla'), ('jhalakathi', 'Jhalakathi'),
                   ('netrokona', 'Netrokona')]:
    check(f'spell-{slug}', byid[f'bd:district:{slug}']['name'] == name)
# --- counts (corrected state: +24 fills) ---
check('codes-1373', len(codes) == 1373, str(len(codes)))
check('links-1373', len(links) == 1373, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{4}$', c['code'])]
check('code-format-4digit', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
dangc = [l['postcode'] for l in links
         if l['postcode'] not in {c['code'] for c in codes}]
check('no-dangling-codes', not dangc, str(dangc[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 1373 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
check('no-dual-links', all(c == 1 for c in counts.values()))
# Files ship sorted ascending; the applier must keep them sorted.
cc = [c['code'] for c in codes]
check('codes-sorted', cc == sorted(cc, key=int))
lp = [l['postcode'] for l in links]
check('links-sorted', lp == sorted(lp, key=int))
check('same-order', cc == lp)
# --- per-district primary counts (corrected) ---
expect = {'bagerhat': 26, 'bandarban': 8, 'barguna': 8, 'barishal': 43,
          'bhola': 14, 'bogura': 20, 'brahmanbaria': 28, 'chandpur': 26,
          'chapai-nawabganj': 12, 'chattogram': 100, 'chuadanga': 8,
          'cox-s-bazar': 14, 'cumilla': 42, 'dhaka': 52, 'dinajpur': 19,
          'faridpur': 17, 'feni': 20, 'gaibandha': 12, 'gazipur': 24,
          'gopalganj': 14, 'habiganj': 22, 'jamalpur': 20, 'jashore': 21,
          'jhalakathi': 12, 'jhenaidah': 9, 'joypurhat': 7,
          'khagrachhari': 8, 'khulna': 39, 'kishoreganj': 21,
          'kurigram': 13, 'kushtia': 17, 'lakshmipur': 26,
          'lalmonirhat': 9, 'madaripur': 13, 'magura': 8,
          'manikganj': 18, 'meherpur': 5, 'moulvibazar': 26,
          'munshiganj': 38, 'mymensingh': 34, 'naogaon': 19,
          'narail': 9, 'narayanganj': 21, 'narsingdi': 18,
          'natore': 14, 'netrokona': 18, 'nilphamari': 10,
          'noakhali': 60, 'pabna': 18, 'panchagarh': 6,
          'patuakhali': 16, 'pirojpur': 25, 'rajbari': 9,
          'rajshahi': 26, 'rangamati': 14, 'rangpur': 14,
          'satkhira': 25, 'shariatpur': 12, 'sherpur': 8,
          'sirajganj': 23, 'sunamganj': 26, 'sylhet': 55,
          'tangail': 43, 'thakurgaon': 11}
have = Counter()
for l in links:
    if l['is_primary'] == 'true':
        have[l['area_source_id'].split(':')[-1]] += 1
for slug, n in expect.items():
    check(f'count-{slug}-{n}', have[slug] == n, str(have[slug]))
plink = {l['postcode']: l['area_source_id'] for l in links
         if l['is_primary'] == 'true'}
# --- GPO anchors (UPU profile + official finder) ---
for pc, slug in [('1000', 'dhaka'), ('1100', 'dhaka'),
                 ('4000', 'chattogram'), ('6000', 'rajshahi'),
                 ('9000', 'khulna')]:
    check(f'gpo-{pc}-{slug}', plink.get(pc) == f'bd:district:{slug}',
          str(plink.get(pc)))
# --- official-corroborated anomaly keeps (cross-block codes are real) ---
keeps = {'1333': 'munshiganj', '1334': 'munshiganj', '1335': 'munshiganj',
         '3893': 'sunamganj', '5470': 'thakurgaon', '8013': 'gopalganj',
         '1360': 'dhaka'}
for pc, slug in keeps.items():
    check(f'keep-{pc}-{slug}', plink.get(pc) == f'bd:district:{slug}',
          str(plink.get(pc)))
# --- 24 fills (Mapanet + postcodebase + GPO-dir / 2024 official page) ---
fills = {'1236': 'dhaka', '1706': 'gazipur', '2206': 'mymensingh',
         '2207': 'mymensingh', '2208': 'mymensingh', '3118': 'sylhet',
         '3505': 'cumilla', '3512': 'cumilla', '3547': 'cumilla',
         '3573': 'cumilla', '3726': 'lakshmipur', '3727': 'lakshmipur',
         '3805': 'noakhali', '3813': 'noakhali', '4357': 'chattogram',
         '4640': 'bandarban', '6200': 'rajshahi', '6207': 'rajshahi',
         '6213': 'rajshahi', '6433': 'natore', '6501': 'naogaon',
         '8207': 'barishal', '8217': 'barishal', '8611': 'patuakhali'}
for pc, slug in fills.items():
    check(f'fill-{pc}-{slug}', plink.get(pc) == f'bd:district:{slug}',
          str(plink.get(pc)))
# --- rejected lookalikes must stay absent ---
gone = ['1661', '1662', '1663', '1664', '1665',  # Narsingdi block typos
        '2461',  # off-by-one of 2462 Shaldigha
        '4240',  # Chirirbandar misfiled to Chittagong by Mapanet
        '8920', '8921',  # 79xx->89xx digit typos of Kalkini 7920/7921
        '1218', '1231',  # 56ok/kabirhat corruptions of 1216/1230
        '1921',  # kabirhat misattribution of Singair offices
        '3515']  # superseded by 3512 per 2024 official Cumilla page
have_codes = {c['code'] for c in codes}
check('rejects-absent', not [g for g in gone if g in have_codes],
      str([g for g in gone if g in have_codes]))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
