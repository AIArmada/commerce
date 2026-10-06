import csv, re, sys
from collections import Counter
# Ecuador gate. Pins the B17 inline pass (10 INEC-2026 formal
# renames incl. slugs + 176 leg moves; 246 areas / 1225 codes /
# 1225 legs / 0 multis): 24 provinces ISO 3166-2:EC exact + 222
# cantons INEC-DPA-2026 exact modulo documented spellings.
# RENAMES (INEC DPA 2026 + es.wiki annex; GN ADM2 agrees except
# Quito/Yaguachi/Santiago where GN is stale-short): Baños ->
# Baños de Agua Santa, Quito -> Distrito Metropolitano de Quito,
# Joya de los Sachas -> La Joya de los Sachas, Pueblo Viejo ->
# Puebloviejo, Río Verde -> Rioverde, Yaguachi -> San Jacinto de
# Yaguachi, Pelileo -> San Pedro de Pelileo, Santiago de Méndez
# -> Santiago, Píllaro -> Santiago de Píllaro, Santo Domingo de
# los Colorados -> Santo Domingo. KEEPS: Veinticuatro de Mayo
# (annex + en.wiki + GN spell out; INEC '24 DE MAYO' is digit
# style), Alfredo Baquerizo Moreno (INEC '(JUJAN)' parenthetical
# only), General Antonio Elizalde (INEC double-space typo).
# Postal: bundle set == GN set exactly (1225/1225); 0 multis;
# 080701-03 La Concordia keeps legacy Esmeraldas 08 prefix
# (Esmeraldas canton until 2013 transfer; GN admin2 2302 Santo
# Domingo confirms); 900001 El Piedrero -> El Triunfo (UTA thesis
# + citypopulation + 2017 decree), 900002/3 Manga del Cura ->
# El Carmen (en.wiki explicit + UNAL/ULEAM canton-study), 900004
# Las Golondrinas -> Cotacachi (citypopulation + SRI doc + topo
# map with 900004). HOLD: Borbón (Nov-2025 referendum only;
# INEC 2026 still parish 080253, no cantonization law found).
# EOL: all LF-only.
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_ec.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/ecuador-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/ecuador-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/ecuador-postal-code-areas.csv'
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
check('areas-246', len(rows) == 246, str(len(rows)))
check('province-24', sum(1 for r in rows if r['type'] == 'province') == 24)
check('canton-222', sum(1 for r in rows if r['type'] == 'canton') == 222)
iso = {'azuay': 'A', 'bolivar': 'B', 'canar': 'F', 'carchi': 'C',
       'chimborazo': 'H', 'cotopaxi': 'X', 'el-oro': 'O', 'esmeraldas': 'E',
       'galapagos': 'W', 'guayas': 'G', 'imbabura': 'I', 'loja': 'L',
       'los-rios': 'R', 'manabi': 'M', 'morona-santiago': 'S', 'napo': 'N',
       'orellana': 'D', 'pastaza': 'Y', 'pichincha': 'P', 'santa-elena': 'SE',
       'santo-domingo-de-los-tsachilas': 'SD', 'sucumbios': 'U',
       'tungurahua': 'T', 'zamora-chinchipe': 'Z'}
