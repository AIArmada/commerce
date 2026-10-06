import csv, re, sys
from collections import Counter
# Iran gate. M7 revisit: 96914 South Khorasan -> Razavi Khorasan
# (5 unanimous Gonabad-area rows; Photon 5/5 Razavi/Gonabad; wiki
# Gonabad County + Kakhk District + Zibad all Razavi; TripAdvisor
# addressed usage "Gonabad 96914") + 97716 Razavi secondary
# (Gazi/Jazin rows -> Jazin RD, Bajestan County, Razavi; Ferdows
# 3v2 keeps the South primary). Fresh 364-row/109-code Mapanet
# re-pull is code-set-identical to the build. Tree is COD v01
# exact (31 + 429, zero parent mismatches). Run from repo root:
# python3 /tmp/geo-verify/M7/IR/gate_ir.py
A = '/Users/saiffil/Herd/commerce/packages/addressing/resources/geography/iran-address-areas.csv'
C = '/Users/saiffil/Herd/commerce/packages/addressing/resources/geography/iran-postal-codes.csv'
L = '/Users/saiffil/Herd/commerce/packages/addressing/resources/geography/iran-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
# --- tree: 31 provinces + 429 counties (COD v01 May-2019 exact) ---
check('areas-460', len(rows) == 460, str(len(rows)))
check('provinces-31', sum(1 for r in rows if r['type'] == 'province') == 31)
check('counties-429', sum(1 for r in rows if r['type'] == 'county') == 429)
check('l1-31', sum(1 for r in rows if r['level'] == '1') == 31)
check('l2-429', sum(1 for r in rows if r['level'] == '2') == 429)
iso = {'ir:province:markazi': ('Markazi', '00'),
 'ir:province:gilan': ('Gilan', '01'),
 'ir:province:mazandaran': ('Mazandaran', '02'),
 'ir:province:east-azerbaijan': ('East Azerbaijan', '03'),
 'ir:province:west-azerbaijan': ('West Azerbaijan', '04'),
 'ir:province:kermanshah': ('Kermanshah', '05'),
 'ir:province:khuzestan': ('Khuzestan', '06'),
 'ir:province:fars': ('Fars', '07'),
 'ir:province:kerman': ('Kerman', '08'),
 'ir:province:razavi-khorasan': ('Razavi Khorasan', '09'),
 'ir:province:isfahan': ('Isfahan', '10'),
 'ir:province:sistan-and-baluchestan': ('Sistan and Baluchestan', '11'),
 'ir:province:kurdistan': ('Kurdistan', '12'),
 'ir:province:hamadan': ('Hamadan', '13'),
 'ir:province:chaharmahal-and-bakhtiari': ('Chaharmahal and Bakhtiari', '14'),
 'ir:province:lorestan': ('Lorestan', '15'),
 'ir:province:ilam': ('Ilam', '16'),
 'ir:province:kohgiluyeh-and-boyer-ahmad': ('Kohgiluyeh and Boyer-Ahmad', '17'),
 'ir:province:bushehr': ('Bushehr', '18'),
 'ir:province:zanjan': ('Zanjan', '19'),
 'ir:province:semnan': ('Semnan', '20'),
 'ir:province:yazd': ('Yazd', '21'),
 'ir:province:hormozgan': ('Hormozgan', '22'),
 'ir:province:tehran': ('Tehran', '23'),
 'ir:province:ardabil': ('Ardabil', '24'),
 'ir:province:qom': ('Qom', '25'),
 'ir:province:qazvin': ('Qazvin', '26'),
 'ir:province:golestan': ('Golestan', '27'),
 'ir:province:north-khorasan': ('North Khorasan', '28'),
 'ir:province:south-khorasan': ('South Khorasan', '29'),
 'ir:province:alborz': ('Alborz', '30')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'iso-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['type'] == 'province', str(r))
# IR-09 formal ISO name is Khorasan-e Razavi; the "Central Khorasan"
# English gloss on the ISO 3166-2:IR wiki page is unofficial.
check('razavi-not-central',
      byid['ir:province:razavi-khorasan']['name'] == 'Razavi Khorasan')
check('west-azerbaijan-spelling',
      byid['ir:province:west-azerbaijan']['name'] == 'West Azerbaijan'
      and 'ir:province:west-azarbaijan' not in byid)
counts = {'ir:province:markazi': 12, 'ir:province:gilan': 16,
 'ir:province:mazandaran': 22, 'ir:province:east-azerbaijan': 20,
 'ir:province:west-azerbaijan': 17, 'ir:province:kermanshah': 14,
 'ir:province:khuzestan': 27, 'ir:province:fars': 29,
 'ir:province:kerman': 23, 'ir:province:razavi-khorasan': 28,
 'ir:province:isfahan': 24, 'ir:province:sistan-and-baluchestan': 19,
 'ir:province:kurdistan': 10, 'ir:province:hamadan': 9,
 'ir:province:chaharmahal-and-bakhtiari': 9, 'ir:province:lorestan': 11,
 'ir:province:ilam': 10, 'ir:province:kohgiluyeh-and-boyer-ahmad': 8,
 'ir:province:bushehr': 10, 'ir:province:zanjan': 8,
 'ir:province:semnan': 8, 'ir:province:yazd': 10,
 'ir:province:hormozgan': 13, 'ir:province:tehran': 16,
 'ir:province:ardabil': 10, 'ir:province:qom': 1,
 'ir:province:qazvin': 6, 'ir:province:golestan': 14,
 'ir:province:north-khorasan': 8, 'ir:province:south-khorasan': 11,
 'ir:province:alborz': 6}
have = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
ok = True
for sid, n in counts.items():
    if have[sid] != n:
        print('FAIL county-count', sid, have[sid]); fails.append(f'count {sid}'); ok = False
if ok: print('PASS all 31 per-province county counts (429)')
check('abadan-khuzestan',
      byid['ir:county:abadan']['parent_source_id'] == 'ir:province:khuzestan')
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
sids = [r['source_id'] for r in rows]
check('unique-source-ids', len(set(sids)) == len(sids))
# --- postal: 109 codes / 111 links, all L1, 5-digit prefixes ---
check('codes-109', len(codes) == 109, str(len(codes)))
check('links-111', len(links) == 111, str(len(links)))
bad = [c['code'] for c in codes if not re.match(r'^\d{5}$', c['code'])]
check('code-format-5n', not bad, str(bad[:3]))
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
multi = [pc for pc, c in prim.items() if c != 1]
check('exactly-one-primary', len(prim) == 109 and not multi,
      f'primaries={len(prim)} multi={multi[:3]}')
check('all-l1', all(l['area_source_id'].split(':')[1] == 'province'
      for l in links))
code_set = {c['code'] for c in codes}
link_set = {l['postcode'] for l in links}
check('codes-align-links', code_set == link_set)
# --- multis: 45617 + 97716 duals ---
lcounts = Counter(l['postcode'] for l in links)
multis = sorted(pc for pc, c in lcounts.items() if c > 1)
check('multis-2', multis == ['45617', '97716'], str(multis))
exp = {'45617': [('ir:province:qazvin', 'true'),
    ('ir:province:zanjan', 'false')],
 '97716': [('ir:province:south-khorasan', 'true'),
    ('ir:province:razavi-khorasan', 'false')]}
for pc, legs in exp.items():
    got = sorted((l['area_source_id'], l['is_primary']) for l in links
                 if l['postcode'] == pc)
    check(f'multi-{pc}', got == sorted(legs), str(got))
# 45617: Qazvin side = 10 r1=21 rows (Buin Zahra + Takestan incl. the
# Nahavand-name row at Takestan coords) + Magan/Mahin (Tarom-e Sofla,
# Qazvin); Zanjan side = Abhar/Abkhar + Qarabulaq x2 + Sain Qaleh.
# 97716: Ferdows/Tun 3v2 keep the South primary; Gazi/Jazin rows sit
# in Jazin RD, Bajestan County (Razavi).
link = {}
for l in links:
    if l['is_primary'] == 'true':
        link[l['postcode']] = l['area_source_id']
# --- adjudication pins ---
check('move-96914-razavi', link.get('96914') == 'ir:province:razavi-khorasan',
      str(link.get('96914')))
check('north-94614', link.get('94614') == 'ir:province:north-khorasan')
check('north-94714', link.get('94714') == 'ir:province:north-khorasan')
check('south-96714', link.get('96714') == 'ir:province:south-khorasan')
check('south-97614', link.get('97614') == 'ir:province:south-khorasan')
check('south-97716', link.get('97716') == 'ir:province:south-khorasan')
check('tehran-11369', link.get('11369') == 'ir:province:tehran')
check('tehran-33134', link.get('33134') == 'ir:province:tehran')
check('qom-37184', link.get('37184') == 'ir:province:qom')
check('qom-39771', link.get('39771') == 'ir:province:qom')
check('qazvin-45617', link.get('45617') == 'ir:province:qazvin')
check('yazd-97514', link.get('97514') == 'ir:province:yazd')
check('kerman-78514', link.get('78514') == 'ir:province:kerman')
check('kerman-78814', link.get('78814') == 'ir:province:kerman')
# --- per-province primary counts (post M7 move) ---
expect = {'ir:province:markazi': 3, 'ir:province:gilan': 3,
 'ir:province:mazandaran': 7, 'ir:province:east-azerbaijan': 2,
 'ir:province:west-azerbaijan': 2, 'ir:province:kermanshah': 10,
 'ir:province:khuzestan': 3, 'ir:province:fars': 3,
 'ir:province:kerman': 2, 'ir:province:razavi-khorasan': 1,
 'ir:province:isfahan': 3, 'ir:province:sistan-and-baluchestan': 1,
 'ir:province:kurdistan': 6, 'ir:province:hamadan': 11,
 'ir:province:chaharmahal-and-bakhtiari': 3, 'ir:province:lorestan': 7,
 'ir:province:ilam': 4, 'ir:province:kohgiluyeh-and-boyer-ahmad': 1,
 'ir:province:bushehr': 3, 'ir:province:zanjan': 2,
 'ir:province:semnan': 2, 'ir:province:yazd': 1,
 'ir:province:hormozgan': 7, 'ir:province:tehran': 2,
 'ir:province:ardabil': 9, 'ir:province:qom': 2,
 'ir:province:qazvin': 1, 'ir:province:golestan': 3,
 'ir:province:north-khorasan': 2, 'ir:province:south-khorasan': 3,
 'ir:province:alborz': 0}
phave = Counter(l['area_source_id'] for l in links
                if l['is_primary'] == 'true')
for sid, n in expect.items():
    check(f"primaries-{sid.split(':')[-1]}-{n}", phave[sid] == n,
          str(phave[sid]))
# Codeless by design: Alborz has no Mapanet r1 and no rows; Razavi
# Khorasan now holds 96914 + the 97716 secondary (30/31 covered).
check('codeless-alborz',
      not [l for l in links if l['area_source_id'] == 'ir:province:alborz'])
check('razavi-covered', phave['ir:province:razavi-khorasan'] == 1
      and sum(1 for l in links
              if l['area_source_id'] == 'ir:province:razavi-khorasan'
              and l['is_primary'] == 'false') == 1)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
