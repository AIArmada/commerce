import csv, re, sys
from collections import Counter
# Liberia gate. Pins the B14 fix pass (2022-census scheme): 21 renames,
# 5 drops, 35 adds (127 -> 157 districts) plus the verify-only remainder:
# 15 counties, 31-code county-pure postal overlay, explicit holds.
# Oracles: LISGIS 2022 census final report App.B (primary, incl. exact
# per-table arithmetic), 11 county CDA Table 2s, COD-PS 2020 p-codes,
# Statoids 2008 census transcription, WP districts table, citypopulation
# county totals, philib post offices, UPU lbrEn.pdf.
# B14-PDF reconciliation (user-supplied final report, 2022 frame, all
# male+female=total): B2 gap +17986/+9513/+8473 seals Gounwolaila under
# Gbarpolu (row omitted from the B2 print); B3/B5 gaps -17986 (each) prove
# its two printed rows spurious dups (identical figures twice); B4 gap
# +14422/+7391/+7031 seals omitted Owensgrove; B8 gap +17478/+9107/+8371
# seals omitted Dugbe River; B11 sums exactly (no 8th district). The B14
# "Barobo 18,758" figure was void mixed-frame arithmetic (2008 county
# total vs 2022 district rows), is printed nowhere, and is STRUCK.
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_lr.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/liberia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/liberia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/liberia-postal-code-areas.csv'
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
# --- tree: 15 counties + 157 districts ---
check('areas-172', len(rows) == 172, str(len(rows)))
check('counties-15', sum(1 for r in rows if r['type'] == 'county') == 15)
check('districts-157', sum(1 for r in rows if r['type'] == 'district') == 157)
check('l1-15', sum(1 for r in rows if r['level'] == '1') == 15)
check('l2-157', sum(1 for r in rows if r['level'] == '2') == 157)
iso = {'lr:county:bomi': ('Bomi', 'BM'), 'lr:county:bong': ('Bong', 'BG'),
       'lr:county:gbarpolu': ('Gbarpolu', 'GP'),
       'lr:county:grand-bassa': ('Grand Bassa', 'GB'),
       'lr:county:grand-cape-mount': ('Grand Cape Mount', 'CM'),
       'lr:county:grand-gedeh': ('Grand Gedeh', 'GG'),
       'lr:county:grand-kru': ('Grand Kru', 'GK'),
       'lr:county:lofa': ('Lofa', 'LO'), 'lr:county:margibi': ('Margibi', 'MG'),
       'lr:county:maryland': ('Maryland', 'MY'),
       'lr:county:montserrado': ('Montserrado', 'MO'),
       'lr:county:nimba': ('Nimba', 'NI'),
       'lr:county:river-cess': ('River Cess', 'RI'),
       'lr:county:river-gee': ('River Gee', 'RG'),
       'lr:county:sinoe': ('Sinoe', 'SI')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'county-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
per_county = {'lr:county:bomi': 5, 'lr:county:bong': 12,
              'lr:county:gbarpolu': 6, 'lr:county:grand-bassa': 8,
              'lr:county:grand-cape-mount': 5, 'lr:county:grand-gedeh': 8,
              'lr:county:grand-kru': 19, 'lr:county:lofa': 11,
              'lr:county:margibi': 5, 'lr:county:maryland': 7,
              'lr:county:montserrado': 15, 'lr:county:nimba': 17,
              'lr:county:river-gee': 10, 'lr:county:river-cess': 8,
              'lr:county:sinoe': 21}
got = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
for sid, n in per_county.items():
    check(f'l2-{sid.split(":")[-1]}', got.get(sid) == n, f'{got.get(sid)} != {n}')
# --- 21 renames (post-fix slug -> name, parent) ---
renames = {'lr:district:panta': ('Panta', 'lr:county:bong'),
           'lr:district:sanoyeah': ('Sanoyeah', 'lr:county:bong'),
           'lr:district:neekreen': ('Neekreen', 'lr:county:grand-bassa'),
           'lr:district:st-john-river-city': ('St. John River City', 'lr:county:grand-bassa'),
           'lr:district:golakonneh': ('Golakonneh', 'lr:county:grand-cape-mount'),
           'lr:district:quardu-boundi': ('Quardu Boundi', 'lr:county:lofa'),
           'lr:district:farmington': ('Farmington', 'lr:county:margibi'),
           'lr:district:pleebo-sodoken': ('Pleebo/Sodoken', 'lr:county:maryland'),
           'lr:district:garr-bain': ('Garr-Bain', 'lr:county:nimba'),
           'lr:district:gbehlay-geh': ('Gbehlay-Geh', 'lr:county:nimba'),
           'lr:district:sanniquellie-mahn': ('Sanniquellie Mahn', 'lr:county:nimba'),
           'lr:district:wee-gbehyi-mahn': ('Wee-Gbehyi-Mahn', 'lr:county:nimba'),
           'lr:district:beawor': ('Beawor', 'lr:county:river-cess'),
           'lr:district:central-rivercess': ('Central Rivercess', 'lr:county:river-cess'),
           'lr:district:zarflahn': ('Zarflahn', 'lr:county:river-cess'),
           'lr:district:jeadepo': ('Jeadepo', 'lr:county:sinoe'),
           'lr:district:kulu': ('Kulu', 'lr:county:sinoe'),
           'lr:district:plahn': ('Plahn', 'lr:county:sinoe'),
           'lr:district:sanquin-number-1': ('Sanquin Number 1', 'lr:county:sinoe'),
           'lr:district:sanquin-number-2': ('Sanquin Number 2', 'lr:county:sinoe'),
           'lr:district:sanquin-number-3': ('Sanquin Number 3', 'lr:county:sinoe')}
for sid, (name, par) in renames.items():
    r = byid.get(sid)
    check(f'renamed-{sid.split(":")[-1]}', bool(r) and r['name'] == name
          and r['parent_source_id'] == par and r['level'] == '2', str(r))
# old slugs must be gone
for sid in ['lr:district:panta-kpa', 'lr:district:sanayea', 'lr:district:nekreen',
            'lr:district:st-john-river', 'lr:district:gola-konneh',
            'lr:district:quardu-gboni', 'lr:district:firestone',
            'lr:district:pleebo-sodeken', 'lr:district:gbehlageh',
            'lr:district:wee-gbehy-mahn', 'lr:district:bearwor',
            'lr:district:zartlahn', 'lr:district:jaedepo',
            'lr:district:kulu-shaw-boe', 'lr:district:plahn-nyarn',
            'lr:district:sanquin-district-1', 'lr:district:sanquin-district-2',
            'lr:district:sanquin-district-3']:
    check(f'oldslug-gone-{sid.split(":")[-1]}', sid not in byid, sid)
# --- 5 drops absent ---
for sid in ['lr:district:gbarzon', 'lr:district:barrobo', 'lr:district:webbo',
            'lr:district:mambah-kaba', 'lr:district:montserrado:commonwealth']:
    check(f'dropped-{sid.split(":")[-1]}', sid not in byid, sid)
# --- 35 adds present ---
adds = {'lr:district:gounwolaila': ('Gounwolaila', 'lr:county:gbarpolu'),
        'lr:district:owensgrove': ('Owensgrove', 'lr:county:grand-bassa'),
        'lr:district:bhai': ("B'hai", 'lr:county:grand-gedeh'),
        'lr:district:cavala': ('Cavala', 'lr:county:grand-gedeh'),
        'lr:district:gbao': ('Gbao', 'lr:county:grand-gedeh'),
        'lr:district:gboe-ploe': ('Gboe-Ploe', 'lr:county:grand-gedeh'),
        'lr:district:glio-twarbo': ('Glio-Twarbo', 'lr:county:grand-gedeh'),
        'lr:district:putu': ('Putu', 'lr:county:grand-gedeh'),
        'lr:district:lukameh': ('Lukameh', 'lr:county:lofa'),
        'lr:district:wahasa': ('Wahasa', 'lr:county:lofa'),
        'lr:district:waum': ('Waum', 'lr:county:lofa'),
        'lr:district:tengia': ('Tengia', 'lr:county:lofa'),
        'lr:district:mambahn-kabah': ('Mambahn Kabah', 'lr:county:margibi'),
        'lr:district:kabah-administrative': ('Kabah Administrative', 'lr:county:margibi'),
        'lr:district:whojah': ('Whojah', 'lr:county:maryland'),
        'lr:district:gwelekpoken': ('Gwelekpoken', 'lr:county:maryland'),
        'lr:district:nyorken': ('Nyorken', 'lr:county:maryland'),
        'lr:district:karluway-number-1': ('Karluway Number 1', 'lr:county:maryland'),
        'lr:district:karluway-number-2': ('Karluway Number 2', 'lr:county:maryland'),
        'lr:district:harper': ('Harper', 'lr:county:maryland'),
        'lr:district:west-point-township': ('West Point Township', 'lr:county:montserrado'),
        'lr:district:borough-of-new-kru-town': ('Borough of New Kru Town', 'lr:county:montserrado'),
        'lr:district:gardnersville-township': ('Gardnersville Township', 'lr:county:montserrado'),
        'lr:district:barnersville-township': ('Barnersville Township', 'lr:county:montserrado'),
        'lr:district:louisiana-township': ('Louisiana Township', 'lr:county:montserrado'),
        'lr:district:paynesville-township': ('Paynesville Township', 'lr:county:montserrado'),
        'lr:district:congo-town-township': ('Congo Town Township', 'lr:county:montserrado'),
        'lr:district:new-georgia-township': ('New Georgia Township', 'lr:county:montserrado'),
        'lr:district:caldwell-township': ('Caldwell Township', 'lr:county:montserrado'),
        'lr:district:garglohn-township': ('Garglohn Township', 'lr:county:montserrado'),
        'lr:district:johnsonville-township': ('Johnsonville Township', 'lr:county:montserrado'),
        'lr:district:jlah': ('Jlah', 'lr:county:sinoe'),
        'lr:district:krah': ('Krah', 'lr:county:sinoe'),
        'lr:district:sarboh': ('Sarboh', 'lr:county:sinoe'),
        'lr:district:bar-nakay': ('Bar-Nakay', 'lr:county:sinoe')}
for sid, (name, par) in adds.items():
    r = byid.get(sid)
    check(f'added-{sid.split(":")[-1]}', bool(r) and r['name'] == name
          and r['parent_source_id'] == par and r['level'] == '2'
          and r['type'] == 'district', str(r))
# --- contested keeps ---
keeps = {'lr:district:penicess': ('Penicess', 'lr:county:grand-kru'),
         'lr:district:karforh': ('Karforh', 'lr:county:river-gee'),
         'lr:district:porkpa': ('Porkpa', 'lr:county:grand-cape-mount'),
         'lr:district:commonwealth': ('Commonwealth', 'lr:county:grand-bassa'),
         'lr:district:grand-cape-mount:commonwealth': ('Commonwealth', 'lr:county:grand-cape-mount'),
         'lr:district:dugbe-river': ('Dugbe River', 'lr:county:sinoe'),
         'lr:district:jaedae': ('Jaedae', 'lr:county:sinoe'),
         'lr:district:meinpea-mahn': ('Meinpea-Mahn', 'lr:county:nimba'),
         'lr:district:twan-river': ('Twan River', 'lr:county:nimba'),
         'lr:district:zoe-gbao': ('Zoe-Gbao', 'lr:county:nimba'),
         'lr:district:tehr': ('Tehr', 'lr:county:bomi'),
         'lr:district:careysburg': ('Careysburg', 'lr:county:montserrado'),
         'lr:district:greater-monrovia': ('Greater Monrovia', 'lr:county:montserrado')}
for sid, (name, par) in keeps.items():
    r = byid.get(sid)
    check(f'kept-{sid.split(":")[-1]}', bool(r) and r['name'] == name
          and r['parent_source_id'] == par, str(r))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 31 codes / 31 links, all L1 county, zero multis ---
check('codes-31', len(codes) == 31, str(len(codes)))
check('links-31', len(links) == 31, str(len(links)))
check('codes-LR', all(c['country_code'] == 'LR' for c in codes))
clist = [c['code'] for c in codes]
check('codes-4digit', all(re.match(r'^\d{4}$', c) for c in clist))
check('codes-range', min(clist) == '1000' and max(clist) == '7520',
      f'{min(clist)}..{max(clist)}')
check('all-l1', all(byid[l['area_source_id']]['level'] == '1' for l in links))
check('all-primary', all(l['is_primary'] == 'true' for l in links))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
check('zero-multis', len({l['postcode'] for l in links}) == 31)
exp_leg = {'lr:county:bomi': 1, 'lr:county:bong': 2,
           'lr:county:grand-bassa': 1, 'lr:county:grand-cape-mount': 1,
           'lr:county:grand-gedeh': 3, 'lr:county:grand-kru': 4,
           'lr:county:lofa': 3, 'lr:county:margibi': 4,
           'lr:county:maryland': 2, 'lr:county:montserrado': 3,
           'lr:county:nimba': 3, 'lr:county:river-cess': 1,
           'lr:county:river-gee': 2, 'lr:county:sinoe': 1}
have = Counter(l['area_source_id'] for l in links)
for sid, n in exp_leg.items():
    check(f'legs-{sid.split(":")[-1]}', have.get(sid) == n,
          f'{have.get(sid)} != {n}')
check('gbarpolu-zero-held', have.get('lr:county:gbarpolu', 0) == 0,
      'Bopolu codeless per philib; no fill found')
anchors = {'1000': 'lr:county:montserrado', '1010': 'lr:county:montserrado',
           '1500': 'lr:county:margibi', '2000': 'lr:county:bomi',
           '3000': 'lr:county:bong', '3500': 'lr:county:nimba',
           '4000': 'lr:county:grand-bassa', '4500': 'lr:county:river-cess',
           '5000': 'lr:county:sinoe', '5500': 'lr:county:grand-kru',
           '6020': 'lr:county:river-gee', '6500': 'lr:county:maryland',
           '7000': 'lr:county:grand-gedeh', '7500': 'lr:county:lofa',
           '2500': 'lr:county:grand-cape-mount'}
pin = {l['postcode']: l['area_source_id'] for l in links}
for code, sid in anchors.items():
    check(f'anchor-{code}', pin.get(code) == sid, str(pin.get(code)))
# --- B14-PDF errata guards (final-report App.B, 2022 frame) ---
goun = [r for r in rows if r['level'] == '2' and r['name'] == 'Gounwolaila']
check('pdf-gounwolaila-once', len(goun) == 1
      and goun[0]['parent_source_id'] == 'lr:county:gbarpolu', str(goun))
check('pdf-no-gounwolaila-cm-mg',
      all(r['parent_source_id'] not in ('lr:county:grand-cape-mount',
                                        'lr:county:margibi') for r in goun),
      'B3/B5 printed rows are spurious dups')
barobo = [r['source_id'] for r in rows
          if r['name'].lower().replace(' ', '') in ('barobo', 'barrobo')]
check('pdf-no-barobo-name', not barobo, str(barobo[:3]))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
