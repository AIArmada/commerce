import csv, hashlib, re, sys
from collections import Counter
# Sri Lanka geography gate. Pins the B7 VERIFY-ONLY state (no fixes):
# 34 areas (9 provinces + 25 districts), 2121 codes / 2121 links, all primary.
# Oracles: ISO 3166-2:LK, SL Post Post Code Directory (2022 PDF book),
# SL Post online lookup (postcode_new), GeoNames LK.zip, calllanka, advice.lk.
# Run from repo root: python3 docs/agents/audit/gate_lk.py
A = './packages/addressing/resources/geography/sri-lanka-address-areas.csv'
C = './packages/addressing/resources/geography/sri-lanka-postal-codes.csv'
L = './packages/addressing/resources/geography/sri-lanka-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# --- EOL / trailing newline assertions (raw bytes) ---
for path, label in [(A, 'areas'), (C, 'codes'), (L, 'links')]:
    raw = open(path, 'rb').read()
    check(f'eol-{label}-lf', b'\r' not in raw)
    check(f'eol-{label}-trailing-nl', raw.endswith(b'\n'))
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: ISO 3166-2:LK oracle (9 provinces + 25 districts) ---
check('areas-34', len(rows) == 34, str(len(rows)))
check('provinces-9', sum(1 for r in rows if r['type'] == 'province') == 9)
check('districts-25', sum(1 for r in rows if r['type'] == 'district') == 25)
kids = {'western': 3, 'central': 3, 'southern': 3, 'northern': 5,
        'eastern': 3, 'north-western': 2, 'north-central': 2,
        'uva': 2, 'sabaragamuwa': 2}
for prov, n in kids.items():
    have = [r for r in rows if r['parent_source_id'] == f'lk:province:{prov}']
    check(f'prov-{prov}-{n}', len(have) == n, str(len(have)))
check('prov-codes-1-9', sorted(r['code'] for r in rows if r['type'] == 'province') ==
      ['1', '2', '3', '4', '5', '6', '7', '8', '9'])
iso = {'11': 'colombo', '12': 'gampaha', '13': 'kalutara',
       '21': 'kandy', '22': 'matale', '23': 'nuwara-eliya',
       '31': 'galle', '32': 'matara', '33': 'hambantota',
       '41': 'jaffna', '42': 'kilinochchi', '43': 'mannar',
       '44': 'vavuniya', '45': 'mullaitivu',
       '51': 'batticaloa', '52': 'ampara', '53': 'trincomalee',
       '61': 'kurunegala', '62': 'puttalam',
       '71': 'anuradhapura', '72': 'polonnaruwa',
       '81': 'badulla', '82': 'monaragala',
       '91': 'ratnapura', '92': 'kegalle'}
have_iso = {r['code']: r['source_id'].split(':')[-1]
            for r in rows if r['type'] == 'district'}
check('iso-25', have_iso == iso,
      str([k for k in iso if have_iso.get(k) != iso[k]]))
# ISO rule: district code first digit == parent province code.
bad_parent = [r['source_id'] for r in rows if r['type'] == 'district'
              and (r['code'][0] != byid[r['parent_source_id']]['code'])]
