import csv, sys
from collections import Counter
# Argentina gate. Pins the B19 worker pass (553 areas: 24 L1 + 529
# L2; 2501 codes / 3115 links; areas LF, postal pure CRLF): L1
# 24/24 ISO-exact; L2 membership 529/529 vs live georef (Tolhuin
# present, no 2024+ splits). AREAS (17): Pueyrredón rename
# (2010 law, bundle 16y stale), Cafayate/Hucal typos, GSM full
# name, Feliciano short, San Miguel (Corrientes) qualifier,
# Realicó/Rinconada suffix-strips, 9 accent fixes (curuzu-cuatia
# slug change is a byte no-op: accent move vanishes in ASCII).
# POSTAL: 2 San Miguel retargets (operator W/SAN MIGUEL
# name-collided onto BA partido; ONLY cross-province mis-map),
# tie flips 3151->Victoria + 5400->Capital-SJ, CABA 6 fixes
# (C1405/C1424->C6, C1406->C7P+C10s+C6s, C1416 C10<->C11 swap,
# C1426->C13P+C14s, C1439->C8) from addressed usage; 3112+3.
# Holds: SDE Capital/JFB 3v3, BA Madariaga/Rosales, 3162, 8522,
# 5157, 4635-Y, CABA residual ~295, thin ties, singletons,
# 8133, 5272, 13 bare dupes (official-bare kept by decision).
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_ar.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/argentina-address-areas.csv'
C = f'{G}/argentina-postal-codes.csv'
L = f'{G}/argentina-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
raw_c = open(C, 'rb').read()
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 2502, str(raw_c.count(b'\r\n')))
raw_l = open(L, 'rb').read()
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 3116, str(raw_l.count(b'\r\n')))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-553', len(rows) == 553, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-24', sum(1 for r in rows if r['level'] == '1') == 24)
check('L2-529', sum(1 for r in rows if r['level'] == '2') == 529)
iso = {'salta': 'A', 'buenos-aires': 'B', 'san-luis': 'D', 'entre-rios': 'E',
       'la-rioja': 'F', 'santiago-del-estero': 'G', 'chaco': 'H', 'san-juan': 'J',
       'catamarca': 'K', 'la-pampa': 'L', 'mendoza': 'M', 'misiones': 'N',
       'formosa': 'P', 'neuquen': 'Q', 'rio-negro': 'R', 'santa-fe': 'S',
       'tucuman': 'T', 'chubut': 'U', 'tierra-del-fuego': 'V',
       'corrientes': 'W', 'cordoba': 'X', 'jujuy': 'Y', 'santa-cruz': 'Z'}
ok = all(byid.get(f'ar:province:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-23', ok and len(iso) == 23)
check('caba-C', byid.get('ar:city:autonomous-city-of-buenos-aires', {}).get('code') == 'C')
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('fix-pueyrredon', byid.get('ar:department:juan-martin-de-pueyrredon', {}).get('name') == 'Juan Martín de Pueyrredón')
check('fix-la-capital-gone', 'ar:department:la-capital-san-luis' not in byid)
check('fix-cafayate', byid.get('ar:department:cafayate', {}).get('name') == 'Cafayate')
check('fix-hucal', byid.get('ar:department:hucal', {}).get('name') == 'Hucal')
check('fix-feliciano', byid.get('ar:department:feliciano', {}).get('name') == 'Feliciano')
check('fix-gsm', byid.get('ar:department:general-jose-de-san-martin', {}).get('name') == 'General José de San Martín')
check('fix-san-miguel-ctes', byid.get('ar:department:san-miguel-corrientes', {}).get('name') == 'San Miguel (Corrientes)')
check('fix-atreuco', byid.get('ar:department:atreuco', {}).get('name') == 'Atreucó')
check('fix-chical-co', byid.get('ar:department:chical-co', {}).get('name') == 'Chical Co')
check('fix-puan', byid.get('ar:partido:puan', {}).get('name') == 'Puán')
check('hold-sde-capital', 'Capital' in byid.get('ar:department:capital-santiago-del-estero', {}).get('name', ''))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-2501', len(codes) == 2501, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-3115', len(legs) == 3115, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], {})[r['area_source_id']] = r['is_primary']
check('P01-3483', bycode['3483'].get('ar:department:san-miguel-corrientes') == 'true')
check('P02-3485', bycode['3485'].get('ar:department:san-miguel-corrientes') == 'true')
check('P03-3151', bycode['3151'].get('ar:department:victoria') == 'true')
check('P04-5400', bycode['5400'].get('ar:department:capital-san-juan') == 'true')
check('C01-C1405', bycode['C1405'].get('ar:commune:comuna-6') == 'true')
check('C02-C1406', bycode['C1406'].get('ar:commune:comuna-7') == 'true' and bycode['C1406'].get('ar:commune:comuna-10') == 'false' and bycode['C1406'].get('ar:commune:comuna-6') == 'false')
check('C03-C1416', bycode['C1416'].get('ar:commune:comuna-11') == 'true')
check('C04-C1424', bycode['C1424'].get('ar:commune:comuna-6') == 'true')
check('C05-C1426', bycode['C1426'].get('ar:commune:comuna-13') == 'true' and bycode['C1426'].get('ar:commune:comuna-14') == 'false')
check('C06-C1439', bycode['C1439'].get('ar:commune:comuna-8') == 'true')
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
