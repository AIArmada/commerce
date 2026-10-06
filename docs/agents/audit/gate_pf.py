import csv, re, sys
from collections import Counter
A = './packages/addressing/resources/geography/french-polynesia-address-areas.csv'
C = './packages/addressing/resources/geography/french-polynesia-postal-codes.csv'
L = './packages/addressing/resources/geography/french-polynesia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# EOL + trailing newline assertions (LF-only files).
for path, label in [(A, 'areas'), (C, 'codes'), (L, 'links')]:
    raw = open(path, 'rb').read()
    check(f'{label}-lf-only', b'\r' not in raw)
    check(f'{label}-trailing-nl', raw.endswith(b'\n'))
rows = list(csv.DictReader(open(A)))
codes = list(csv.DictReader(open(C)))
links = list(csv.DictReader(open(L)))
byid = {r['source_id']: r for r in rows}
check('areas-53', len(rows) == 53, str(len(rows)))
check('divisions-5', sum(1 for r in rows if r['type'] == 'division') == 5)
check('communes-48', sum(1 for r in rows if r['type'] == 'commune') == 48)
check('codes-83', len(codes) == 83, str(len(codes)))
check('links-93', len(links) == 93, str(len(links)))
# Division rows: WP Administrative divisions of French Polynesia (5 subdivisions).
for slug, name, code in [('austral-islands', 'Austral Islands', '01'),
        ('leeward-islands', 'Leeward Islands', '02'),
        ('marquesas-islands', 'Marquesas Islands', '03'),
        ('tuamotu-gambier', 'Tuamotu-Gambier', '04'),
        ('windward-islands', 'Windward Islands', '05')]:
    r = byid.get(f'pf:division:{slug}')
    check(f'div-{code}', bool(r) and r['name'] == name and r['code'] == code
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
# Full parent table: WP commune table + ISPF RP2022 roster + MFG subdivision tags.
table = {
 'pf:division:marquesas-islands': ['fatu-hiva', 'hiva-oa', 'nuku-hiva', 'tahuata', 'ua-huka', 'ua-pou'],
 'pf:division:tuamotu-gambier': ['anaa', 'arutua', 'fakarava', 'fangatau', 'gambier', 'hao',
   'hikueru', 'makemo', 'manihi', 'napuka', 'nukutavake', 'puka-puka', 'rangiroa', 'reao',
   'takaroa', 'tatakoto', 'tureia'],
 'pf:division:austral-islands': ['raivavae', 'rapa', 'rimatara', 'rurutu', 'tubuai'],
 'pf:division:leeward-islands': ['bora-bora', 'huahine', 'maupiti', 'tahaa', 'taputapuatea',
   'tumaraa', 'uturoa'],
 'pf:division:windward-islands': ['arue', 'faaa', 'hitiaa-o-te-ra', 'mahina', 'moorea-maiao',
   'paea', 'papara', 'papeete', 'pirae', 'punaauia', 'taiarapu-est', 'taiarapu-ouest', 'teva-i-uta'],
}
ok = True
for p, kids in table.items():
    have = sorted(r['source_id'].split(':')[-1] for r in rows if r['parent_source_id'] == p)
    if have != sorted(kids):
        print('FAIL parent', p, have); fails.append(f'parent {p}'); ok = False
if ok: print('PASS all 5 parent mappings')
# INSEE commune codes 98711-98758 (MFG + Etalab COG + ISPF 11-58 numbering).
insee = {'anaa': '98711', 'arue': '98712', 'arutua': '98713', 'bora-bora': '98714',
 'faaa': '98715', 'fakarava': '98716', 'fangatau': '98717', 'fatu-hiva': '98718',
 'gambier': '98719', 'hao': '98720', 'hikueru': '98721', 'hitiaa-o-te-ra': '98722',
 'hiva-oa': '98723', 'huahine': '98724', 'mahina': '98725', 'makemo': '98726',
 'manihi': '98727', 'maupiti': '98728', 'moorea-maiao': '98729', 'napuka': '98730',
 'nuku-hiva': '98731', 'nukutavake': '98732', 'paea': '98733', 'papara': '98734',
 'papeete': '98735', 'pirae': '98736', 'puka-puka': '98737', 'punaauia': '98738',
 'raivavae': '98739', 'rangiroa': '98740', 'rapa': '98741', 'reao': '98742',
 'rimatara': '98743', 'rurutu': '98744', 'tahaa': '98745', 'tahuata': '98746',
 'taiarapu-est': '98747', 'taiarapu-ouest': '98748', 'takaroa': '98749',
 'taputapuatea': '98750', 'tatakoto': '98751', 'teva-i-uta': '98752', 'tubuai': '98753',
 'tumaraa': '98754', 'tureia': '98755', 'ua-huka': '98756', 'ua-pou': '98757',
 'uturoa': '98758'}
bad = [s for s, cd in insee.items()
       if byid.get(f'pf:commune:{s}', {}).get('code') != cd]
check('insee-48', not bad, str(bad))
check('communes-L2', all(byid[f'pf:commune:{s}']['level'] == '2' for s in insee))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
# Code file: 987xx, ascending, LF.
bad = [c['code'] for c in codes if not re.match(r'^987\d\d$', c['code'])]
check('code-format-987xx', not bad, str(bad[:3]))
check('codes-ascending', [c['code'] for c in codes] == sorted(c['code'] for c in codes))
check('codes-country-PF', all(c['country_code'] == 'PF' for c in codes))
# Stale/special codes excluded: 98702 Faaa-aeroport, 98713/98715 Papeete BP/messageries,
# 98717 old Punaauia annexe, 98791 old Taenga (OPT ~2012 listing); absent from all modern oracles.
code_set = {c['code'] for c in codes}
check('stale-excluded', not (code_set & {'98702', '98713', '98715', '98717', '98791', '98759'}))
# Link invariants.
dang = [l for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
prim = Counter(l['postcode'] for l in links if l['is_primary'] == 'true')
check('exactly-one-primary', len(prim) == 83 and all(c == 1 for c in prim.values()))
check('codes-links-same-set', code_set == set(prim), str(code_set ^ set(prim)))
order = [l['postcode'] for l in links]
check('links-ascending', order == sorted(order))
seen = set()
adj_ok = True
for i, l in enumerate(links):
    pc = l['postcode']
    if pc in seen and links[i - 1]['postcode'] != pc:
        adj_ok = False
    seen.add(pc)
check('legs-adjacent', adj_ok)
# Within-code order is alphabetical by area_source_id (primary is NOT first:
# 98735/98796 list primaries last, 98790 in the middle).
from itertools import groupby
def _alpha_ok() -> bool:
    for _, grp in groupby(links, key=lambda l: l['postcode']):
        areas = [l['area_source_id'] for l in grp]
        if areas != sorted(areas):
            return False
    return True
alpha_ok = _alpha_ok()
check('within-code-alphabetical', alpha_ok)
check('all-to-commune', all(byid[l['area_source_id']]['type'] == 'commune' for l in links))
# Per-commune link counts (GeoNames + MFG + Etalab crosswalk).
exp = {'anaa': 3, 'arue': 1, 'arutua': 3, 'bora-bora': 1, 'faaa': 1, 'fakarava': 4,
 'fangatau': 2, 'fatu-hiva': 1, 'gambier': 3, 'hao': 2, 'hikueru': 2,
 'hitiaa-o-te-ra': 4, 'hiva-oa': 3, 'huahine': 2, 'mahina': 2, 'makemo': 3,
 'manihi': 2, 'maupiti': 1, 'moorea-maiao': 2, 'napuka': 1, 'nuku-hiva': 3,
 'nukutavake': 2, 'paea': 1, 'papara': 1, 'papeete': 1, 'pirae': 1, 'puka-puka': 1,
 'punaauia': 2, 'raivavae': 1, 'rangiroa': 5, 'rapa': 2, 'reao': 2, 'rimatara': 2,
 'rurutu': 1, 'tahaa': 2, 'tahuata': 1, 'taiarapu-est': 4, 'taiarapu-ouest': 3,
 'takaroa': 3, 'taputapuatea': 1, 'tatakoto': 1, 'teva-i-uta': 2, 'tubuai': 1,
 'tumaraa': 1, 'tureia': 1, 'ua-huka': 2, 'ua-pou': 2, 'uturoa': 1}
for slug, n in exp.items():
    have = sum(1 for l in links if l['area_source_id'] == f'pf:commune:{slug}')
    check(f'count-{slug}-{n}', have == n, str(have))
link = {}
for l in links:
    link.setdefault(l['postcode'], []).append((l['area_source_id'], l['is_primary']))
def prim_of(pc):
    return next(s for s, p in link[pc] if p == 'true')
# Papeete/Faaa/Punaauia cluster pins.
check('98714-papeete', prim_of('98714') == 'pf:commune:papeete')
check('98704-faaa', prim_of('98704') == 'pf:commune:faaa')
check('punaauia-98703-98718', sorted(pc for pc, legs in link.items()
      for s, p in legs if s == 'pf:commune:punaauia') == ['98703', '98718'])
# UPU PYF 08/2011 anchor: 98709 MAHINA TAHITI.
check('98709-mahina', prim_of('98709') == 'pf:commune:mahina')
# All 10 secondary legs (GeoNames + MFG + Etalab, 3 signals each).
sec = [(pc, s) for pc, legs in link.items() for s, p in legs if p == 'false']
check('secondaries-10', len(sec) == 10, str(len(sec)))
for pc, slug in [('98732', 'maupiti'), ('98735', 'taputapuatea'), ('98735', 'tumaraa'),
        ('98790', 'anaa'), ('98790', 'fakarava'), ('98790', 'hao'), ('98790', 'hikueru'),
        ('98790', 'makemo'), ('98790', 'takaroa'), ('98796', 'hiva-oa')]:
    check(f'sec-{pc}-{slug}', (pc, f'pf:commune:{slug}') in [(p, s) for p, s in sec])
# Shared-code primaries (kept: oracles list associations, not primaries; ties keep).
check('98732-primary-huahine', prim_of('98732') == 'pf:commune:huahine')
check('98735-primary-uturoa', prim_of('98735') == 'pf:commune:uturoa')
check('98790-primary-rangiroa', prim_of('98790') == 'pf:commune:rangiroa')
check('98796-primary-nuku-hiva', prim_of('98796') == 'pf:commune:nuku-hiva')
# Single-code communes hold no secondary.
check('single-code-pure', all(link[pc][0][1] == 'true' and len(link[pc]) == 1
      for pc in ['98701', '98730', '98740', '98743', '98750', '98753', '98754', '98772',
                 '98774', '98783', '98784']))
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
