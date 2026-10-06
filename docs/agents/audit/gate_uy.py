import csv, sys
from collections import Counter, defaultdict
# Uruguay gate. Pins the B14 fix pass: tree 125 -> 136 municipalities
# (11 adds: Ansina 2015-batch catch-up + 10 2025 creations; 8x
# Municipio A-G Spanish relabels + Miguelete -> Colonia Miguelete),
# postal 8 primary flips + 11 drops + 14 adds on the exact 124-code set.
# Codes 124, legs 339 -> 342, multis 88 -> 88 (15800 + 12800 collapse
# to singles, 12400 + 34100 go dual).
# Oct-2026 open-log #15 retry: 5 flips + 7 drops + 16 adds (30 holds
# adjudicated, 27 resolved). Legs 342 -> 351, multis 88 -> 93 (11300,
# 11600, 11700, 11900, 12100 go multi; no collapses). 3 holds stay:
# H7 tupambae, H13-SJ san-jacinto, H27-carmelo carmelo.
# Oracles: OPP Mapa Municipios 2025 (official presidency map, 136 boxes
# transcribed + box-counted), ES wiki Municipios tables (136), Medios
# Publicos 11-nuevos article, Intendencia rosters (Canelones 32,
# Paysandu alcalde cards), Correo Uruguayo listadoCP (official 1943-row
# table, 124 codes set-equal), GeoNames UY.zip (1964 rows, 122 codes;
# 20100 + 27500 Correo-official GN-stale), ES Anexo Barrios-Montevideo
# CCZ table + Villa Colon / Conciliacion infoboxes (Municipio G),
# UPU uryEn addressed anchors, OSM Nominatim (Las Canas 37000).
# Integrator notes: 34100 was a singleton pre-fix (Zapican add makes it
# dual); 12400 G-vote rests on Anexo + Villa-Colon-G + Conciliacion-G
# (CCZ13 corroboration); 12500 is G-single (Lezica A-split secondary
# would be single-signal, held); 27 of 30 verdict HOLDs resolved, 3 stay.
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_uy.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/uruguay-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/uruguay-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/uruguay-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
MULTIS = {
    "11300": ("uy:municipality:municipality-ch", 2, ['uy:municipality:municipality-b']),
    "11400": ("uy:municipality:municipality-e", 3, ['uy:municipality:municipality-f', 'uy:municipality:municipality-d']),
    "11600": ("uy:municipality:municipality-ch", 3, ['uy:municipality:municipality-e', 'uy:municipality:municipality-c']),
    "11700": ("uy:municipality:municipality-c", 3, ['uy:municipality:municipality-d', 'uy:municipality:municipality-a']),
    "11800": ("uy:municipality:municipality-c", 3, ['uy:municipality:municipality-b', 'uy:municipality:municipality-ch']),
    "11900": ("uy:municipality:municipality-a", 2, ['uy:municipality:municipality-g']),
    "12000": ("uy:municipality:municipality-d", 3, ['uy:municipality:municipality-f', 'uy:municipality:municipality-c']),
    "12100": ("uy:municipality:municipality-f", 2, ['uy:municipality:municipality-e']),
    "12300": ("uy:municipality:municipality-d", 2, ['uy:municipality:municipality-f']),
    "12400": ("uy:municipality:municipality-g", 2, ['uy:municipality:municipality-d']),
    "15000": ("uy:municipality:ciudad-de-la-costa", 3, ['uy:municipality:colonia-nicolich', 'uy:municipality:paso-carrasco']),
    "15100": ("uy:municipality:salinas", 2, ['uy:municipality:pando']),
    "15200": ("uy:municipality:atlantida", 2, ['uy:municipality:parque-del-plata']),
    "15300": ("uy:municipality:la-floresta", 2, ['uy:municipality:parque-del-plata']),
    "15400": ("uy:municipality:la-floresta", 2, ['uy:municipality:soca']),
    "15500": ("uy:municipality:pando", 4, ['uy:municipality:barros-blancos', 'uy:municipality:colonia-nicolich', 'uy:municipality:suarez']),
    "15600": ("uy:municipality:pando", 7, ['uy:department:canelones', 'uy:municipality:atlantida', 'uy:municipality:empalme-olmos', 'uy:municipality:salinas', 'uy:municipality:soca', 'uy:municipality:suarez']),
    "15700": ("uy:municipality:toledo", 5, ['uy:department:canelones', 'uy:municipality:del-andaluz', 'uy:municipality:sauce', 'uy:municipality:suarez']),
    "15900": ("uy:municipality:las-piedras", 7, ['uy:municipality:18-de-mayo', 'uy:municipality:canelones', 'uy:municipality:la-paz', 'uy:municipality:los-cerrillos', 'uy:municipality:progreso', 'uy:municipality:sauce']),
    "20000": ("uy:municipality:maldonado", 6, ['uy:municipality:garzon', 'uy:municipality:pan-de-azucar', 'uy:municipality:piriapolis', 'uy:municipality:punta-del-este', 'uy:municipality:san-carlos']),
    "20200": ("uy:municipality:piriapolis", 3, ['uy:department:maldonado', 'uy:municipality:solis-grande']),
    "20300": ("uy:municipality:pan-de-azucar", 2, ['uy:municipality:solis-grande']),
    "20400": ("uy:municipality:san-carlos", 3, ['uy:municipality:garzon', 'uy:municipality:maldonado']),
    "20500": ("uy:municipality:aigua", 3, ['uy:department:lavalleja', 'uy:department:rocha']),
    "27000": ("uy:department:rocha", 2, ['uy:municipality:la-paloma']),
    "27100": ("uy:department:rocha", 2, ['uy:municipality:chuy']),
    "27200": ("uy:municipality:castillos", 2, ['uy:department:rocha']),
    "27300": ("uy:municipality:lascano", 2, ['uy:department:rocha']),
    "27400": ("uy:municipality:la-paloma", 3, ['uy:department:rocha', 'uy:municipality:castillos']),
    "27500": ("uy:department:rocha", 2, ['uy:municipality:lascano']),
    "30000": ("uy:department:lavalleja", 3, ['uy:municipality:mariscala', 'uy:municipality:piraraja']),
    "30100": ("uy:municipality:solis-de-mataojo", 5, ['uy:department:canelones', 'uy:department:lavalleja', 'uy:municipality:solis-grande', 'uy:municipality:soca']),
    "30300": ("uy:department:lavalleja", 3, ['uy:department:treinta-y-tres', 'uy:municipality:jose-pedro-varela']),
    "31100": ("uy:department:treinta-y-tres", 2, ['uy:municipality:vergara']),
    "33000": ("uy:department:treinta-y-tres", 4, ['uy:municipality:enrique-martinez', 'uy:municipality:rincon', 'uy:municipality:villa-sara']),
    "34100": ("uy:department:lavalleja", 2, ['uy:municipality:zapican']),
    "34200": ("uy:department:florida", 3, ['uy:department:lavalleja', 'uy:municipality:jose-batlle-y-ordonez']),
    "35100": ("uy:department:treinta-y-tres", 2, ['uy:department:florida']),
    "35200": ("uy:municipality:cerro-chato", 3, ['uy:department:florida', 'uy:department:treinta-y-tres']),
    "35300": ("uy:department:cerro-largo", 3, ['uy:municipality:arevalo', 'uy:municipality:santa-clara-de-olimar']),
    "36200": ("uy:municipality:fraile-muerto", 5, ['uy:department:cerro-largo', 'uy:municipality:cerro-de-las-cuentas', 'uy:municipality:quebracho', 'uy:municipality:tres-islas']),
    "37000": ("uy:department:cerro-largo", 9, ['uy:municipality:acegua', 'uy:municipality:arbolito', 'uy:municipality:banado-de-medina', 'uy:municipality:centurion', 'uy:municipality:isidoro-noblia', 'uy:municipality:las-canas', 'uy:municipality:ramon-trigo', 'uy:municipality:tupambae']),
    "37100": ("uy:department:cerro-largo", 5, ['uy:department:treinta-y-tres', 'uy:municipality:laguna-merin', 'uy:municipality:placido-rosas', 'uy:municipality:rio-branco']),
    "40100": ("uy:department:rivera", 2, ['uy:municipality:tranqueras']),
    "40200": ("uy:department:artigas", 2, ['uy:municipality:tranqueras']),
    "41100": ("uy:department:rivera", 3, ['uy:department:tacuarembo', 'uy:municipality:minas-de-corrales']),
    "41200": ("uy:department:rivera", 2, ['uy:municipality:vichadero']),
    "45000": ("uy:department:tacuarembo", 6, ['uy:department:cerro-largo', 'uy:department:rivera', 'uy:municipality:ansina', 'uy:municipality:tambores', 'uy:municipality:villa-caraguata']),
    "45100": ("uy:department:rio-negro", 4, ['uy:department:durazno', 'uy:department:tacuarembo', 'uy:municipality:paso-de-los-toros']),
    "45200": ("uy:department:tacuarembo", 2, ['uy:municipality:san-gregorio-de-polanco']),
    "50000": ("uy:department:salto", 8, ['uy:department:paysandu', 'uy:municipality:chapicuy', 'uy:municipality:colonia-lavalleja', 'uy:municipality:mataojo', 'uy:municipality:rincon-de-valentin', 'uy:municipality:salto:san-antonio', 'uy:municipality:villa-constitucion']),
    "50200": ("uy:municipality:belen", 2, ['uy:department:artigas']),
    "55000": ("uy:department:artigas", 2, ['uy:municipality:baltasar-brum']),
    "55100": ("uy:municipality:bella-union", 3, ['uy:department:artigas', 'uy:municipality:tomas-gomensoro']),
    "60000": ("uy:department:paysandu", 11, ['uy:department:rio-negro', 'uy:municipality:el-eucalipto', 'uy:municipality:guichon', 'uy:municipality:lorenzo-geyres', 'uy:municipality:paysandu:cerro-chato', 'uy:municipality:paysandu:quebracho', 'uy:municipality:piedras-coloradas', 'uy:municipality:porvenir', 'uy:municipality:san-javier', 'uy:municipality:young']),
    "60100": ("uy:municipality:guichon", 3, ['uy:department:paysandu', 'uy:department:rio-negro']),
    "60200": ("uy:municipality:paysandu:quebracho", 3, ['uy:municipality:chapicuy', 'uy:municipality:paysandu:cerro-chato']),
    "65000": ("uy:department:rio-negro", 2, ['uy:municipality:nuevo-berlin']),
    "65100": ("uy:department:rio-negro", 3, ['uy:department:durazno', 'uy:municipality:young']),
    "70000": ("uy:department:colonia", 3, ['uy:municipality:carmelo', 'uy:municipality:conchillas']),
    "70100": ("uy:municipality:carmelo", 4, ['uy:department:colonia', 'uy:department:soriano', 'uy:municipality:nueva-palmira']),
    "70200": ("uy:department:colonia", 4, ['uy:municipality:colonia:la-paz', 'uy:municipality:rosario', 'uy:municipality:tarariras']),
    "70300": ("uy:department:colonia", 5, ['uy:municipality:colonia-valdense', 'uy:municipality:cufre', 'uy:municipality:nueva-helvecia', 'uy:municipality:rosario']),
    "70500": ("uy:municipality:juan-l-lacaze", 2, ['uy:department:colonia']),
    "70600": ("uy:municipality:tarariras", 2, ['uy:department:colonia']),
    "70700": ("uy:department:colonia", 3, ['uy:department:soriano', 'uy:municipality:nueva-palmira']),
    "70800": ("uy:department:colonia", 8, ['uy:municipality:carmelo', 'uy:municipality:conchillas', 'uy:municipality:florencio-sanchez', 'uy:municipality:miguelete', 'uy:municipality:ombues-de-lavalle', 'uy:municipality:rosario', 'uy:municipality:tarariras']),
    "75000": ("uy:department:soriano", 2, ['uy:department:rio-negro']),
    "75100": ("uy:department:soriano", 3, ['uy:municipality:dolores', 'uy:municipality:villa-soriano']),
    "75200": ("uy:department:soriano", 5, ['uy:department:colonia', 'uy:municipality:cardona', 'uy:municipality:florencio-sanchez', 'uy:municipality:jose-enrique-rodo']),
    "75400": ("uy:department:soriano", 2, ['uy:municipality:jose-enrique-rodo']),
    "75500": ("uy:department:soriano", 2, ['uy:municipality:palmitas']),
    "80000": ("uy:department:san-jose", 4, ['uy:municipality:ciudad-del-plata', 'uy:municipality:ecilda-paullier', 'uy:municipality:rodriguez']),
    "80100": ("uy:municipality:libertad", 3, ['uy:department:san-jose', 'uy:municipality:rodriguez']),
    "80400": ("uy:municipality:rodriguez", 2, ['uy:municipality:ciudad-del-plata']),
    "80500": ("uy:municipality:ciudad-del-plata", 2, ['uy:department:san-jose']),
    "85000": ("uy:department:flores", 3, ['uy:department:san-jose', 'uy:municipality:ismael-cortinas']),
    "90000": ("uy:municipality:canelones", 9, ['uy:department:canelones', 'uy:municipality:aguas-corrientes', 'uy:municipality:juanico', 'uy:municipality:los-cerrillos', 'uy:municipality:santa-lucia', 'uy:municipality:santa-rosa', 'uy:municipality:sauce', 'uy:municipality:suarez']),
    "90100": ("uy:municipality:los-cerrillos", 2, ['uy:department:canelones']),
    "90200": ("uy:municipality:santa-lucia", 5, ['uy:department:canelones', 'uy:department:san-jose', 'uy:municipality:aguas-corrientes', 'uy:municipality:rodriguez']),
    "91100": ("uy:municipality:san-ramon", 3, ['uy:department:canelones', 'uy:municipality:tala']),
    "91200": ("uy:municipality:san-bautista", 3, ['uy:department:canelones', 'uy:municipality:san-jacinto']),
    "91400": ("uy:municipality:santa-rosa", 2, ['uy:department:canelones']),
    "91500": ("uy:municipality:sauce", 6, ['uy:department:canelones', 'uy:municipality:pando', 'uy:municipality:san-jacinto', 'uy:municipality:santa-rosa', 'uy:municipality:toledo']),
    "91600": ("uy:municipality:san-jacinto", 5, ['uy:department:canelones', 'uy:municipality:empalme-olmos', 'uy:municipality:san-bautista', 'uy:municipality:sauce']),
    "91700": ("uy:municipality:migues", 5, ['uy:department:lavalleja', 'uy:municipality:montes', 'uy:municipality:san-jacinto', 'uy:municipality:soca']),
    "91800": ("uy:municipality:tala", 2, ['uy:department:canelones']),
    "94100": ("uy:department:florida", 3, ['uy:department:flores', 'uy:municipality:sarandi-grande']),
    "96100": ("uy:department:florida", 2, ['uy:municipality:fray-marcos']),
    "96200": ("uy:municipality:fray-marcos", 4, ['uy:department:canelones', 'uy:department:florida', 'uy:municipality:tala']),
    "96300": ("uy:municipality:casupa", 4, ['uy:department:florida', 'uy:department:lavalleja', 'uy:municipality:fray-marcos']),
    "97000": ("uy:department:durazno", 6, ['uy:department:cerro-largo', 'uy:department:flores', 'uy:department:florida', 'uy:department:rio-negro', 'uy:municipality:villa-del-carmen']),
    "98000": ("uy:department:florida", 4, ['uy:department:durazno', 'uy:municipality:sarandi-del-yi', 'uy:municipality:villa-del-carmen']),
}
SINGLES = {
    "11000": "uy:municipality:municipality-b",
    "11100": "uy:municipality:municipality-b",
    "11200": "uy:municipality:municipality-b",
    "11500": "uy:municipality:municipality-e",
    "12500": "uy:municipality:municipality-g",
    "12700": "uy:municipality:municipality-a",
    "12800": "uy:municipality:municipality-a",
    "12900": "uy:municipality:municipality-g",
    "13000": "uy:municipality:municipality-f",
    "15800": "uy:municipality:ciudad-de-la-costa",
    "20100": "uy:municipality:punta-del-este",
    "36100": "uy:municipality:tupambae",
    "40000": "uy:department:rivera",
    "50100": "uy:municipality:villa-constitucion",
    "60300": "uy:municipality:piedras-coloradas",
    "60400": "uy:department:rio-negro",
    "61100": "uy:municipality:san-javier",
    "70400": "uy:municipality:colonia-valdense",
    "75300": "uy:department:soriano",
    "80200": "uy:department:san-jose",
    "80300": "uy:municipality:ecilda-paullier",
    "91300": "uy:municipality:san-antonio",
    "94000": "uy:department:florida",
    "95100": "uy:department:florida",
    "95200": "uy:department:florida",
    "95300": "uy:department:florida",
    "95400": "uy:department:florida",
    "95500": "uy:department:florida",
    "95600": "uy:department:florida",
    "96400": "uy:department:florida",
    "96500": "uy:department:florida",
}
PINS_L1 = {
    "uy:department:artigas": ("Artigas", "AR"), "uy:department:canelones": ("Canelones", "CA"),
    "uy:department:cerro-largo": ("Cerro Largo", "CL"), "uy:department:colonia": ("Colonia", "CO"),
    "uy:department:durazno": ("Durazno", "DU"), "uy:department:flores": ("Flores", "FS"),
    "uy:department:florida": ("Florida", "FD"), "uy:department:lavalleja": ("Lavalleja", "LA"),
    "uy:department:maldonado": ("Maldonado", "MA"), "uy:department:montevideo": ("Montevideo", "MO"),
    "uy:department:paysandu": ("Paysandú", "PA"), "uy:department:rio-negro": ("Río Negro", "RN"),
    "uy:department:rivera": ("Rivera", "RV"), "uy:department:rocha": ("Rocha", "RO"),
    "uy:department:salto": ("Salto", "SA"), "uy:department:san-jose": ("San José", "SJ"),
    "uy:department:soriano": ("Soriano", "SO"), "uy:department:tacuarembo": ("Tacuarembó", "TA"),
    "uy:department:treinta-y-tres": ("Treinta y Tres", "TT"),
}
PINS_L2_NEW = [
    ("uy:municipality:ansina", "Ansina", "uy:department:tacuarembo"),
    ("uy:municipality:del-andaluz", "Del Andaluz", "uy:department:canelones"),
    ("uy:municipality:juanico", "Juanicó", "uy:department:canelones"),
    ("uy:municipality:conchillas", "Conchillas", "uy:department:colonia"),
    ("uy:municipality:cufre", "Cufré", "uy:department:colonia"),
    ("uy:municipality:piraraja", "Pirarajá", "uy:department:lavalleja"),
    ("uy:municipality:zapican", "Zapicán", "uy:department:lavalleja"),
    ("uy:municipality:paysandu:cerro-chato", "Cerro Chato", "uy:department:paysandu"),
    ("uy:municipality:el-eucalipto", "El Eucalipto", "uy:department:paysandu"),
    ("uy:municipality:villa-soriano", "Villa Soriano", "uy:department:soriano"),
    ("uy:municipality:villa-caraguata", "Villa Caraguatá", "uy:department:tacuarembo"),
]
PINS_L2_RENAMED = [
    ("uy:municipality:municipality-a", "Municipio A"), ("uy:municipality:municipality-b", "Municipio B"),
    ("uy:municipality:municipality-c", "Municipio C"), ("uy:municipality:municipality-ch", "Municipio CH"),
    ("uy:municipality:municipality-d", "Municipio D"), ("uy:municipality:municipality-e", "Municipio E"),
    ("uy:municipality:municipality-f", "Municipio F"), ("uy:municipality:municipality-g", "Municipio G"),
    ("uy:municipality:miguelete", "Colonia Miguelete"),
]
L2_PER_DEPT = {"uy:department:artigas": 3, "uy:department:canelones": 32, "uy:department:cerro-largo": 16,
    "uy:department:colonia": 13, "uy:department:durazno": 2, "uy:department:flores": 1, "uy:department:florida": 3,
    "uy:department:lavalleja": 6, "uy:department:maldonado": 8, "uy:department:montevideo": 8,
    "uy:department:paysandu": 9, "uy:department:rio-negro": 3, "uy:department:rivera": 3, "uy:department:rocha": 4,
    "uy:department:salto": 6, "uy:department:san-jose": 4, "uy:department:soriano": 5,
    "uy:department:tacuarembo": 4, "uy:department:treinta-y-tres": 6}
