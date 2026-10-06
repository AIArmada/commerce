import csv, re, sys
from collections import Counter
# Malawi gate. Pins the B7 fix-and-fill pass (491 gazetted 6-digit postcodes /
# 491 single-primary links) plus the ISO 3166-2:MW tree re-verification
# (3 regions + 28 districts). Oracles: Malawi Government Gazette 2019-05-10
# General Notice 37 "Malawi Postcodes 2019" pp.169-177 (491/491 rows),
# MACRA post-codes page via Wayback 2026-05-17 (199-code subset + 312225
# Chichiri footer pin), UPU MWI profile 01/2021 (6-digit format + 204101 /
# 312200), UPU Post_Code_Type Aug-2022 (Malawi 999999 N), WP List of postal
# codes (MW NNNNNN, ref macra.mw/post-codes), postalcodes.com.ng (Dedza 9/9,
# Blantyre Rural 11/11, Dowa 10/10), prostobank + ipostalcode + nowmsg spots,
# faceofmalawi MACRA-launch article, Smarty 999999 format guide, WP town
# articles (Luchenza->Thyolo, Mzuzu->Mzimba). Run from repo root:
# python3 docs/agents/audit/gate_mw.py
A = './packages/addressing/resources/geography/malawi-address-areas.csv'
C = './packages/addressing/resources/geography/malawi-postal-codes.csv'
L = './packages/addressing/resources/geography/malawi-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
try:
    rows = list(csv.DictReader(open(A)))
    codes = list(csv.DictReader(open(C)))
    links = list(csv.DictReader(open(L)))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
# --- tree: 3 regions + 28 districts (ISO 3166-2:MW) ---
check('areas-31', len(rows) == 31, str(len(rows)))
check('regions-3', sum(1 for r in rows if r['type'] == 'region') == 3)
check('districts-28', sum(1 for r in rows if r['type'] == 'district') == 28)
iso_reg = {'mw:region:central': ('Central', 'C'), 'mw:region:northern': ('Northern', 'N'),
           'mw:region:southern': ('Southern', 'S')}
for sid, (name, code) in iso_reg.items():
    r = byid.get(sid)
    check(f'iso-reg-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
iso_dis = {'mw:district:balaka': ('Balaka', 'BA', 'southern'), 'mw:district:blantyre': ('Blantyre', 'BL', 'southern'),
 'mw:district:chikwawa': ('Chikwawa', 'CK', 'southern'), 'mw:district:chiradzulu': ('Chiradzulu', 'CR', 'southern'),
 'mw:district:chitipa': ('Chitipa', 'CT', 'northern'), 'mw:district:dedza': ('Dedza', 'DE', 'central'),
 'mw:district:dowa': ('Dowa', 'DO', 'central'), 'mw:district:karonga': ('Karonga', 'KR', 'northern'),
 'mw:district:kasungu': ('Kasungu', 'KS', 'central'), 'mw:district:likoma': ('Likoma', 'LK', 'northern'),
 'mw:district:lilongwe': ('Lilongwe', 'LI', 'central'), 'mw:district:machinga': ('Machinga', 'MH', 'southern'),
 'mw:district:mangochi': ('Mangochi', 'MG', 'southern'), 'mw:district:mchinji': ('Mchinji', 'MC', 'central'),
 'mw:district:mulanje': ('Mulanje', 'MU', 'southern'), 'mw:district:mwanza': ('Mwanza', 'MW', 'southern'),
 'mw:district:mzimba': ('Mzimba', 'MZ', 'northern'), 'mw:district:neno': ('Neno', 'NE', 'southern'),
 'mw:district:nkhata-bay': ('Nkhata Bay', 'NB', 'northern'), 'mw:district:nkhotakota': ('Nkhotakota', 'NK', 'central'),
 'mw:district:nsanje': ('Nsanje', 'NS', 'southern'), 'mw:district:ntcheu': ('Ntcheu', 'NU', 'central'),
 'mw:district:ntchisi': ('Ntchisi', 'NI', 'central'), 'mw:district:phalombe': ('Phalombe', 'PH', 'southern'),
 'mw:district:rumphi': ('Rumphi', 'RU', 'northern'), 'mw:district:salima': ('Salima', 'SA', 'central'),
 'mw:district:thyolo': ('Thyolo', 'TH', 'southern'), 'mw:district:zomba': ('Zomba', 'ZO', 'southern')}
for sid, (name, code, reg) in iso_dis.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '2' and r['parent_source_id'] == f'mw:region:{reg}', str(r))
for reg, n in [('southern', 13), ('central', 9), ('northern', 6)]:
    have = [r for r in rows if r['parent_source_id'] == f'mw:region:{reg}']
    check(f'reg-{reg}-{n}', len(have) == n, str(len(have)))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# --- postal counts: 491 gazetted codes / 491 single-primary links ---
