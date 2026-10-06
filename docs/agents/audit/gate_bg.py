import csv, re, sys
from collections import Counter
# Bulgaria gate. Pins the B17 inline postal pass (293 areas /
# 4351 codes / 4363 legs / 12 multis): 22 ADDs (GN-live + wiki/
# office/OSM seconds incl. Topoli-9024), 34 REMOVEs (Teteven-13
# + Yablanitsa-6 dead blocks + 13 orphan singles + Gabrovo/Kardzhali/
# Byala branch-quarter codes 6609/7101), 5 Malko Tarnovo MOVEs
# (835x->816x post-CRC-2016 renumber, REVERSING the bundler's
# asserted direction; 8162 town + 8163 Brashlyan + 8165 Stoilovo
# + 8166 Gramatikovo + 8170 Zvezdets via bgpost offices + Google/
# hotel + OSM + 2017 govt tender; 8 villages HELD on 835x),
# 2789 +belitsa leg (Galabovo), 2791 -yakoruda leg (Avramovo=
# 2795, none=2791), 6190 Gurkovo-leg HELD (GN-only Zhergovec),
# 7 DONOTADDs (office-live beats wiki/GN-stale: 2096/2190/3264/
# 5173/7685/9822/9494; Mirkovo-209x + Nesebar-822x GN stale
# blocks proven), office-wins rule (unnumbered village offices
# = delivery; numbered town branches excluded, resorts kept:
# 9006/9007/8240/9620 delivery-used). 143/143 code-mismatches
# closed (81 STALE incl. GN city-vs-ring Dobrich + homonym
# errors, 61 CORROB, 1 HOLD); 26 GN-opens closed (17 DROP +
# 8 HOLD + 9024 ADD); 24 orphan singles + 4 wiki-only + 8
# opens-HOLD kept/documented (single-signal).
# EOL: areas LF-only; postal CRLF.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_bg.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/bulgaria-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/bulgaria-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/bulgaria-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
check('codes-CRLF', b'\r\n' in raw_c and b'\r' not in raw_c.replace(b'\r\n', b''))
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF', b'\r\n' in raw_l and b'\r' not in raw_l.replace(b'\r\n', b''))
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-293', len(rows) == 293, str(len(rows)))
check('district-28', sum(1 for r in rows if r['type'] == 'district') == 28)
check('municipality-265', sum(1 for r in rows if r['type'] == 'municipality') == 265)
spots = {'bg:municipality:malko-tarnovo': ('Malko Tarnovo', 'bg:district:burgas'),
         'bg:municipality:sofia': ('Sofia', 'bg:district:sofia-city'),
         'bg:municipality:dobrichka': ('Dobrichka', 'bg:district:dobrich'),
         'bg:municipality:dobrich': ('Dobrich', 'bg:district:dobrich'),
         'bg:municipality:byala': ('Byala', 'bg:district:ruse'),
         'bg:municipality:varna:byala': ('Byala', 'bg:district:varna'),
         'bg:municipality:shabla': ('Shabla', 'bg:district:dobrich'),
         'bg:municipality:teteven': ('Teteven', 'bg:district:lovech')}
for sid, (nm, par) in spots.items():
    r = byid.get(sid, {})
    check(f'spot-{sid}', r.get('name') == nm and r.get('parent_source_id') == par,
          str((r.get('name'), r.get('parent_source_id'))))