ANCHORS = {"11600": "uy:municipality:municipality-ch", "12900": "uy:municipality:municipality-g",
    "15400": "uy:municipality:la-floresta", "15600": "uy:municipality:pando",
    "70200": "uy:department:colonia", "75000": "uy:department:soriano",
    "80300": "uy:municipality:ecilda-paullier", "90000": "uy:municipality:canelones"}
HOLDS_ABSENT_LEGS = [("45000", "uy:department:paysandu"),
    ("91200", "uy:municipality:santa-rosa"),
    ("40000", "uy:municipality:minas-de-corrales")]
HOLDS_ABSENT_PRIMARY = {"12100": "uy:municipality:municipality-f", "12000": "uy:municipality:municipality-d",
    "40200": "uy:department:artigas", "34200": "uy:department:florida",
    "45100": "uy:department:rio-negro", "98000": "uy:department:florida",
    "15500": "uy:municipality:pando", "35200": "uy:municipality:cerro-chato",
    "60200": "uy:municipality:paysandu:quebracho"}
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') and raw_c.count(b'\n') > 0)
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') and raw_l.count(b'\n') > 0)
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-155', len(rows) == 155, str(len(rows)))
check('l1-19', sum(1 for r in rows if r['level'] == '1') == 19)
check('l2-136', sum(1 for r in rows if r['level'] == '2') == 136)
for sid, (nm, cd) in PINS_L1.items():
    r = byid.get(sid)
    check(f'l1-{cd}', bool(r) and r['name'] == nm and r['code'] == cd
          and r['level'] == '1' and r['parent_source_id'] == '', str(r))