check('iso-parent-rule', not bad_parent, str(bad_parent))
check('levels-1-2', all((r['level'] == '1') == (r['type'] == 'province') for r in rows))
# --- counts (verify-only: 2121 / 2121) ---
check('codes-2121', len(codes) == 2121, str(len(codes)))
check('links-2121', len(links) == 2121, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5digit', not bad, str(bad[:3]))
check('codes-country-LK', all(c['country_code'] == 'LK' for c in codes))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
dangc = [l['postcode'] for l in links
         if l['postcode'] not in {c['code'] for c in codes}]
check('no-dangling-codes', not dangc, str(dangc[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 2121 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
counts = Counter(l['postcode'] for l in links)
check('no-dual-links', all(c == 1 for c in counts.values()))
check('rel-served_by', all(l['relationship_type'] == 'served_by' for l in links))
# Files ship sorted ascending; keep them sorted.
cc = [c['code'] for c in codes]
check('codes-sorted', cc == sorted(cc))
lp = [l['postcode'] for l in links]
check('links-sorted', lp == sorted(lp))
check('same-order', cc == lp)
# --- per-district primary counts (full 25-row table, B7 verified) ---
expect = {'ampara': 67, 'anuradhapura': 134, 'badulla': 145,
          'batticaloa': 48, 'colombo': 71, 'galle': 96,
          'gampaha': 134, 'hambantota': 67, 'jaffna': 51,
          'kalutara': 84, 'kandy': 179, 'kegalle': 102,
          'kilinochchi': 28, 'kurunegala': 217, 'mannar': 25,
          'matale': 74, 'matara': 82, 'monaragala': 66,
          'mullaitivu': 18, 'nuwara-eliya': 79, 'polonnaruwa': 68,
          'puttalam': 90, 'ratnapura': 132, 'trincomalee': 43,
          'vavuniya': 21}
have = Counter()
for l in links:
    if l['is_primary'] == 'true':
        have[l['area_source_id'].split(':')[-1]] += 1
for slug, n in expect.items():
    check(f'count-{slug}-{n}', have[slug] == n, str(have[slug]))
check('counts-sum-2121', sum(have.values()) == 2121, str(sum(have.values())))
plink = {l['postcode']: l['area_source_id'] for l in links
         if l['is_primary'] == 'true'}
# --- Colombo cluster pins (SL Post book + online list 5; calllanka + advice.lk list all 15) ---
colombo15 = ['00100', '00200', '00300', '00400', '00500', '00600',
             '00700', '00800', '00900', '01000', '01100', '01200',
             '01300', '01400', '01500']
for pc in colombo15:
    check(f'pin-{pc}-colombo', plink.get(pc) == 'lk:district:colombo',
          str(plink.get(pc)))
# --- district main-office anchors (SL Post book + online + GeoNames agree) ---
for pc, slug in [('40000', 'jaffna'), ('20000', 'kandy'),
                 ('80000', 'galle'), ('50000', 'anuradhapura'),
                 ('60000', 'kurunegala'), ('70000', 'ratnapura'),
                 ('90000', 'badulla'), ('30000', 'batticaloa'),
                 ('31000', 'trincomalee'), ('32000', 'ampara')]:
    check(f'anchor-{pc}-{slug}', plink.get(pc) == f'lk:district:{slug}',
          str(plink.get(pc)))
# --- cross-block keeps: range-edge codes verified in SL Post oracles ---
keeps = {'10660': 'gampaha',  # 106xx Colombo-range office in Gampaha
         '10662': 'gampaha', '10664': 'gampaha',
         '42530': 'mullaitivu',  # 425xx Kilinochchi-range offices in Mullaitivu
         '42532': 'mullaitivu', '42534': 'mullaitivu',
         '43583': 'vavuniya',  # outlier code, SL Post book + online agree
         '32198': 'ampara',  # Kalmunai (AR) sub-range inside Ampara district
         '22680': 'nuwara-eliya',  # Ginigathhena; stale GeoNames twin is 20680
         '22684': 'nuwara-eliya',  # Kelanigama; stale GeoNames twin is 20688
         '22748': 'nuwara-eliya'}  # Maturata; renumbered from GeoNames-stale 20748
for pc, slug in keeps.items():
    check(f'keep-{pc}-{slug}', plink.get(pc) == f'lk:district:{slug}',
          str(plink.get(pc)))
# --- rejected stale GeoNames codes must stay absent (renumbers proven via SL Post book) ---
gone = ['20186',  # Kengalla: no such office in SL Post oracles
        '20560', '20566', '20567', '20568', '20588', '20590', '20592',  # -> 225xx
        '20660', '20668', '20669', '20670', '20678', '20680', '20682',  # -> 226xx
        '20684', '20686', '20688',
        '20742', '20744', '20748', '20750', '20752',  # -> 227xx
        '22040',  # Kottellena -> 22042
        '32155',  # Siripura: no Ampara office by that name in SL Post oracles
        '50567',  # Pulmoddai -> 31017 (Trincomalee; GeoNames district wrong too)
        '70252', '70254', '70256',  # -> 91252 / closed / 91256
        '81318',  # Karatota -> 81308
        '82401',  # Goda Koggalla -> 82104
        '82586',  # Gangulandeniya -> 82506
        '91040', '91042',  # Inginiyagala/Nelliyadda -> 32040/32042 (Ampara, not Monaragala)
        '96167']  # Idalgashinna -> 90167
have_codes = {c['code'] for c in codes}
check('rejects-absent', not [g for g in gone if g in have_codes],
      str([g for g in gone if g in have_codes]))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
