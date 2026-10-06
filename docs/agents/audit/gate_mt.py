import csv, re, sys
from collections import Counter
# Malta gate. B9 revisit 2026-10-03: fix-and-fill, 8-cell count-neutral swap.
# MaltaPost finder re-swept exhaustively (GetAllTowns 89 -> GetAllStreets 8656
# rows / 8139 unique ids, 8046 with addresses, 93 verified-404-empty) yielding a
# live universe of 27823 codes; Search endpoint cross-checks every drift cell.
# Retired (sweep-absent + Search-404): GZR 1564, MXK 4084, RBT 4104, RBT 4105.
# New (sweep-present + Search exact-hit): MXK 4081, RBT 4120, RBT 4121, XBX 1096.
# Zero council moves: all 27819 shared codes agree; CBD 5060 is dual-locality
# live (Santa Venera + Qormi) so the Santa Venera primary stands per stability.
# Run from repo root: python3 /tmp/geo-verify/B9/MT/gate_mt.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/malta-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/malta-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/malta-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# --- EOL + trailing newline assertions (raw bytes) ---
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
check('codes-CRLF-only', raw_c.count(b'\n') == raw_c.count(b'\r\n') and b'\r' in raw_c)
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF-only', raw_l.count(b'\n') == raw_l.count(b'\r\n') and b'\r' in raw_l)
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
# --- tree: 68 local councils, ISO 3166-2:MT codes 01-68 ---
check('areas-68', len(rows) == 68, str(len(rows)))
check('areas-all-l1-council', all(r['type'] == 'local_council' and r['level'] == '1' and not r['parent_source_id'] for r in rows))
check('areas-unique-ids', len(byid) == 68)
check('areas-codes-01-68', sorted(r['code'] for r in rows) == [f'{i:02d}' for i in range(1, 69)])
check('areas-country-MT', all(r['country_code'] == 'MT' for r in rows))
# English-vs-Maltese exonym variants pinned (same ISO entities).
variants = {'06': 'Cospicua', '20': 'Senglea', '45': 'Victoria', '46': 'Rabat',
            '48': "St. Julian's", '51': "St. Paul's Bay", '65': 'Żebbuġ Gozo'}
