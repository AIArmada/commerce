import csv, sys
from collections import Counter
# Madagascar gate. Pins the B14 VERIFY-ONLY pass (zero content fixes):
# 6 provinces (ISO 3166-2:MG A/D/F/M/T/U) + 24 regions + 114 districts
# match the WP regions/districts tables exactly (modulo the French
# "Haute Matsiatra" vs the Malagasy "Matsiatra Ambony" used by the
# regions article and the bundle); postal 110 codes / 114 legs / 4
# multis at district grain. EOL normalized to CRLF on all three files
# (areas was mixed LF/CRLF from two-batch assembly).
# Oracles: WP Regions/Districts of Madagascar tables, 114 district
# articles + city/commune articles (postcodes), GeoNames MG ADM1/ADM2,
# UPU mdgEn.pdf (101/501 anchors), postcodesdb/postalcodedb town pages,
# INSTAT via the WP table refs. Mapanet (paywalled) is the bundle's own
# postal source per the overlay build note, so it cannot re-verify.
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_mg.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/madagascar-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/madagascar-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/madagascar-postal-code-areas.csv'
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
check('areas-CRLF', is_crlf(raw_a))
check('areas-trailing-CRLF', raw_a.endswith(b'\r\n'))
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
# --- tree: 6 provinces + 24 regions + 114 districts ---
check('areas-144', len(rows) == 144, str(len(rows)))
check('l1-6', sum(1 for r in rows if r['level'] == '1') == 6)
check('l2-24', sum(1 for r in rows if r['level'] == '2') == 24)
check('l3-114', sum(1 for r in rows if r['level'] == '3') == 114)
iso = {'mg:province:antananarivo': ('Antananarivo', 'T'),
       'mg:province:antsiranana': ('Antsiranana', 'D'),
       'mg:province:fianarantsoa': ('Fianarantsoa', 'F'),
       'mg:province:mahajanga': ('Mahajanga', 'M'),
       'mg:province:toamasina': ('Toamasina', 'A'),
       'mg:province:toliara': ('Toliara', 'U')}
for sid, (name, code) in iso.items():
    r = byid.get(sid)
    check(f'province-{code}', bool(r) and r['name'] == name
          and r['code'] == code and r['level'] == '1'
          and r['parent_source_id'] == '', str(r))
per_region = {'mg:region:alaotra-mangoro': 5, 'mg:region:ambatosoa': 2,
              'mg:region:amoron-i-mania': 4, 'mg:region:analamanga': 8,
              'mg:region:analanjirofo': 4, 'mg:region:androy': 4,
              'mg:region:anosy': 3, 'mg:region:atsimo-andrefana': 9,
              'mg:region:atsimo-atsinanana': 5, 'mg:region:atsinanana': 7,
              'mg:region:betsiboka': 3, 'mg:region:boeny': 6,
              'mg:region:bongolava': 2, 'mg:region:diana': 5,
              'mg:region:fitovinany': 3, 'mg:region:ihorombe': 3,
              'mg:region:itasy': 3, 'mg:region:matsiatra-ambony': 7,
              'mg:region:melaky': 5, 'mg:region:menabe': 5,
              'mg:region:sava': 4, 'mg:region:sofia': 7,
              'mg:region:vakinankaratra': 7, 'mg:region:vatovavy': 3}
got = Counter(r['parent_source_id'] for r in rows if r['level'] == '3')
for sid, n in per_region.items():
    check(f"l3-{sid.split(':')[-1]}", got.get(sid) == n,
          f'{got.get(sid)} != {n}')
# Malagasy region form (regions article) + Ambatosoa split pair.
check('matsiatra-ambony',
      byid.get('mg:region:matsiatra-ambony', {}).get('name')
      == 'Matsiatra Ambony')
amb = sorted(r['name'] for r in rows
             if r['parent_source_id'] == 'mg:region:ambatosoa')
check('ambatosoa-pair', amb == ['Mananara Avaratra', 'Maroantsetra'],
      str(amb))
# --- postal: 110 codes / 114 legs / 4 multis, all district grain ---
check('codes-110', len(codes) == 110, str(len(codes)))
check('legs-114', len(links) == 114, str(len(links)))
check('legs-district-grain',
      all(byid[r['area_source_id']]['level'] == '3' for r in links))
bycode = {}
for r in links:
    bycode.setdefault(r['postcode'], []).append(r)
multis = sorted(k for k, v in bycode.items() if len(v) > 1)
check('multis-4', multis == ['113', '303', '305', '314'], str(multis))
for code, legs in bycode.items():
    prim = [r for r in legs if r['is_primary'] == 'true']
    check(f'primary-{code}', len(prim) == 1, str(len(prim)))
