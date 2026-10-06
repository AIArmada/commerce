import csv, re, sys
# Slovakia gate. B11 revisit 2026-10-04: FIX-AND-FILL, 114 region->district moves.
# Bundled set == current GeoNames SK postal dump exactly: 5233 rows / 3480 distinct
# codes, zero diff both ways, range 010 01-992 14. Per-code admin2 sets match on all
# 1411 codes with GeoNames street rows (incl. all 33 multi-link sets); primaries equal
# the GeoNames row-majority on every multi (ties kept per stability rule). 1150 blank-only
# codes match town->district resolution; 585 Bratislava blanks follow the 2nd-digit rule
# (WP Postal codes in Slovakia + 54 GeoNames street rows: 811/821/83x/84x/85x -> I..V).
# FIX: 114 KI office codes sat at sk:region:kosice although their towns resolve
# unambiguously (same rule as the other 1740 non-KI blanks): TrebiSov town x60 +
# Kralovsky Chlmec x22 -> trebiSov; Spisska Nova Ves x13; Michalovce x10; Roznava x5;
# Moldava nad Bodvou x2 -> kosice-okolie; Sobrance x1; Kosice-zeleziarne 044 54 ->
# kosice-ii (WP Saca: steelworks in Saca, Kosice II). 215 true Kosice-city office
# codes stay at region (no digit rule: 040 01 spans I+IV, 040 11 II+IV).
# Run from repo root: python3 /tmp/geo-verify/B11/SK/gate_sk.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/slovakia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/slovakia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/slovakia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# --- EOL assertions (raw bytes; areas LF, codes+links CRLF) ---
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
check('codes-CRLF-only', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 3481)
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF-only', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 3515)
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
# --- tree: 8 kraje + 79 okresy ---
check('areas-87', len(rows) == 87, str(len(rows)))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
check('l1-8-regions', len(l1) == 8 and all(r['type'] == 'region' and not r['parent_source_id'] for r in l1))
check('l1-iso-codes', sorted(r['code'] for r in l1) == ['BC', 'BL', 'KI', 'NI', 'PV', 'TA', 'TC', 'ZI'])
check('l2-79', len(l2) == 79, str(len(l2)))
check('l2-all-district', all(r['type'] == 'district' for r in l2))
check('areas-unique-ids', len(byid) == 87)
check('areas-country-SK', all(r['country_code'] == 'SK' for r in rows))
check('l2-parents-valid', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in l2))
from collections import Counter
parent = {r['source_id']: r['parent_source_id'] for r in l2}
mem = Counter(parent[r['source_id']] for r in l2)
check('region-membership', dict(mem) == {'sk:region:banska-bystrica': 13, 'sk:region:bratislava': 8, 'sk:region:kosice': 11, 'sk:region:nitra': 7, 'sk:region:presov': 13, 'sk:region:trencin': 9, 'sk:region:trnava': 7, 'sk:region:zilina': 11}, str(dict(mem)))
# --- postal: 3480 codes / 3514 links ---
check('codes-3480', len(codes) == 3480, str(len(codes)))
check('links-3514', len(links) == 3514, str(len(links)))
clist = [c['code'] for c in codes]
check('codes-unique', len(set(clist)) == 3480)
check('codes-sorted', clist == sorted(clist))
bad = [c for c in clist if not re.match(r'^\d{3} \d{2}$', c)]
check('code-format-NNN-NN', not bad, str(bad[:3]))
check('codes-range', min(clist) == '010 01' and max(clist) == '992 14', f'{min(clist)}-{max(clist)}')
check('codes-country-SK', all(c['country_code'] == 'SK' for c in codes))
check('no-internal-86-89', not [c for c in clist if c[0] == '8' and c[1] in '6789'])
by = {}
prim = {}
seenc = []
for l in links:
    by.setdefault(l['postcode'], []).append(l)
    if l['postcode'] != (seenc[-1] if seenc else None):
        seenc.append(l['postcode'])
    if l['is_primary'] == 'true':
        prim[l['postcode']] = l['area_source_id']