check('codes-4351', len(codes) == 4351, str(len(codes)))
check('links-4363', len(links) == 4363, str(len(links)))
check('codes-BG', all(c['country_code'] == 'BG' for c in codes))
clist = [c['code'] for c in codes]
check('codes-4digit', all(re.match(r'^\d{4}$', c) for c in clist))
check('codes-unique', len(set(clist)) == len(clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
multis = {pc: sorted(a for _, a in [(l['postcode'], l['area_source_id']) for l in links if l['postcode'] == pc]) for pc in have if have[pc] > 1}
check('multis-12', len(multis) == 12, str(sorted(multis)))
expect_multi = {'2408': ['bg:municipality:radomir', 'bg:municipality:zemen'],
                '2782': ['bg:municipality:belitsa', 'bg:municipality:razlog'],
                '2789': ['bg:municipality:belitsa', 'bg:municipality:yakoruda'],
                '4939': ['bg:municipality:banite', 'bg:municipality:madan'],
                '5084': ['bg:municipality:elena', 'bg:municipality:zlataritsa'],
                '5096': ['bg:municipality:elena', 'bg:municipality:zlataritsa'],
                '6190': ['bg:municipality:gurkovo', 'bg:municipality:nikolaevo'],
                '6268': ['bg:municipality:radnevo', 'bg:municipality:stara-zagora'],
                '6488': ['bg:municipality:harmanli', 'bg:municipality:madzharovo'],
                '6800': ['bg:municipality:kirkovo', 'bg:municipality:momchilgrad'],
                '7973': ['bg:municipality:antonovo', 'bg:municipality:omurtag'],
                '9144': ['bg:municipality:aksakovo', 'bg:municipality:suvorovo']}
bad = [pc for pc, legs in expect_multi.items() if multis.get(pc) != legs]
check('multi-pairs', not bad, str(bad))
sec = [l['postcode'] for l in links if l['is_primary'] == 'false']
check('secondary-12', len(sec) == 12 and set(sec) == set(multis), str(sorted(sec)))
check('primary-rest', all(l['is_primary'] == 'true' for l in links if l['postcode'] not in multis))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
check('legs-L2-only', all(byid[l['area_source_id']]['level'] == '2' for l in links))
cset = set(clist)
leg = {}
for l in links:
    leg.setdefault(l['postcode'], []).append(l['area_source_id'])
adds = {'2655': 'bg:municipality:nevestino', '2658': 'bg:municipality:nevestino',
        '4446': 'bg:municipality:septemvri', '4456': 'bg:municipality:septemvri',
        '4848': 'bg:municipality:smolyan', '4888': 'bg:municipality:laki',
        '5156': 'bg:municipality:zlataritsa', '5399': 'bg:municipality:dryanovo',
        '6233': 'bg:municipality:stara-zagora', '6631': 'bg:municipality:kardzhali',
        '6832': 'bg:municipality:momchilgrad', '6838': 'bg:municipality:momchilgrad',
        '6839': 'bg:municipality:dzhebel', '6863': 'bg:municipality:kirkovo',
        '6886': 'bg:municipality:kirkovo', '6897': 'bg:municipality:kirkovo',
        '7918': 'bg:municipality:omurtag', '8289': 'bg:municipality:primorsko',
        '8339': 'bg:municipality:sredets', '9495': 'bg:municipality:dobrichka',
        '9496': 'bg:municipality:dobrichka', '9024': 'bg:municipality:varna'}
check('adds-22', len(adds) == 22)
for pc, sid in adds.items():
    check(f'add-{pc}', pc in cset and leg.get(pc) == [sid], str(leg.get(pc)))
removes = ('5721 5722 5736 5737 5738 5739 5742 5743 5744 5745 5747 5748 5749 '
           '5735 5751 5752 5766 5767 5768 9633 4476 3521 2906 3163 8258 4577 '
           '8839 8840 8143 3036 9434 9435 6609 7101').split()
check('removes-34', len(removes) == 34)
for pc in removes:
    check(f'gone-{pc}', pc not in cset and pc not in leg, str(leg.get(pc)))
moves = {'8162': '8350', '8163': '8357', '8165': '8359', '8166': '8370', '8170': '8360'}
for new, old in moves.items():
    check(f'move-{old}-{new}', new in cset and old not in cset
          and leg.get(new) == ['bg:municipality:malko-tarnovo'] and old not in leg,
          str(leg.get(new)))
for pc in ('8365', '8361', '8363', '8367', '8368', '8364', '8358', '8369'):
    check(f'hold-mt-{pc}', leg.get(pc) == ['bg:municipality:malko-tarnovo'], str(leg.get(pc)))
check('leg-2791-belitsa-only', leg.get('2791') == ['bg:municipality:belitsa'], str(leg.get('2791')))
keeps = {'2076': 'bg:municipality:mirkovo', '3056': 'bg:municipality:vratsa',
         '2166': 'bg:municipality:pravets', '5136': 'bg:municipality:gorna-oryahovitsa',
         '7641': 'bg:municipality:glavinitsa', '9818': 'bg:municipality:shumen',
         '9433': 'bg:municipality:dobrichka', '9620': 'bg:municipality:balchik',
         '8240': 'bg:municipality:nesebar', '9006': 'bg:municipality:varna',
         '9007': 'bg:municipality:varna', '5301': 'bg:municipality:gabrovo',
         '5304': 'bg:municipality:gabrovo', '5307': 'bg:municipality:gabrovo',
         '5309': 'bg:municipality:gabrovo', '5351': 'bg:municipality:tryavna',
         '2231': 'bg:municipality:kostinbrod', '9674': 'bg:municipality:shabla'}
for pc, sid in keeps.items():
    check(f'keep-{pc}', leg.get(pc) == [sid], str(leg.get(pc)))
for pc in ('2096', '2190', '3264', '5173', '7685', '9822', '9494', '3685', '6843'):
    check(f'out-{pc}', pc not in cset, str(leg.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