check('codes-491', len(codes) == 491, str(len(codes)))
check('links-491', len(links) == 491, str(len(links)))
check('country-MW', all(c['country_code'] == 'MW' for c in codes))
bad = [c['code'] for c in codes if not re.match(r'^\d{6}$', c['code'])]
check('code-format-6digit', not bad, str(bad[:3]))
check('codes-unique', len({c['code'] for c in codes}) == 491)
check('codes-sorted', [c['code'] for c in codes] == sorted(c['code'] for c in codes))
check('links-sorted', [l['postcode'] for l in links] == sorted(l['postcode'] for l in links))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
badrel = [l for l in links if l['relationship_type'] != 'served_by' or l['is_primary'] != 'true']
check('link-shape', not badrel, str(badrel[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 491 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('every-code-linked', {c['code'] for c in codes} == {l['postcode'] for l in links})
counts = Counter(l['postcode'] for l in links)
check('no-secondaries', all(c == 1 for c in counts.values()))
# --- per-district primary counts (28 districts, none codeless) ---
expect = {'balaka': 11, 'blantyre': 50, 'chikwawa': 16, 'chiradzulu': 10, 'chitipa': 10,
 'dedza': 9, 'dowa': 10, 'karonga': 7, 'kasungu': 39, 'likoma': 2, 'lilongwe': 76,
 'machinga': 19, 'mangochi': 28, 'mchinji': 14, 'mulanje': 10, 'mwanza': 4,
 'mzimba': 32, 'neno': 6, 'nkhata-bay': 14, 'nkhotakota': 10, 'nsanje': 12,
 'ntcheu': 12, 'ntchisi': 8, 'phalombe': 8, 'rumphi': 13, 'salima': 12,
 'thyolo': 17, 'zomba': 32}
have = Counter()
for l in links:
    if l['is_primary'] == 'true':
        have[l['area_source_id'].split(':')[-1]] += 1
for slug, n in expect.items():
    check(f'count-{slug}-{n}', have[slug] == n, str(have[slug]))
check('counts-sum-491', sum(have.values()) == 491, str(sum(have.values())))
# --- code -> district anchors: clusters + adjudicated anomalies ---
plink = {l['postcode']: l['area_source_id'] for l in links if l['is_primary'] == 'true'}
anchors = {
 # Lilongwe rural + urban Areas cluster
 '206101': 'mw:district:lilongwe', '206118': 'mw:district:lilongwe',
 '207201': 'mw:district:lilongwe', '207258': 'mw:district:lilongwe',
 # Blantyre rural + urban cluster
 '311101': 'mw:district:blantyre', '311111': 'mw:district:blantyre',
 '312200': 'mw:district:blantyre', '312238': 'mw:district:blantyre',
 # Mzimba + Mzuzu city cluster
 '104100': 'mw:district:mzimba', '104114': 'mw:district:mzimba',
 '105200': 'mw:district:mzimba', '105216': 'mw:district:mzimba',
 # Zomba rural + urban cluster
 '304101': 'mw:district:zomba', '304112': 'mw:district:zomba',
 '305200': 'mw:district:zomba', '305219': 'mw:district:zomba',
 # anomalies: Lumbadzi gazetted Dowa; Luchenza->Thyolo; Ngabu twins;
 # Lulanga gazette-typo row; Mavwere MACRA-web-typo row; Kasungu
 # municipality tail absent from MACRA web tables; Salima TA names
 '204108': 'mw:district:dowa', '309300': 'mw:district:thyolo',
 '316106': 'mw:district:nsanje', '315110': 'mw:district:chikwawa',
 '315111': 'mw:district:chikwawa', '301100': 'mw:district:mangochi',
 '205113': 'mw:district:mchinji', '201308': 'mw:district:kasungu',
 '201311': 'mw:district:kasungu', '208103': 'mw:district:salima',
 '208105': 'mw:district:salima',
 # one pin per remaining district (gazette 100-pointer BOMA row)
 '101100': 'mw:district:chitipa', '102100': 'mw:district:karonga',
 '103100': 'mw:district:rumphi', '106100': 'mw:district:nkhata-bay',
 '107100': 'mw:district:likoma', '201100': 'mw:district:kasungu',
 '201300': 'mw:district:kasungu', '202100': 'mw:district:nkhotakota',
 '203100': 'mw:district:ntchisi', '204100': 'mw:district:dowa',
 '205100': 'mw:district:mchinji', '208100': 'mw:district:salima',
 '209100': 'mw:district:dedza', '210100': 'mw:district:ntcheu',
 '301400': 'mw:district:mangochi', '302100': 'mw:district:balaka',
 '303100': 'mw:district:machinga', '306100': 'mw:district:chiradzulu',
 '307100': 'mw:district:phalombe', '308100': 'mw:district:mulanje',
 '310100': 'mw:district:thyolo', '313100': 'mw:district:neno',
 '314100': 'mw:district:mwanza', '315100': 'mw:district:chikwawa',
 '316100': 'mw:district:nsanje'}
for pc, want in anchors.items():
    check(f'anchor-{pc}', plink.get(pc) == want, str(plink.get(pc)))
# block-start holds: gazette pointers start mid-block (no phantom zeros)
for phantom in ['206100', '207200', '311100', '304100', '309100']:
    check(f'no-phantom-{phantom}', phantom not in plink)
# --- EOL + trailing newlines (all LF) ---
rawA, rawC, rawL = open(A, 'rb').read(), open(C, 'rb').read(), open(L, 'rb').read()
check('areas-lf-only', rawA.count(b'\r') == 0 and rawA.count(b'\n') == 32,
      f"cr={rawA.count(bytes([13]))} lf={rawA.count(bytes([10]))}")
check('codes-lf-only', rawC.count(b'\r') == 0 and rawC.count(b'\n') == 492,
      f"cr={rawC.count(bytes([13]))} lf={rawC.count(bytes([10]))}")
check('links-lf-only', rawL.count(b'\r') == 0 and rawL.count(b'\n') == 492,
      f"cr={rawL.count(bytes([13]))} lf={rawL.count(bytes([10]))}")
check('areas-trailing-nl', rawA.endswith(b'\n') and not rawA.endswith(b'\r\n'))
check('codes-trailing-nl', rawC.endswith(b'\n') and not rawC.endswith(b'\r\n'))
check('links-trailing-nl', rawL.endswith(b'\n') and not rawL.endswith(b'\r\n'))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