check('links-grouped-codes-order', seenc == clist)
check('every-code-one-primary', len(prim) == 3480 and all(sum(1 for l in v if l['is_primary'] == 'true') == 1 for v in by.values()))
check('links-served-by', all(l['relationship_type'] == 'served_by' for l in links))
dang = [l['postcode'] for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
# --- 114 B11 moves: region -> district (FAIL pre-fix on exactly these) ---
MOVES = {
 '044 54': 'sk:district:kosice-ii',
 '045 23': 'sk:district:kosice-okolie',
 '045 52': 'sk:district:kosice-okolie',
 '048 03': 'sk:district:roznava',
 '048 15': 'sk:district:roznava',
 '048 20': 'sk:district:roznava',
 '048 40': 'sk:district:roznava',
 '048 80': 'sk:district:roznava',
 '052 06': 'sk:district:spisska-nova-ves',
 '052 07': 'sk:district:spisska-nova-ves',
 '052 19': 'sk:district:spisska-nova-ves',
 '052 20': 'sk:district:spisska-nova-ves',
 '052 21': 'sk:district:spisska-nova-ves',
 '052 24': 'sk:district:spisska-nova-ves',
 '052 26': 'sk:district:spisska-nova-ves',
 '052 51': 'sk:district:spisska-nova-ves',
 '052 63': 'sk:district:spisska-nova-ves',
 '052 70': 'sk:district:spisska-nova-ves',
 '052 73': 'sk:district:spisska-nova-ves',
 '052 75': 'sk:district:spisska-nova-ves',
 '052 80': 'sk:district:spisska-nova-ves',
 '071 03': 'sk:district:michalovce',
 '071 05': 'sk:district:michalovce',
 '071 20': 'sk:district:michalovce',
 '071 21': 'sk:district:michalovce',
 '071 55': 'sk:district:michalovce',
 '071 56': 'sk:district:michalovce',
 '071 60': 'sk:district:michalovce',
 '071 74': 'sk:district:michalovce',
 '071 80': 'sk:district:michalovce',
 '071 84': 'sk:district:michalovce',
 '073 42': 'sk:district:sobrance',
 '075 03': 'sk:district:trebisov',
 '075 12': 'sk:district:trebisov',
 '075 15': 'sk:district:trebisov',
 '075 16': 'sk:district:trebisov',
 '075 17': 'sk:district:trebisov',
 '075 18': 'sk:district:trebisov',
 '075 19': 'sk:district:trebisov',
 '075 20': 'sk:district:trebisov',
 '075 21': 'sk:district:trebisov',
 '075 22': 'sk:district:trebisov',
 '075 23': 'sk:district:trebisov',
 '075 25': 'sk:district:trebisov',
 '075 26': 'sk:district:trebisov',
 '075 27': 'sk:district:trebisov',
 '075 29': 'sk:district:trebisov',
 '075 31': 'sk:district:trebisov',
 '075 32': 'sk:district:trebisov',
 '075 34': 'sk:district:trebisov',
 '075 39': 'sk:district:trebisov',
 '075 40': 'sk:district:trebisov',
 '075 42': 'sk:district:trebisov',
 '075 43': 'sk:district:trebisov',
 '075 44': 'sk:district:trebisov',
 '075 45': 'sk:district:trebisov',
 '075 46': 'sk:district:trebisov',
 '075 48': 'sk:district:trebisov',
 '075 49': 'sk:district:trebisov',
 '075 52': 'sk:district:trebisov',
 '075 53': 'sk:district:trebisov',
 '075 54': 'sk:district:trebisov',
 '075 55': 'sk:district:trebisov',
 '075 57': 'sk:district:trebisov',
 '075 58': 'sk:district:trebisov',
 '075 60': 'sk:district:trebisov',
 '075 61': 'sk:district:trebisov',
 '075 62': 'sk:district:trebisov',
 '075 63': 'sk:district:trebisov',
 '075 64': 'sk:district:trebisov',
 '075 66': 'sk:district:trebisov',
 '075 67': 'sk:district:trebisov',
 '075 68': 'sk:district:trebisov',
 '075 71': 'sk:district:trebisov',
 '075 72': 'sk:district:trebisov',
 '075 73': 'sk:district:trebisov',
 '075 74': 'sk:district:trebisov',
 '075 75': 'sk:district:trebisov',
 '075 76': 'sk:district:trebisov',
 '075 77': 'sk:district:trebisov',
 '075 78': 'sk:district:trebisov',
 '075 79': 'sk:district:trebisov',
 '075 80': 'sk:district:trebisov',
 '075 81': 'sk:district:trebisov',
 '075 82': 'sk:district:trebisov',
 '075 85': 'sk:district:trebisov',
 '075 88': 'sk:district:trebisov',
 '075 89': 'sk:district:trebisov',
 '075 90': 'sk:district:trebisov',
 '075 93': 'sk:district:trebisov',
 '075 94': 'sk:district:trebisov',
 '075 95': 'sk:district:trebisov',
 '077 13': 'sk:district:trebisov',
 '077 15': 'sk:district:trebisov',
 '077 17': 'sk:district:trebisov',
 '077 21': 'sk:district:trebisov',
 '077 26': 'sk:district:trebisov',
 '077 29': 'sk:district:trebisov',
 '077 32': 'sk:district:trebisov',
 '077 33': 'sk:district:trebisov',
 '077 35': 'sk:district:trebisov',
 '077 37': 'sk:district:trebisov',
 '077 38': 'sk:district:trebisov',
 '077 39': 'sk:district:trebisov',
 '077 41': 'sk:district:trebisov',
 '077 44': 'sk:district:trebisov',
 '077 53': 'sk:district:trebisov',
 '077 54': 'sk:district:trebisov',
 '077 55': 'sk:district:trebisov',
 '077 61': 'sk:district:trebisov',
 '077 67': 'sk:district:trebisov',
 '077 68': 'sk:district:trebisov',
 '077 74': 'sk:district:trebisov',
 '077 81': 'sk:district:trebisov',
}
for p, want in sorted(MOVES.items()):
    check(f'move-{p}', prim.get(p) == want, f'has {prim.get(p)}')
# --- region level: exactly the 215 Kosice-city office codes ---
REGPOST = {
 '040 02', '040 03', '040 04', '040 05', '040 07', '040 08', '040 10', '040 19',
 '040 20', '040 31', '040 32', '040 34', '040 39', '040 41', '040 42', '040 45',
 '040 46', '040 50', '040 53', '040 55', '040 57', '040 58', '040 59', '040 60',
 '040 64', '040 65', '040 66', '040 73', '040 74', '040 75', '040 77', '040 79',
 '040 81', '040 82', '040 83', '040 85', '040 87', '040 89', '041 00', '041 02',
 '041 03', '041 05', '041 07', '041 08', '041 09', '041 10', '041 11', '041 12',
 '041 14', '041 15', '041 17', '041 18', '041 19', '041 20', '041 21', '041 22',
 '041 23', '041 24', '041 25', '041 26', '041 27', '041 28', '041 30', '041 34',
 '041 37', '041 39', '041 40', '041 41', '041 42', '041 45', '041 47', '041 48',
 '041 49', '041 50', '041 52', '041 53', '041 54', '041 55', '041 56', '041 58',
 '041 60', '041 61', '041 62', '041 63', '041 67', '041 68', '041 70', '041 71',
 '041 74', '041 75', '041 76', '041 78', '041 79', '041 80', '041 81', '041 82',
 '041 83', '041 85', '041 86', '041 89', '041 92', '041 93', '041 96', '041 97',
 '042 00', '042 02', '042 03', '042 04', '042 05', '042 07', '042 08', '042 09',
 '042 11', '042 12', '042 13', '042 14', '042 15', '042 18', '042 19', '042 20',
 '042 23', '042 24', '042 26', '042 27', '042 29', '042 33', '042 34', '042 35',
 '042 39', '042 40', '042 41', '042 42', '042 44', '042 46', '042 48', '042 49',
 '042 52', '042 53', '042 54', '042 55', '042 60', '042 61', '042 64', '042 65',
 '042 71', '042 72', '042 73', '042 74', '042 77', '042 81', '042 82', '042 84',
 '042 86', '042 87', '042 88', '042 89', '042 90', '042 92', '042 95', '042 96',
 '042 97', '042 99', '043 00', '043 01', '043 04', '043 05', '043 06', '043 07',
 '043 09', '043 10', '043 14', '043 15', '043 17', '043 19', '043 20', '043 22',
 '043 26', '043 27', '043 28', '043 29', '043 32', '043 37', '043 38', '043 39',
 '043 40', '043 41', '043 43', '043 47', '043 49', '043 51', '043 52', '043 56',
 '043 57', '043 58', '043 60', '043 65', '043 66', '043 69', '043 70', '043 72',
 '043 73', '043 74', '043 75', '043 76', '043 77', '043 78', '043 79', '043 86',
 '043 89', '043 90', '043 91', '043 93', '043 95', '043 96', '043 98',
}
havereg = sorted(p for p, s in prim.items() if ':region:' in s)
check('region-215-exact', havereg == sorted(REGPOST), str(sorted(set(havereg) ^ set(REGPOST))[:5]))
check('region-all-kosice', all(s == 'sk:region:kosice' for p, s in prim.items() if ':region:' in s))
# --- 33 multi-link sets exact (32 dual + 1 triple), primaries = GN majority ---
DUAL = {
 '027 32': ('sk:district:liptovsky-mikulas', ['sk:district:tvrdosin'],),
 '040 01': ('sk:district:kosice-i', ['sk:district:kosice-iv'],),
 '040 11': ('sk:district:kosice-ii', ['sk:district:kosice-iv'],),
 '040 15': ('sk:district:kosice-ii', ['sk:district:kosice-okolie'],),
 '040 16': ('sk:district:kosice-ii', ['sk:district:kosice-okolie'],),
 '040 18': ('sk:district:kosice-okolie', ['sk:district:kosice-iv'],),
 '067 01': ('sk:district:medzilaborce', ['sk:district:humenne'],),
 '067 13': ('sk:district:humenne', ['sk:district:medzilaborce'],),
 '067 34': ('sk:district:humenne', ['sk:district:snina'],),
 '067 82': ('sk:district:snina', ['sk:district:humenne'],),
 '072 33': ('sk:district:michalovce', ['sk:district:sobrance'],),
 '072 54': ('sk:district:sobrance', ['sk:district:michalovce'],),
 '082 66': ('sk:district:sabinov', ['sk:district:presov'],),
 '086 37': ('sk:district:bardejov', ['sk:district:svidnik'],),
 '087 01': ('sk:district:svidnik', ['sk:district:bardejov'],),
 '094 06': ('sk:district:vranov-nad-toplou', ['sk:district:humenne'],),
 '900 84': ('sk:district:senec', ['sk:district:pezinok'],),
 '906 35': ('sk:district:malacky', ['sk:district:senica'],),
 '916 13': ('sk:district:nove-mesto-nad-vahom', ['sk:district:myjava'],),
 '916 16': ('sk:district:nove-mesto-nad-vahom', ['sk:district:myjava'],),
 '922 31': ('sk:district:piestany', ['sk:district:hlohovec'],),
 '930 28': ('sk:district:dunajska-streda', ['sk:district:komarno'],),
 '956 32': ('sk:district:partizanske', ['sk:district:topolcany'],),
 '956 38': ('sk:district:banovce-nad-bebravou', ['sk:district:topolcany'],),
 '966 11': ('sk:district:ziar-nad-hronom', ['sk:district:zvolen'],),
 '976 81': ('sk:district:brezno', ['sk:district:banska-bystrica'],),
 '980 33': ('sk:district:rimavska-sobota', ['sk:district:lucenec'],),
 '982 52': ('sk:district:rimavska-sobota', ['sk:district:revuca'],),
 '982 62': ('sk:district:revuca', ['sk:district:rimavska-sobota'],),
 '982 65': ('sk:district:revuca', ['sk:district:rimavska-sobota'],),
 '985 22': ('sk:district:poltar', ['sk:district:lucenec'],),
 '985 42': ('sk:district:lucenec', ['sk:district:poltar', 'sk:district:rimavska-sobota'],),
 '985 45': ('sk:district:detva', ['sk:district:poltar'],),
}
havemulti = {p: v for p, v in by.items() if len(v) > 1}
check('multi-set-33-exact', set(havemulti) == set(DUAL), str(sorted(set(havemulti) ^ set(DUAL))[:5]))
wrongm = [p for p, (pp, ss) in DUAL.items() if prim.get(p) != pp or sorted(l['area_source_id'] for l in havemulti.get(p, []) if l['is_primary'] == 'false') != sorted(ss)]
check('multi-pairs-exact', not wrongm, str(wrongm[:5]))
# --- Bratislava 2nd-digit rule over all 913 BA codes ---
BA = {'1': 'sk:district:bratislava-i', '2': 'sk:district:bratislava-ii', '3': 'sk:district:bratislava-iii', '4': 'sk:district:bratislava-iv', '5': 'sk:district:bratislava-v'}
ba = {p: s for p, s in prim.items() if s in set(BA.values())}
check('ba-913', len(ba) == 913, str(len(ba)))
check('ba-2nd-digit-rule', all(s == BA[p[1]] for p, s in ba.items()))
check('ba-all-8xx', all(p.startswith('8') for p in ba))
check('8xx-all-ba', all(p in ba for p in prim if p.startswith('8')))
# --- pins ---
check('pin-010 01', prim.get('010 01') == 'sk:district:zilina')  # UPU Zilina
check('pin-960 01', prim.get('960 01') == 'sk:district:zvolen')  # UPU Zvolen
check('pin-058 06', prim.get('058 06') == 'sk:district:poprad')  # UPU Poprad
check('pin-917 01', prim.get('917 01') == 'sk:district:trnava')  # UPU Trnava
check('pin-851 01', prim.get('851 01') == 'sk:district:bratislava-v')  # Petrzalka orsr
check('pin-841 04', prim.get('841 04') == 'sk:district:bratislava-iv')  # Karlova Ves orsr
check('pin-015 14', prim.get('015 14') == 'sk:district:zilina')  # Rajec
check('pin-015 41', prim.get('015 41') == 'sk:district:zilina')  # Rajec
check('pin-044 54', prim.get('044 54') == 'sk:district:kosice-ii')  # zeleziarne
check('pin-077 01', prim.get('077 01') == 'sk:district:trebisov')  # Kralovsky Chlmec street
check('pin-040 01', prim.get('040 01') == 'sk:district:kosice-i')  # Kosice dual
check('pin-985 42', prim.get('985 42') == 'sk:district:lucenec')  # triple
# --- per-region primary counts (8) ---
havec = Counter(s if ':region:' in s else parent[s] for s in prim.values())
check('per-region-primaries', dict(havec) == {'sk:region:banska-bystrica': 349, 'sk:region:bratislava': 977, 'sk:region:kosice': 509, 'sk:region:nitra': 378, 'sk:region:presov': 354, 'sk:region:trencin': 242, 'sk:region:trnava': 300, 'sk:region:zilina': 371}, str([(k, havec.get(k, 0), v) for k, v in {'sk:region:banska-bystrica': 349, 'sk:region:bratislava': 977, 'sk:region:kosice': 509, 'sk:region:nitra': 378, 'sk:region:presov': 354, 'sk:region:trencin': 242, 'sk:region:trnava': 300, 'sk:region:zilina': 371}.items() if havec.get(k, 0) != v]))
# --- per-district primary counts, full 79-row table ---
havem = Counter(s for s in prim.values() if ':district:' in s)
expm = {'sk:district:banovce-nad-bebravou': 15, 'sk:district:banska-bystrica': 48, 'sk:district:banska-stiavnica': 10, 'sk:district:bardejov': 42, 'sk:district:bratislava-i': 459, 'sk:district:bratislava-ii': 144, 'sk:district:bratislava-iii': 163, 'sk:district:bratislava-iv': 100, 'sk:district:bratislava-v': 47, 'sk:district:brezno': 30, 'sk:district:bytca': 14, 'sk:district:cadca': 25, 'sk:district:detva': 10, 'sk:district:dolny-kubin': 18, 'sk:district:dunajska-streda': 49, 'sk:district:galanta': 43, 'sk:district:gelnica': 14, 'sk:district:hlohovec': 24, 'sk:district:humenne': 38, 'sk:district:ilava': 16, 'sk:district:kezmarok': 21, 'sk:district:komarno': 57, 'sk:district:kosice-i': 2, 'sk:district:kosice-ii': 6, 'sk:district:kosice-iii': 2, 'sk:district:kosice-iv': 1, 'sk:district:kosice-okolie': 39, 'sk:district:krupina': 18, 'sk:district:kysucke-nove-mesto': 11, 'sk:district:levice': 69, 'sk:district:levoca': 20, 'sk:district:liptovsky-mikulas': 43, 'sk:district:lucenec': 39, 'sk:district:malacky': 28, 'sk:district:martin': 38, 'sk:district:medzilaborce': 6, 'sk:district:michalovce': 38, 'sk:district:myjava': 12, 'sk:district:namestovo': 22, 'sk:district:nitra': 95, 'sk:district:nove-mesto-nad-vahom': 32, 'sk:district:nove-zamky': 64, 'sk:district:partizanske': 23, 'sk:district:pezinok': 18, 'sk:district:piestany': 38, 'sk:district:poltar': 14, 'sk:district:poprad': 39, 'sk:district:povazska-bystrica': 27, 'sk:district:presov': 58, 'sk:district:prievidza': 55, 'sk:district:puchov': 17, 'sk:district:revuca': 17, 'sk:district:rimavska-sobota': 40, 'sk:district:roznava': 30, 'sk:district:ruzomberok': 22, 'sk:district:sabinov': 16, 'sk:district:sala': 17, 'sk:district:senec': 18, 'sk:district:senica': 40, 'sk:district:skalica': 16, 'sk:district:snina': 23, 'sk:district:sobrance': 15, 'sk:district:spisska-nova-ves': 31, 'sk:district:stara-lubovna': 20, 'sk:district:stropkov': 11, 'sk:district:svidnik': 14, 'sk:district:topolcany': 41, 'sk:district:trebisov': 116, 'sk:district:trencin': 45, 'sk:district:trnava': 90, 'sk:district:turcianske-teplice': 14, 'sk:district:tvrdosin': 8, 'sk:district:velky-krtis': 32, 'sk:district:vranov-nad-toplou': 46, 'sk:district:zarnovica': 13, 'sk:district:ziar-nad-hronom': 16, 'sk:district:zilina': 156, 'sk:district:zlate-moravce': 35, 'sk:district:zvolen': 62}
wrongd = [(sid, havem.get(sid, 0), n) for sid, n in expm.items() if havem.get(sid, 0) != n]
check('per-district-primaries-79', not wrongd, str(wrongd[:4]))
check('covered-79-districts', sum(1 for sid in expm if havem.get(sid, 0) > 0) == 79)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