for sid, nm, par in PINS_L2_NEW:
    r = byid.get(sid)
    check(f'new-{sid.split(":")[-1]}', bool(r) and r['name'] == nm
          and r['parent_source_id'] == par and r['level'] == '2', str(r))
for sid, nm in PINS_L2_RENAMED:
    r = byid.get(sid)
    check(f'rename-{sid.split(":")[-1]}', bool(r) and r['name'] == nm, str(r))
got = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
for par, n in L2_PER_DEPT.items():
    check(f'l2-{par.split(":")[-1]}-{n}', got.get(par, 0) == n, str(got.get(par, 0)))
check('codes-124', len(codes) == 124, str(len(codes)))
check('legs-351', len(links) == 351, str(len(links)))
bycode = defaultdict(list)
for r in links:
    bycode[r['postcode']].append(r)
check('distinct-124', len(bycode) == 124, str(len(bycode)))
multis = sorted(k for k, v in bycode.items() if len(v) > 1)
check('multis-93', len(multis) == 93, str(len(multis)))
badprim = [c for c, legs in bycode.items()
           if sum(1 for r in legs if r['is_primary'] == 'true') != 1]
check('one-primary-each', not badprim, str(badprim[:5]))
for pc, (prim, n, secs) in MULTIS.items():
    legs = bycode.get(pc, [])
    got_p = [r['area_source_id'] for r in legs if r['is_primary'] == 'true']
    got_s = sorted(r['area_source_id'] for r in legs if r['is_primary'] != 'true')
    check(f'multi-{pc}', len(legs) == n and got_p == [prim] and got_s == sorted(secs),
          f'n={len(legs)}/{n} prim={got_p}')
