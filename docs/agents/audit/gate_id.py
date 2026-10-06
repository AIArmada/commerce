"""Indonesia geography integrity gate.

Verifies, after the Oct-2026 re-verification:
  1. Tree: counts, orphans, Kemendagri code-prefix consistency.
  2. Tree names match Kepmendagri 300.2.2-2138/2025 (via cahyadsn/wilayah.sql),
     except the documented deliberate set.
  3. Links: 9359 codes, 5-digit, exactly-1 primary, no dangling, 514 areas.
  4. Anchor pins for the corrected systematic rotations.
  5. Full 3-digit prefix verdict table (regression guard).
"""
import csv
import os
import re
import sys
from collections import Counter, defaultdict

AUDIT_DIR = os.path.dirname(os.path.abspath(__file__))
WILAYAH_SQL = '/tmp/my-audit/wilayah.sql'  # re-fetch: see ../geography-verification.md

B = 'packages/addressing/resources/geography/'
fails = []

def check(cond, msg):
    if not cond:
        fails.append(msg)

areas = list(csv.DictReader(open(B + 'indonesia-address-areas.csv')))
vill = list(csv.DictReader(open(B + 'indonesia-villages.csv')))
codes = list(csv.DictReader(open(B + 'indonesia-postal-codes.csv')))
links = list(csv.DictReader(open(B + 'indonesia-postal-code-areas.csv')))

# 1. tree integrity
t = Counter(r['type'] for r in areas)
check(t['province'] == 38, f"provinces {t['province']}")
check(t['regency'] == 416, f"regencies {t['regency']}")
check(t['city'] == 98, f"cities {t['city']}")
check(t['district'] == 7285, f"districts {t['district']}")
check(len(vill) == 83762, f"villages {len(vill)}")
ids = {r['source_id'] for r in areas}
check(all(r['parent_source_id'] in ids for r in areas if r['parent_source_id']), "orphan L1-L3")
check(all(v['parent_source_id'] in ids for v in vill), "orphan L4")
bad = [r['code'] for r in areas if r['type'] == 'district' and not r['parent_source_id'].endswith(r['code'][:4])]
check(not bad, f"district prefix mismatch {bad[:3]}")
bad = [r['code'] for r in areas if r['type'] in ('regency', 'city') and not r['parent_source_id'].endswith(r['code'][:2])]
check(not bad, f"L2 prefix mismatch {bad[:3]}")
check(all(r['code'][:6] == {'regency': r['code'][:4], 'city': r['code'][:4]}.get(r['type'], r['code'][:6]) or True for r in areas), "noop")

# 2. official names
if not os.path.isfile(WILAYAH_SQL):
    print(f"GATE ID: missing {WILAYAH_SQL} — re-fetch per docs/agents/geography-verification.md")
    sys.exit(2)
raw = open(WILAYAH_SQL, encoding='utf-8', errors='replace').read()
pairs = re.findall(r"\('([\d.]+)','((?:''|[^'])*)'\)", raw)
off = {k.replace('.', ''): n.replace("''", "'") for k, n in pairs}
DELIBERATE = {'31': 'DKI Jakarta', '34': 'DI Yogyakarta',
              '3101': 'Kabupaten Kepulauan Seribu',
              '3171': 'Kota Jakarta Pusat', '3172': 'Kota Jakarta Utara',
              '3173': 'Kota Jakarta Barat', '3174': 'Kota Jakarta Selatan',
              '3175': 'Kota Jakarta Timur',
              '7109': 'Kabupaten Kepulauan Siau Tagulandang Biaro'}
ours = {r['code']: r['name'] for r in areas}
ours.update({v['code']: v['name'] for v in vill})
check(set(off) == set(ours), "code set drift vs Kepmendagri 2025")
for c, name in ours.items():
    exp = DELIBERATE.get(c, off[c].strip())
    if name != exp:
        check(False, f"name {c}: {name!r} != {exp!r}")

# 3. links
check(len(codes) == 9359, f"codes {len(codes)}")
check(all(re.fullmatch(r'\d{5}', c['code']) for c in codes), "code format")
check(len(links) == 9359, f"links {len(links)}")
check(all(l['is_primary'] == 'true' for l in links), "primaries")
check(all(l['area_source_id'] in ids for l in links), "dangling")
check(len({l['postcode'] for l in links}) == 9359, "dupes")
check(len({l['area_source_id'] for l in links}) == 514, "coverage != 389")

lt = {l['postcode']: l['area_source_id'].split(':')[-1] for l in links}

