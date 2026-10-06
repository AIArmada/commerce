import csv, re, sys
from collections import Counter
# Belarus gate. Pins the B13 fix-and-fill pass (29 retargets + 2 drops +
# 19 fills -> 3140 codes / 3140 links, 118/118 districts linked, zero duals)
# plus the verify-only tree (6 oblasts + Minsk city L1, 118 district L2).
# Oracles: fresh GeoNames BY dump (3133 rows / 3123 codes = bundled set),
# Mapanet.by raion pulls, Belposhta-family branch list (Belveb PVN PDF, 4081
# rows / 2449 codes), ru.wiki place infoboxes. Integrator notes: the worker's
# fixes.csv "Mapanet talachyn-town 211091" citation had no saved pull; the
# integrator fetched the Talachyn-town page directly (talachyn-town.html:
# Tolochin rows 211091 + 211092) so F5/F6 stand 2-signal; the same page shows
# Tolochin 211070, corroborating the 211070->talachyn KEEP (worker's "Mapanet
# gap" note was wrong, verdict strengthened, no data change).
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_by.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/belarus-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/belarus-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/belarus-postal-code-areas.csv'
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
# --- tree: 6 oblasts + Minsk city L1, 118 districts L2 ---
check('areas-125', len(rows) == 125, str(len(rows)))
check('oblasts-6', sum(1 for r in rows if r['type'] == 'oblast') == 6)
check('cities-1', sum(1 for r in rows if r['type'] == 'city') == 1)
check('districts-118', sum(1 for r in rows if r['type'] == 'district') == 118)
check('l1-7', sum(1 for r in rows if r['level'] == '1') == 7)
check('l2-118', sum(1 for r in rows if r['level'] == '2') == 118)
l1name = {r['source_id']: r['name'] for r in rows if r['level'] == '1'}
obl = {'by:oblast:brest': 16, 'by:oblast:gomel': 21, 'by:oblast:grodno': 17,
       'by:oblast:minsk': 22, 'by:oblast:mogilev': 21, 'by:oblast:vitebsk': 21}
got_obl = Counter()
for r in rows:
    if r['level'] == '2':
        got_obl[r['parent_source_id']] += 1
for sid, n in obl.items():
    check(f'l2-{sid.split(":")[-1]}', got_obl.get(sid) == n,
          f"{got_obl.get(sid)} != {n}")
check('minsk-city-l1', byid.get('by:city:minsk', {}).get('level') == '1'
      and byid.get('by:city:minsk', {}).get('parent_source_id') == '')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal: 3140 codes / 3140 links, all single-primary ---
check('codes-3140', len(codes) == 3140, str(len(codes)))
check('links-3140', len(links) == 3140, str(len(links)))
check('codes-BY', all(c['country_code'] == 'BY' for c in codes))
clist = [c['code'] for c in codes]
check('codes-unique', len(set(clist)) == 3140)
check('codes-sorted', clist == sorted(clist))
check('codes-2xxxx', all(re.match(r'^2\d{5}$', c) for c in clist))
check('links-sorted', [l['postcode'] for l in links] == sorted(l['postcode'] for l in links))
check('no-dangling', all(l['area_source_id'] in byid for l in links))
check('all-primary', all(l['is_primary'] == 'true' for l in links))
check('all-served_by', all(l['relationship_type'] == 'served_by' for l in links))
check('zero-duals', len({l['postcode'] for l in links}) == 3140)
check('every-code-linked', {c['code'] for c in codes} == {l['postcode'] for l in links})
pin = {l['postcode']: l['area_source_id'] for l in links}
# --- R1: 25x brest -> byaroza ---
r1 = ['225205', '225209', '225210', '225211', '225212', '225213', '225214',
      '225215', '225216', '225218', '225221', '225222', '225223', '225224',
      '225225', '225226', '225227', '225228', '225230', '225240', '225242',
      '225243', '225245', '225246', '225247']
check('r1-25-byaroza', all(pin.get(c) == 'by:district:byaroza' for c in r1),
      str([c for c in r1 if pin.get(c) != 'by:district:byaroza']))
# --- R2-R5 single retargets ---
check('r2-211440-polotsk', pin.get('211440') == 'by:district:polotsk', str(pin.get('211440')))
check('r3-211620-vydz', pin.get('211620') == 'by:district:vyerkhnyadzvinsk', str(pin.get('211620')))
check('r4-231470-dzyatlava', pin.get('231470') == 'by:district:dzyatlava', str(pin.get('231470')))
check('r5-247711-kalinkavichy', pin.get('247711') == 'by:district:kalinkavichy', str(pin.get('247711')))
# --- drops absent ---
check('drop-213918', '213918' not in pin and '213918' not in set(clist))
check('drop-247047', '247047' not in pin and '247047' not in set(clist))
# --- fills present ---
fills = {'222024': 'by:district:krupki', '247407': 'by:district:svyetlahorsk',
         '211631': 'by:district:vyerkhnyadzvinsk', '213910': 'by:district:klichaw',
         '211091': 'by:district:talachyn', '211092': 'by:district:talachyn',
         '211441': 'by:district:polotsk', '211443': 'by:district:polotsk',
         '211444': 'by:district:polotsk', '211445': 'by:district:polotsk',
         '211446': 'by:district:polotsk', '211447': 'by:district:polotsk',
         '211448': 'by:district:polotsk', '211449': 'by:district:polotsk',
         '211500': 'by:district:polotsk', '211501': 'by:district:polotsk',
         '231894': 'by:district:vawkavysk', '231896': 'by:district:vawkavysk',
         '231891': 'by:district:vawkavysk'}
for code, sid in fills.items():
    check(f'fill-{code}', pin.get(code) == sid, str(pin.get(code)))
# --- tie winners + container keeps + adjudicated keeps ---
keeps = {'211227': 'by:district:lyozna', '211657': 'by:district:polotsk',
         '220024': 'by:city:minsk', '222374': 'by:district:myadzyel',
         '222834': 'by:district:pukhavichy', '211502': 'by:district:polotsk',
         '222160': 'by:district:smalyavichy', '231778': 'by:district:byerastavitsa',
         '247101': 'by:district:loyew', '211070': 'by:district:talachyn',
         '211436': 'by:district:polotsk', '211451': 'by:district:vyerkhnyadzvinsk',
         '224000': 'by:district:brest', '213854': 'by:district:babruysk',
         '222700': 'by:district:stowbtsy', '231290': 'by:district:lida'}
for code, sid in keeps.items():
    check(f'keep-{code}', pin.get(code) == sid, str(pin.get(code)))
# --- holds stay absent ---
for code in ['225203', '225204', '225208', '231918', '222161', '213920',
             '247693', '211606', '222734', '223834']:
    check(f'hold-absent-{code}', code not in pin and code not in set(clist))
# --- per-oblast post-fix counts ---
exp_obl = {'by:oblast:brest': 525, 'by:oblast:gomel': 538,
           'by:oblast:grodno': 378, 'by:oblast:minsk': 615,
           'by:oblast:mogilev': 406, 'by:oblast:vitebsk': 588,
           'by:city:minsk': 90}
have_obl = Counter()
for l in links:
    a = byid[l['area_source_id']]
    key = a['source_id'] if a['level'] == '1' else a['parent_source_id']
    have_obl[key] += 1
for sid, n in exp_obl.items():
    check(f'oblast-{sid.split(":")[-1]}', have_obl.get(sid) == n,
          f"{have_obl.get(sid)} != {n}")
check('districts-linked-118', sum(1 for r in rows if r['type'] == 'district'
      and r['source_id'] in set(pin.values())) == 118)
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
