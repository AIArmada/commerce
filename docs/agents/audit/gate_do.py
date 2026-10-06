import csv, re, sys
from collections import Counter
# Dominican Republic gate. Pins the B16 inline pass (1 rename:
# Ingenio Quisqueya -> Quisqueya; 200 areas / 528 codes / 530
# legs / 2 multis): 10 regions (ISO DO-33..42 exact) + 31
# provinces + 1 district (ISO DO-01..32 exact, Baoruco kept
# per ISO-current + Statoids GEC-2013 over es.wiki Bahoruco)
# + 158 municipalities (es.wiki 158-row table: 158/158
# normalized match, 158/158 parents, bundle uses formal
# official names). Postal: bundle code set == live INPOSDOM
# data.json set exactly (528/528; operator's '' + 'Sin titulo'
# junk rows excluded); both multis dual-confirmed by the
# operator itself (71100 Pueblo Viejo + Guayabal, 81100
# Cabral + Jaquimeyes) with population-majority primaries
# (15,344 > 10,003; 19,293 > 8,374). 6 GN-only DN-sector
# codes (10110/10131/10203/10206/11111/11708) correctly
# excluded (absent from live operator; 4 are acc=1
# estimates). Odd-prefix keeps coordinate-verified: 58081
# Santiago (19.471,-70.695), 56000 Moca town. Hold: 'Jamao
# al Norte' lowercase al (Spanish orthography; es.wiki 'Al').
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_do.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/dominican-republic-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/dominican-republic-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/dominican-republic-postal-code-areas.csv'
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
check('codes-LF-only', b'\r' not in raw_c)
check('codes-trailing-LF', raw_c.endswith(b'\n'))
check('links-LF-only', b'\r' not in raw_l)
check('links-trailing-LF', raw_l.endswith(b'\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-200', len(rows) == 200, str(len(rows)))
check('region-10', sum(1 for r in rows if r['type'] == 'region') == 10)
check('province-31', sum(1 for r in rows if r['type'] == 'province') == 31)
check('district-1', sum(1 for r in rows if r['type'] == 'district') == 1)
check('municipality-158', sum(1 for r in rows if r['type'] == 'municipality') == 158)
check('l1-codes-33-42', sorted(r['code'] for r in rows if r['level'] == '1') ==
      ['33', '34', '35', '36', '37', '38', '39', '40', '41', '42'])
check('l2-codes-01-32', sorted(r['code'] for r in rows if r['level'] == '2') ==
      [f'{i:02d}' for i in range(1, 33)])
spots = {'do:region:ozama': ('Ozama', '40', ''),
         'do:district:distrito-nacional': ('Distrito Nacional', '01', 'do:region:ozama'),
         'do:province:baoruco': ('Baoruco', '03', 'do:region:enriquillo'),
         'do:province:azua': ('Azua', '02', 'do:region:valdesia'),
         'do:municipality:quisqueya': ('Quisqueya', '', 'do:province:san-pedro-de-macoris'),
         'do:municipality:jamao-al-norte': ('Jamao al Norte', '', 'do:province:espaillat'),
         'do:municipality:santo-domingo-de-guzman': ('Santo Domingo de Guzmán', '', 'do:district:distrito-nacional')}
for sid, (nm, cd, par) in spots.items():
    r = byid.get(sid, {})
    ok = r.get('name') == nm and r.get('code', '') == cd and (not par or r.get('parent_source_id') == par)
    check(f'spot-{sid}', ok, str((r.get('name'), r.get('code'), r.get('parent_source_id'))))
check('quisqueya-renamed', 'do:municipality:ingenio-quisqueya' not in byid)
check('codes-528', len(codes) == 528, str(len(codes)))
check('links-530', len(links) == 530, str(len(links)))
check('codes-DO', all(c['country_code'] == 'DO' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
multis = sorted(c for c, v in have.items() if v > 1)
check('multis-2', multis == ['71100', '81100'], str(multis))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-code', all(len(v) == 1 for v in prims.values()) and len(prims) == 528)
def legs_of(pc):
    return sorted(l['area_source_id'] for l in links if l['postcode'] == pc)
check('71100-dual', legs_of('71100') == ['do:municipality:guayabal', 'do:municipality:pueblo-viejo'],
      str(legs_of('71100')))
check('71100-primary', prims.get('71100') == ['do:municipality:pueblo-viejo'])
check('81100-dual', legs_of('81100') == ['do:municipality:cabral', 'do:municipality:jaquimeyes'],
      str(legs_of('81100')))
check('81100-primary', prims.get('81100') == ['do:municipality:cabral'])
for c in ['10110', '10131', '10203', '10206', '11111', '11708']:
    check(f'excluded-{c}', c not in clist)
keeps = {'21400': 'do:municipality:quisqueya', '10100': 'do:district:distrito-nacional',
         '56000': 'do:municipality:moca', '58081': 'do:municipality:santiago-de-los-caballeros',
         '92400': 'do:municipality:monte-plata', '73500': 'do:municipality:juan-santiago',
         '43400': 'do:municipality:la-mata', '15801': 'do:municipality:santo-domingo-este'}
for pc, sid in keeps.items():
    check(f'keep-{pc}', prims.get(pc) == [sid], str(prims.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