for pc, exp in SINGLES.items():
    legs = bycode.get(pc, [])
    check(f'single-{pc}', len(legs) == 1 and legs[0]['area_source_id'] == exp
          and legs[0]['is_primary'] == 'true', str(legs))
dangling = [l['postcode'] for l in links if l['area_source_id'] not in byid]
check('no-dangling-legs', not dangling, str(dangling[:5]))
for pc, exp_prim in ANCHORS.items():
    legs = bycode.get(pc, [])
    got_p = [r['area_source_id'] for r in legs if r['is_primary'] == 'true']
    check(f'anchor-{pc}', got_p == [exp_prim], str(got_p))
have = {(l['postcode'], l['area_source_id']) for l in links}
for pc, ar in HOLDS_ABSENT_LEGS:
    check(f'hold-absent-{pc}-{ar.split(":")[-1]}', (pc, ar) not in have)
for pc, exp_prim in HOLDS_ABSENT_PRIMARY.items():
    legs = bycode.get(pc, [])
    got_p = [r['area_source_id'] for r in legs if r['is_primary'] == 'true']
    check(f'hold-primary-{pc}', got_p == [exp_prim], str(got_p))
for pc, ar in [("55000", "uy:municipality:barros-blancos"), ("15000", "uy:municipality:toledo"),
    ("15300", "uy:municipality:empalme-olmos"), ("15800", "uy:municipality:colonia-nicolich"),
    ("15800", "uy:municipality:pando"), ("15900", "uy:municipality:empalme-olmos"),
    ("37100", "uy:municipality:las-canas"), ("12800", "uy:municipality:municipality-d"),
    ("91500", "uy:municipality:ciudad-de-la-costa"), ("91500", "uy:municipality:empalme-olmos"),
    ("12500", "uy:municipality:municipality-d"),
    ("12000", "uy:municipality:municipality-e"), ("20500", "uy:municipality:lascano"),
    ("20500", "uy:department:maldonado"), ("33000", "uy:municipality:vergara"),
    ("50000", "uy:municipality:belen"), ("70000", "uy:municipality:tarariras"),
    ("60200", "uy:department:paysandu")]:
    check(f'drop-{pc}-{ar.split(":")[-1]}', (pc, ar) not in have)
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