ok = all(byid.get(f'ec:province:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-24', ok and len(iso) == 24)
spots = {'ec:canton:banos-de-agua-santa': ('Baños de Agua Santa', 'ec:province:tungurahua'),
         'ec:canton:distrito-metropolitano-de-quito': ('Distrito Metropolitano de Quito', 'ec:province:pichincha'),
         'ec:canton:la-joya-de-los-sachas': ('La Joya de los Sachas', 'ec:province:orellana'),
         'ec:canton:puebloviejo': ('Puebloviejo', 'ec:province:los-rios'),
         'ec:canton:rioverde': ('Rioverde', 'ec:province:esmeraldas'),
         'ec:canton:san-jacinto-de-yaguachi': ('San Jacinto de Yaguachi', 'ec:province:guayas'),
         'ec:canton:san-pedro-de-pelileo': ('San Pedro de Pelileo', 'ec:province:tungurahua'),
         'ec:canton:santiago': ('Santiago', 'ec:province:morona-santiago'),
         'ec:canton:santiago-de-pillaro': ('Santiago de Píllaro', 'ec:province:tungurahua'),
         'ec:canton:santo-domingo': ('Santo Domingo', 'ec:province:santo-domingo-de-los-tsachilas'),
         'ec:canton:veinticuatro-de-mayo': ('Veinticuatro de Mayo', 'ec:province:manabi'),
         'ec:canton:alfredo-baquerizo-moreno': ('Alfredo Baquerizo Moreno', 'ec:province:guayas'),
         'ec:canton:general-antonio-elizalde': ('General Antonio Elizalde', 'ec:province:guayas'),
         'ec:canton:la-concordia': ('La Concordia', 'ec:province:santo-domingo-de-los-tsachilas'),
         'ec:canton:sevilla-don-bosco': ('Sevilla Don Bosco', 'ec:province:morona-santiago')}
for sid, (nm, par) in spots.items():
    r = byid.get(sid, {})
    check(f'spot-{sid}', r.get('name') == nm and r.get('parent_source_id') == par,
          str((r.get('name'), r.get('parent_source_id'))))
for s in ['banos', 'quito', 'joya-de-los-sachas', 'pueblo-viejo', 'rio-verde',
          'yaguachi', 'pelileo', 'santiago-de-mendez', 'pillaro',
          'santo-domingo-de-los-colorados', 'borbon']:
    check(f'gone-{s}', f'ec:canton:{s}' not in byid)
check('codes-1225', len(codes) == 1225, str(len(codes)))
check('links-1225', len(links) == 1225, str(len(links)))
check('codes-EC', all(c['country_code'] == 'EC' for c in codes))
clist = [c['code'] for c in codes]
check('codes-6digit', all(re.match(r'^\d{6}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
check('all-primary', all(l['is_primary'] == 'true' for l in links))
have = Counter(l['postcode'] for l in links)
check('multis-0', all(v == 1 for v in have.values()))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
check('legs-L2-only', all(byid[l['area_source_id']]['level'] == '2' for l in links))
leg = {l['postcode']: l['area_source_id'] for l in links}
anoms = {'080701': 'ec:canton:la-concordia', '080702': 'ec:canton:la-concordia',
         '080703': 'ec:canton:la-concordia', '900001': 'ec:canton:el-triunfo',
         '900002': 'ec:canton:el-carmen', '900003': 'ec:canton:el-carmen',
         '900004': 'ec:canton:cotacachi'}
for pc, sid in anoms.items():
    check(f'anom-{pc}', leg.get(pc) == sid, str(leg.get(pc)))
inec = {'azuay': '01', 'bolivar': '02', 'canar': '03', 'carchi': '04',
        'cotopaxi': '05', 'chimborazo': '06', 'el-oro': '07', 'esmeraldas': '08',
        'guayas': '09', 'imbabura': '10', 'loja': '11', 'los-rios': '12',
        'manabi': '13', 'morona-santiago': '14', 'napo': '15', 'pastaza': '16',
        'pichincha': '17', 'tungurahua': '18', 'zamora-chinchipe': '19',
        'galapagos': '20', 'sucumbios': '21', 'orellana': '22',
        'santo-domingo-de-los-tsachilas': '23', 'santa-elena': '24'}
par = {r['source_id']: r['parent_source_id'].split(':')[-1] for r in rows if r['level'] == '2'}
bad = [pc for pc, sid in leg.items()
       if pc not in anoms and pc[:2] != inec.get(par.get(sid, ''), '??')]
check('prefix-province', not bad, str(bad[:5]))
for sid, n in [('ec:canton:distrito-metropolitano-de-quito', 116),
               ('ec:canton:santo-domingo', 33),
               ('ec:canton:san-pedro-de-pelileo', 6),
               ('ec:canton:san-jacinto-de-yaguachi', 5),
               ('ec:canton:la-joya-de-los-sachas', 4),
               ('ec:canton:santiago-de-pillaro', 3),
               ('ec:canton:puebloviejo', 3),
               ('ec:canton:banos-de-agua-santa', 2),
               ('ec:canton:rioverde', 2),
               ('ec:canton:santiago', 2)]:
    v = sum(1 for l in links if l['area_source_id'] == sid)
    check(f'legcount-{sid}', v == n, str(v))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