bycode = {r['code']: r for r in rows}
check('name-variants-7', all(bycode[c]['name'] == n for c, n in variants.items()))
# --- postal: 27823 codes / 27823 links, sorted, 1:1, all primary ---
check('codes-27823', len(codes) == 27823, str(len(codes)))
check('links-27823', len(links) == 27823, str(len(links)))
clist = [c['code'] for c in codes]
check('codes-unique', len(set(clist)) == 27823)
check('codes-sorted', clist == sorted(clist))
bad = [c for c in clist if not re.match(r'^[A-Z]{3} \d{4}$', c)]
check('code-format-AAA-NNNN', not bad, str(bad[:3]))
check('codes-country-MT', all(c['country_code'] == 'MT' for c in codes))
llist = [l['postcode'] for l in links]
check('links-order-matches-codes', llist == clist)
check('links-all-primary', all(l['is_primary'] == 'true' and l['relationship_type'] == 'served_by' for l in links))
dang = [l['postcode'] for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
check('prefixes-76', len({c.split()[0] for c in clist}) == 76)
# --- fixed cells: 4 retired absent, 4 new present+linked ---
cset = set(clist)
pin = {l['postcode']: l['area_source_id'] for l in links}
retired = ['GZR 1564', 'MXK 4084', 'RBT 4104', 'RBT 4105']
for pc in retired:
    check(f'retired-absent-{pc.replace(" ", "")}', pc not in cset and pc not in pin)
added = {'MXK 4081': 'mt:local_council:marsaxlokk', 'RBT 4120': 'mt:local_council:rabat',
         'RBT 4121': 'mt:local_council:rabat', 'XBX 1096': 'mt:local_council:ta-xbiex'}
for pc, sid in added.items():
    check(f'added-{pc.replace(" ", "")}', pc in cset and pin.get(pc) == sid, pin.get(pc, 'MISSING'))
# --- cluster pins: Valletta / Sliema / Victoria + Rabat disambiguation ---
check('pin-VLT1000-valletta', pin.get('VLT 1000') == 'mt:local_council:valletta')
check('pin-VLT1930-valletta', pin.get('VLT 1930') == 'mt:local_council:valletta')
check('pin-SLM1000-sliema', pin.get('SLM 1000') == 'mt:local_council:sliema')
check('pin-VCT1000-victoria', pin.get('VCT 1000') == 'mt:local_council:victoria')
check('pin-RBT1000-rabat', pin.get('RBT 1000') == 'mt:local_council:rabat')
check('pin-HMR1420-amrun', pin.get('HMR 1420') == 'mt:local_council:amrun')
# --- sub-locality -> parent council pins (MaltaPost town rows) ---
sub = {
 'SGN 4011': 'mt:local_council:san-gwann',      # Kappara
 'PTA 1010': 'mt:local_council:pieta',          # Gwardamanga
 'RBT 4100': 'mt:local_council:rabat',          # Bahrija
 'RBT 2600': 'mt:local_council:rabat',          # Tal-Virtu
 'XLN 1010': 'mt:local_council:munxar',         # Xlendi
 'MFN 1010': 'mt:local_council:zebbug-gozo',    # Marsalforn
 'KMN 1010': 'mt:local_council:gajnsielem',     # Comino/Kemmuna
 'BKR 4010': 'mt:local_council:birkirkara',     # Swatar (Birkirkara side)
 'MSD 1820': 'mt:local_council:msida',          # Swatar (Msida side)
 'BKR 1871': 'mt:local_council:birkirkara',     # Fleur-de-Lys
 'SWQ 1012': 'mt:local_council:swieqi',         # Madliena
 'STJ 1000': 'mt:local_council:st-julians',     # Paceville
 'SPB 6010': 'mt:local_council:st-pauls-bay',   # Burmarrad
 'ZRQ 2290': 'mt:local_council:zurrieq',        # Bubagra
 'ZBR 1980': 'mt:local_council:zabbar',         # St Peters
 'LQA 3010': 'mt:local_council:luqa',           # Hal Farrug
 'NXR 5011': 'mt:local_council:naxxar',         # Bahar ic-Caghak
 'KCM 1100': 'mt:local_council:kercem',         # Santa Lucija (Gozo hamlet)
}
for pc, sid in sub.items():
    check(f'sub-{pc.replace(" ", "")}', pin.get(pc) == sid, pin.get(pc, 'MISSING'))
# --- special / commercial-prefix pins ---
check('pin-MEC0001-pieta', pin.get('MEC 0001') == 'mt:local_council:pieta')
check('pin-SPK1000-stjulians', pin.get('SPK 1000') == 'mt:local_council:st-julians')
check('pin-SCM1001-kalkara', pin.get('SCM 1001') == 'mt:local_council:kalkara')
check('pin-MTP1000-marsa', pin.get('MTP 1000') == 'mt:local_council:marsa')
check('pin-MTP1001-marsa', pin.get('MTP 1001') == 'mt:local_council:marsa')
check('pin-CBD1010-birkirkara', pin.get('CBD 1010') == 'mt:local_council:birkirkara')
check('pin-CBD4060-svenera', pin.get('CBD 4060') == 'mt:local_council:santa-venera')
check('pin-CBD5020-qormi', pin.get('CBD 5020') == 'mt:local_council:qormi')
check('pin-CBD5060-svenera-kept', pin.get('CBD 5060') == 'mt:local_council:santa-venera')
# --- per-council code counts, full 68-row table (post-fix) ---
exp_counts = {
 'mt:local_council:amrun': 460,
 'mt:local_council:attard': 629,
 'mt:local_council:balzan': 219,
 'mt:local_council:birgu': 218,
 'mt:local_council:birkirkara': 1187,
 'mt:local_council:birzebbuga': 602,
 'mt:local_council:cospicua': 375,
 'mt:local_council:dingli': 265,
 'mt:local_council:fgura': 451,
 'mt:local_council:floriana': 165,
 'mt:local_council:fontana': 57,
 'mt:local_council:gajnsielem': 247,
 'mt:local_council:garb': 137,
 'mt:local_council:gargur': 195,
 'mt:local_council:gasri': 61,
 'mt:local_council:gaxaq': 379,
 'mt:local_council:gudja': 200,
 'mt:local_council:gzira': 252,
 'mt:local_council:iklin': 170,
 'mt:local_council:kalkara': 174,
 'mt:local_council:kercem': 166,
 'mt:local_council:kirkop': 186,
 'mt:local_council:lija': 234,
 'mt:local_council:luqa': 359,
 'mt:local_council:marsa': 341,
 'mt:local_council:marsaskala': 640,
 'mt:local_council:marsaxlokk': 287,
 'mt:local_council:mdina': 59,
 'mt:local_council:melliea': 790,
 'mt:local_council:mgarr': 291,
 'mt:local_council:mosta': 1170,
 'mt:local_council:mqabba': 258,
 'mt:local_council:msida': 391,
 'mt:local_council:mtarfa': 108,
 'mt:local_council:munxar': 128,
 'mt:local_council:nadur': 390,
 'mt:local_council:naxxar': 1041,
 'mt:local_council:paola': 465,
 'mt:local_council:pembroke': 163,
 'mt:local_council:pieta': 164,
 'mt:local_council:qala': 215,
 'mt:local_council:qormi': 979,
 'mt:local_council:qrendi': 305,
 'mt:local_council:rabat': 932,
 'mt:local_council:safi': 182,
 'mt:local_council:san-gwann': 718,
 'mt:local_council:san-lawrenz': 53,
 'mt:local_council:sannat': 177,
 'mt:local_council:santa-lucija': 143,
 'mt:local_council:santa-venera': 332,
 'mt:local_council:senglea': 212,
 'mt:local_council:siggiewi': 612,
 'mt:local_council:sliema': 678,
 'mt:local_council:st-julians': 446,
 'mt:local_council:st-pauls-bay': 980,
 'mt:local_council:swieqi': 615,
 'mt:local_council:ta-xbiex': 140,
 'mt:local_council:tarxien': 521,
 'mt:local_council:valletta': 312,
 'mt:local_council:victoria': 645,
 'mt:local_council:xagra': 346,
 'mt:local_council:xewkija': 391,
 'mt:local_council:xgajra': 105,
 'mt:local_council:zabbar': 914,
 'mt:local_council:zebbug-gozo': 245,
 'mt:local_council:zebbug-malta': 802,
 'mt:local_council:zejtun': 953,
 'mt:local_council:zurrieq': 796,
}
have = Counter(l['area_source_id'] for l in links)
wrong = [(sid, have.get(sid, 0), n) for sid, n in exp_counts.items() if have.get(sid, 0) != n]
check('per-council-counts-68', not wrong, str(wrong[:4]))
check('covered-68', sum(1 for sid in exp_counts if have.get(sid, 0) > 0) == 68)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
