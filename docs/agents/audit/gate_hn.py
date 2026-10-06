import csv, re, sys
from collections import Counter
# Honduras gate. Pins the B17 inline pass (1 rename + 1
# accent fix; 316 areas / 84 codes / 85 legs / 1 multi): 18
# departments ISO 3166-2:HN exact + 298 municipalities
# (dept-aware pairs 298/298 vs es.wiki annex; GN ADM2 298
# full-set vote). FIX-1: San Juan de Flores -> Cantarranas
# (es.wiki annex + es.wiki article + en.wiki lede + OSM
# municipality boundary; GN ADM2 stale, +slug). FIX-2:
# Taulabe -> Taulabé (GN ADM2 + en.wiki + es.wiki).
# KEEPS: Wampusirpi (en.wiki + GN over es.wiki Wampusirpe),
# San José de Colinas (en.wiki + GN, no 'las'), Santiago de
# Puringla (es.wiki over GN dropped-'de'), Ocotepeque muni
# (es.wiki + OSM boundary; Nueva Ocotepeque is the city),
# San Pedro (es.wiki + OSM boundary + en.wiki canonical;
# GN long form outlier), San Miguel Guancapla (es.wiki;
# GN misnames Intibucá 1014), Saba/San Francisco de la Paz/
# Texiguat bare (es.wiki over GN 'Municipio de' prefix).
# Postal: 37 GN codes all bundled with 37/37 dept agreement;
# 47 Mapanet-only kept (18 prefix blocks zero splits, range
# 11101-52102); 12101 dual Comayagua-primary + FM-secondary
# (GN lists Comayagua city + Comayagüela twin); 5 missing
# XX000 bases (11/12/31/33/41) unsupported by GN/Mapanet/
# OSM-sample, correctly absent. EOL: all LF-only.
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_hn.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/honduras-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/honduras-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/honduras-postal-code-areas.csv'
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
check('areas-316', len(rows) == 316, str(len(rows)))
check('department-18', sum(1 for r in rows if r['type'] == 'department') == 18)
check('municipality-298', sum(1 for r in rows if r['type'] == 'municipality') == 298)
check('dept-codes-18', len({r['code'] for r in rows if r['level'] == '1'}) == 18)
spots = {'hn:department:comayagua': ('Comayagua', 'CM'),
         'hn:department:francisco-morazan': ('Francisco Morazán', 'FM'),
         'hn:municipality:cantarranas': ('Cantarranas', ''),
         'hn:municipality:taulabe': ('Taulabé', ''),
         'hn:municipality:wampusirpi': ('Wampusirpi', ''),
         'hn:municipality:san-jose-de-colinas': ('San José de Colinas', ''),
         'hn:municipality:ocotepeque': ('Ocotepeque', ''),
         'hn:municipality:san-pedro': ('San Pedro', ''),
         'hn:municipality:san-miguel-guancapla': ('San Miguel Guancapla', ''),
         'hn:municipality:santiago-de-puringla': ('Santiago de Puringla', '')}
for sid, (nm, cd) in spots.items():
    r = byid.get(sid, {})
    check(f'spot-{sid}', r.get('name') == nm and r.get('code', '') == cd,
          str((r.get('name'), r.get('code'))))
check('sjf-gone', 'hn:municipality:san-juan-de-flores' not in byid)
check('codes-84', len(codes) == 84, str(len(codes)))
check('links-85', len(links) == 85, str(len(links)))
check('codes-HN', all(c['country_code'] == 'HN' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
have = Counter(l['postcode'] for l in links)
multis = sorted(c for c, v in have.items() if v > 1)
check('multis-1', multis == ['12101'], str(multis))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
check('legs-L1-only', all(byid[l['area_source_id']]['level'] == '1' for l in links))
prims = {}
for l in links:
    if l['is_primary'] == 'true':
        prims.setdefault(l['postcode'], []).append(l['area_source_id'])
check('one-primary-per-code', all(len(v) == 1 for v in prims.values()) and len(prims) == 84)
def legs_of(pc):
    return sorted(l['area_source_id'] for l in links if l['postcode'] == pc)
check('12101-dual', legs_of('12101') == ['hn:department:comayagua', 'hn:department:francisco-morazan'],
      str(legs_of('12101')))
check('12101-primary', prims.get('12101') == ['hn:department:comayagua'])
for c in ['11000', '12000', '31000', '33000', '41000']:
    check(f'excluded-{c}', c not in clist)
keeps = {'11101': 'hn:department:francisco-morazan', '12111': 'hn:department:comayagua',
         '15101': 'hn:department:la-paz', '16101': 'hn:department:olancho',
         '21101': 'hn:department:cortes', '23101': 'hn:department:yoro',
         '32301': 'hn:department:colon', '32351': 'hn:department:colon',
         '33100': 'hn:department:gracias-a-dios', '41101': 'hn:department:copan',
         '51201': 'hn:department:choluteca', '13000': 'hn:department:el-paraiso'}
for pc, sid in keeps.items():
    check(f'keep-{pc}', prims.get(pc) == [sid], str(prims.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