# Sealed multis (both partners carry the code in their WP articles).
sealed_multis = {'113': ('mg:district:betafo', 'mg:district:mandoto'),
                 '303': ('mg:district:ambalavao', 'mg:district:lalangina'),
                 '305': ('mg:district:ambohimahasoa',
                         'mg:district:vohibato'),
                 '314': ('mg:district:ikalamavony', 'mg:district:isandra')}
for code, (p, s) in sealed_multis.items():
    legs = {r['area_source_id']: r['is_primary']
            for r in bycode.get(code, [])}
    check(f'multi-{code}',
          legs.get(p) == 'true' and legs.get(s) == 'false', str(legs))
# UPU + city/commune + directory anchors.
anchors = {'101': 'mg:district:antananarivo-renivohitra',
           '102': 'mg:district:antananarivo-atsimondrano',
           '103': 'mg:district:antananarivo-avaradrano',
           '110': 'mg:district:antsirabe-i',
           '201': 'mg:district:antsiranana-i',
           '203': 'mg:district:ambanja',
           '204': 'mg:district:ambilobe',
           '207': 'mg:district:nosy-be',
           '301': 'mg:district:fianarantsoa-i',
           '401': 'mg:district:mahajanga-i',
           '501': 'mg:district:toamasina-i',
           '502': 'mg:district:toamasina-ii',
           '504': 'mg:district:amparafaravola',
           '508': 'mg:district:vohibinany',
           '601': 'mg:district:toliara-i',
           '602': 'mg:district:toliara-ii',
           '604': 'mg:district:ambovombe-androy',
           '614': 'mg:district:taolagnaro'}
for code, sid in anchors.items():
    legs = bycode.get(code, [])
    check(f'anchor-{code}',
          any(r['area_source_id'] == sid and r['is_primary'] == 'true'
              for r in legs), str(legs))
# 2026-10-06 retry: 24 more seals (WP town/commune + OSM calculated +
# addressed usage over the French-list single lineage).
seals = {'105': 'mg:district:ambohidratrimo',
         '115': 'mg:district:fenoarivo-afovoany',
         '119': 'mg:district:tsiroanomandidy',
         '206': 'mg:district:antalaha',
         '306': 'mg:district:ambositra',
         '307': 'mg:district:befotaka',
         '318': 'mg:district:midongy-atsimo',
         '402': 'mg:district:mahajanga-ii',
         '404': 'mg:district:ambatomainty',
         '416': 'mg:district:marovoay',
         '420': 'mg:district:soalala',
         '503': 'mg:district:ambatondrazaka',
         '506': 'mg:district:anosibe-anala',
         '507': 'mg:district:antanambao-manampotsy',
         '509': 'mg:district:fenoarivo-atsinanana',
         '514': 'mg:district:moramanga',
         '515': 'mg:district:nosy-boraha',
         '516': 'mg:district:soanierana-ivongo',
         '517': 'mg:district:vatomandry',
         '603': 'mg:district:amboasary-atsimo',
         '605': 'mg:district:ampanihy',
         '612': 'mg:district:betioky-atsimo',
         '617': 'mg:district:miandrivazo',
         '620': 'mg:district:sakaraha'}
for code, sid in seals.items():
    legs = bycode.get(code, [])
    check(f'seal-{code}',
          any(r['area_source_id'] == sid and r['is_primary'] == 'true'
              for r in legs), str(legs))
# 2026-10-06 retry: 3 residual holds (French-list-only, WP exhausted).
for code, sid in [('111', 'mg:district:antsirabe-ii'),
                  ('323', 'mg:district:manandriana'),
                  ('606', 'mg:district:ankazoabo-atsimo')]:
    legs = bycode.get(code, [])
    check(f'held-{code}',
          len(legs) == 1 and legs[0]['area_source_id'] == sid
          and legs[0]['is_primary'] == 'true', str(legs))
# 302 intentionally absent (no Fianarantsoa II district anywhere).
check('302-absent', '302' not in bycode)
# Province-block sanity: 1xx..6xx match the district's province.
prov_of = {}
for r in rows:
    if r['level'] == '3':
        reg = byid[r['parent_source_id']]
        prov_of[r['source_id']] = byid[reg['parent_source_id']]['name']
blocks = {'Antananarivo': '1', 'Antsiranana': '2', 'Fianarantsoa': '3',
          'Mahajanga': '4', 'Toamasina': '5', 'Toliara': '6'}
bad = [(r['postcode'], r['area_source_id']) for r in links
       if not r['postcode'].startswith(blocks[prov_of[r['area_source_id']]])]
check('province-blocks', not bad, str(bad))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