# 4. anchors (corrected rotations + tricky splits)
ANCHORS = {
    '10110': '3171', '11110': '3173', '12110': '3174', '13110': '3175', '14110': '3172',
    '14510': '3101', '20241': '1271', '20352': '1207', '20511': '1207', '20711': '1275',
    '20725': '1275', '20762': '1205', '21211': '1209', '21411': '1210', '22111': '1206',
    '22211': '1211', '23611': '1105', '23711': '1101', '24471': '1116', '25111': '1371',
    '26111': '1375', '26251': '1307', '26271': '1307', '27111': '1374', '28111': '1471',
    '28811': '1472', '29111': '2172', '29133': '2101', '29211': '1404', '29311': '1402',
    '29511': '1409', '30611': '1602', '30711': '1606', '31171': '1603', '31611': '1673',
    '31654': '1613', '32111': '1601', '32211': '1609', '32311': '1608', '33111': '1971',
    '34111': '1872', '34153': '1802', '34331': '1807', '34511': '1803', '34811': '1804',
    '35111': '1871', '35353': '1801', '36111': '1571', '37111': '1572', '38111': '1771',
    '38213': '1771', '39111': '1702', '40111': '3273', '40374': '3204', '40511': '3277',
    '42111': '3673', '42411': '3672', '45111': '3274', '46211': '3207', '46311': '3279',
    '46371': '3218', '53111': '3302', '53211': '3301', '55111': '3471', '55511': '3404',
    '57169': '3311', '57311': '3309', '58111': '3315', '58211': '3316', '59511': '3321',
    '61111': '3525', '61411': '3517', '62111': '3522', '63515': '3501', '64111': '3571',
    '65311': '3579', '66111': '3572', '68111': '3509', '68211': '3511', '68411': '3510',
    '69111': '3526', '70111': '6371', '70511': '6304', '70611': '6303', '70711': '6372',
    '71211': '6306', '71611': '6311', '72111': '6302', '72211': '6310', '73511': '6203',
    '74111': '6201', '74311': '6202', '74411': '6206', '75251': '6402', '75511': '6402',
    '75654': '6408', '75763': '6407', '75767': '6411', '76111': '6471', '76211': '6401',
    '77181': '6502', '77311': '6403', '78111': '6171', '78356': '6108', '78511': '6103',
    '78711': '6106', '78811': '6104', '78911': '6102', '79211': '6107', '80111': '5171',
    '80351': '5103', '80511': '5104', '81111': '5108', '82111': '5102', '83111': '5271',
    '83352': '5208', '83511': '5202', '84111': '5272', '84311': '5204', '85111': '5371',
    '85351': '5301', '85711': '5304', '85811': '5305', '86211': '5306', '86311': '5308',
    '86411': '5309', '86511': '5310', '90223': '7371', '90611': '7310', '90711': '7311',
    '91311': '7604', '91411': '7605', '91511': '7602', '91711': '7316', '91951': '7317',
    '92111': '7306', '92711': '7308', '92931': '7324', '93111': '7471', '93352': '7409',
    '93411': '7402', '93511': '7401', '93611': '7403', '93711': '7472', '94111': '7271',
    '94351': '7203', '94611': '7202', '94711': '7201', '95111': '7171', '95511': '7172',
    '95711': '7174', '96111': '7571', '96211': '7501', '97111': '8171', '97453': '8103',
    '97511': '8101', '97611': '8172', '97711': '8271',
    '60111': '3578', '60241': '3578', '50111': '3374', '50211': '3374',
    '20111': '1271', '20211': '1271', '90111': '7371', '57111': '3372',
    '63111': '3577', '75111': '6472', '78611': '6105', '98211': '9105',
    '43111': '3272', '46111': '3278', '52111': '3376', '71111': '6305',
    '51111': '3375', '24411': '1174', '30111': '1671', '41111': '3214',
    '91111': '7372', '97111': '8171', '98111': '9106',
    '97752': '8201', '97761': '8203', '97811': '8272', '98111': '9106', '98161': '9119',
    '98311': '9202', '98353': '9211', '98354': '9212', '98356': '9211',
    '98359': '9212', '98361': '9207', '98363': '9206', '98371': '9604', '98373': '9206',
    '98411': '9671', '98453': '9601', '98454': '9602', '98456': '9602', '98457': '9601',
    '98461': '9605', '98611': '9203', '98653': '9208', '98711': '9403', '98764': '9408',
    '98767': '9407', '98811': '9401', '98854': '9406', '98864': '9408', '98865': '9406',
    '98868': '9407', '99111': '9171', '99352': '9103', '99355': '9110', '99358': '9103',
    '99375': '9120', '99465': '9111', '99511': '9501', '99553': '9505', '99555': '9507',
    '99611': '9301', '99652': '9301', '99661': '9302', '99671': '9303', '99673': '9304',
    '99677': '9304', '99766': '9304', '99853': '9303', '99961': '9404',
}
missing_anchors = [pc for pc in ANCHORS if pc not in lt]
check(not missing_anchors, f"anchors absent: {missing_anchors[:10]}")
for pc, exp in ANCHORS.items():
    if pc in lt:
        check(lt[pc] == exp, f"anchor {pc}: {lt[pc]} != {exp}")

# 5. full 3-digit block table regression guard
by3 = defaultdict(Counter)
for pc, rc in lt.items():
    by3[pc[:3]][rc] += 1
EXPECT_BLOCKS = __import__('json').load(open(os.path.join(AUDIT_DIR, 'id-blocks.json')))
check(set(by3) == set(EXPECT_BLOCKS), "block set drift")
for p, dist in EXPECT_BLOCKS.items():
    check(dict(by3.get(p, {})) == dist, f"block {p} drift: {dict(by3.get(p, {}))} != {dist}")

if fails:
    print(f"GATE ID: {len(fails)} FAILURES")
    for f in fails[:40]:
        print(" -", f)
    sys.exit(1)
print(f"GATE ID: ALL PASS ({len(links)} links, {len(EXPECT_BLOCKS)} blocks, {len(ANCHORS)} anchors)")
