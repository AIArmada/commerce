import csv, sys
from collections import Counter
# Mongolia gate. Pins the B18 pass as FIX-AND-VERIFY (361 areas:
# 21 provinces + 1 capital city + 330 sums + 9 duuregs; 39 postal
# codes / 39 legs): 22/22 L1 codes exact vs ISO 3166-2:MN numerics
# (035-073 + MN-1 single-digit Ulaanbaatar); per-province sum counts
# exact vs the burtgel-cited districts roster + bekkaze catalogue
# (19/14/20/16/4/14/14/15/18/3/17/17/23/15/2/19/17/13/27/19/24);
# postal 39/39 legs correct, each with >=2 signals (Mongol Shuudan
# catalogue + worldpostalcode sum/duureg pages + Mongol Post branch
# directory; OSM on 83120). FOUR fixes, all wiki display-label
# artefacts copied verbatim (GN ADM2 + catalogue + wpc + link target
# each): Eg->Batshireet (vandal pipe), Bayanuur->Bayannuur (Bulgan,
# collision-scoped id), Jargalant (Khovd city)->Jargalant,
# Khovd (sum)->Khovd. Holds: Tsagaannuur (village) kept under
# Bayan-Olgii (GN ADM2; omission elsewhere is postal coverage, not
# non-existence); Bor-Ondor/Khatgal towns correctly excluded;
# L1 transliterations (Arkhangai/Arhangay) are not errors.
# EOL: areas LF-only; postal files CRLF (as shipped).
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_mn.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/mongolia-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/mongolia-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/mongolia-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
for tag, f in (('codes', C), ('legs', L)):
    raw = open(f, 'rb').read()
    check(f'{tag}-CRLF-only', raw.count(b'\r') == raw.count(b'\r\n') == raw.count(b'\n'))
    check(f'{tag}-trailing-CRLF', raw.endswith(b'\r\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-361', len(rows) == 361, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('province-21', sum(1 for r in rows if r['type'] == 'province') == 21)
check('capital-city-1', sum(1 for r in rows if r['type'] == 'capital_city') == 1)
check('sum-330', sum(1 for r in rows if r['type'] == 'sum') == 330)
check('duureg-9', sum(1 for r in rows if r['type'] == 'duureg') == 9)
check('L1-22', sum(1 for r in rows if r['level'] == '1') == 22)
check('L2-339', sum(1 for r in rows if r['level'] == '2') == 339)
iso = {'mn:province:arkhangai': '073', 'mn:province:bayan-olgii': '071',
       'mn:province:bayankhongor': '069', 'mn:province:bulgan': '067',
       'mn:province:darkhan-uul': '037', 'mn:province:dornod': '061',
       'mn:province:dornogovi': '063', 'mn:province:dundgovi': '059',
       'mn:province:govi-altai': '065', 'mn:province:govisumber': '064',
       'mn:province:khentii': '039', 'mn:province:khovd': '043',
       'mn:province:khovsgol': '041', 'mn:province:omnogovi': '053',
       'mn:province:orkhon': '035', 'mn:province:ovorkhangai': '055',
       'mn:province:selenge': '049', 'mn:province:sukhbaatar': '051',
       'mn:province:tov': '047', 'mn:capital_city:ulaanbaatar': '1',
       'mn:province:uvs': '046', 'mn:province:zavkhan': '057'}
ok = all(byid.get(s, {}).get('code') == c for s, c in iso.items())
check('iso-22', ok and len(iso) == 22)
counts = {'mn:province:arkhangai': 19, 'mn:province:bayan-olgii': 14,
          'mn:province:bayankhongor': 20, 'mn:province:bulgan': 16,
          'mn:province:darkhan-uul': 4, 'mn:province:dornod': 14,
          'mn:province:dornogovi': 14, 'mn:province:dundgovi': 15,
          'mn:province:govi-altai': 18, 'mn:province:govisumber': 3,
          'mn:province:khentii': 17, 'mn:province:khovd': 17,
          'mn:province:khovsgol': 23, 'mn:province:omnogovi': 15,
          'mn:province:orkhon': 2, 'mn:province:ovorkhangai': 19,
          'mn:province:selenge': 17, 'mn:province:sukhbaatar': 13,
          'mn:province:tov': 27, 'mn:province:uvs': 19,
          'mn:province:zavkhan': 24, 'mn:capital_city:ulaanbaatar': 9}
have = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
ok = all(have.get(s) == n for s, n in counts.items())
check('per-parent-counts', ok, str({k: have.get(k) for k in counts if have.get(k) != counts[k]}))
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('fix-batshireet', byid.get('mn:sum:batshireet', {}).get('name') == 'Batshireet'
      and byid.get('mn:sum:batshireet', {}).get('parent_source_id') == 'mn:province:khentii')
check('fix-eg-gone', 'mn:sum:eg' not in byid)
check('fix-bulgan-bayannuur', byid.get('mn:sum:bulgan:bayannuur', {}).get('name') == 'Bayannuur'
      and byid.get('mn:sum:bulgan:bayannuur', {}).get('parent_source_id') == 'mn:province:bulgan')
check('fix-bayanuur-gone', 'mn:sum:bayanuur' not in byid)
check('fix-khovd-jargalant', byid.get('mn:sum:khovd:jargalant', {}).get('name') == 'Jargalant'
      and byid.get('mn:sum:khovd:jargalant', {}).get('parent_source_id') == 'mn:province:khovd')
check('fix-jargalant-city-gone', 'mn:sum:jargalant-khovd-city' not in byid)
check('fix-khovd-khovd', byid.get('mn:sum:khovd:khovd', {}).get('name') == 'Khovd'
      and byid.get('mn:sum:khovd:khovd', {}).get('parent_source_id') == 'mn:province:khovd')
check('fix-khovd-sum-gone', 'mn:sum:khovd-sum' not in byid)
check('hold-tsagaannuur-village', byid.get('mn:sum:tsagaannuur-village', {}).get('name') == 'Tsagaannuur (village)'
      and byid.get('mn:sum:tsagaannuur-village', {}).get('parent_source_id') == 'mn:province:bayan-olgii')
check('hold-bayannuur-pair', byid.get('mn:sum:bayannuur', {}).get('parent_source_id') == 'mn:province:bayan-olgii'
      and byid.get('mn:sum:bulgan:bayannuur', {}).get('parent_source_id') == 'mn:province:bulgan')
check('hold-bare-khovd-is-uvs', byid.get('mn:sum:khovd', {}).get('parent_source_id') == 'mn:province:uvs')
check('hold-jargalant-6x', sum(1 for r in rows if r['name'] == 'Jargalant') == 6)
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-39', len(codes) == 39, str(len(codes)))
check('legs-39', len(legs) == 39, str(len(legs)))
check('codes-5-digit', all(len(r['code']) == 5 and r['code'].isdigit() for r in codes))
check('codes-country-MN', all(r['country_code'] == 'MN' for r in codes))
check('codes-unique', len({r['code'] for r in codes}) == 39)
check('legs-set-equal', {r['postcode'] for r in legs} == {r['code'] for r in codes})
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
check('legs-served-primary', all(r['relationship_type'] == 'served_by' and r['is_primary'] == 'true' for r in legs))
check('one-primary-per-code', Counter(r['postcode'] for r in legs if r['is_primary'] == 'true').most_common(1)[0][1] == 1)
ub = {'13260': 'mn:duureg:ulaanbaatar:bayanzurkh', '13343': 'mn:duureg:ulaanbaatar:bayanzurkh',
      '13370': 'mn:duureg:ulaanbaatar:bayanzurkh', '14192': 'mn:duureg:ulaanbaatar:sukhbaatar',
      '14201': 'mn:duureg:ulaanbaatar:sukhbaatar', '14250': 'mn:duureg:ulaanbaatar:sukhbaatar',
      '15110': 'mn:duureg:chingeltei', '15141': 'mn:duureg:chingeltei',
      '15160': 'mn:duureg:chingeltei', '16052': 'mn:duureg:ulaanbaatar:bayangol',
      '16092': 'mn:duureg:ulaanbaatar:bayangol', '17011': 'mn:duureg:khan-uul',
      '17031': 'mn:duureg:khan-uul', '17032': 'mn:duureg:khan-uul',
      '17110': 'mn:duureg:khan-uul', '18010': 'mn:duureg:songino-khairkhan',
      '18080': 'mn:duureg:songino-khairkhan'}
aimag = {'21063': 'mn:province:dornod', '22075': 'mn:province:sukhbaatar',
         '23123': 'mn:province:khentii', '41100': 'mn:province:tov',
         '41111': 'mn:province:tov', '42019': 'mn:province:govisumber',
         '43081': 'mn:province:selenge', '44103': 'mn:province:dornogovi',
         '45045': 'mn:province:darkhan-uul', '46087': 'mn:province:omnogovi',
         '48101': 'mn:province:dundgovi', '61029': 'mn:province:orkhon',
         '62166': 'mn:province:ovorkhangai', '63082': 'mn:province:bulgan',
         '64101': 'mn:province:bayankhongor', '65141': 'mn:province:arkhangai',
         '67123': 'mn:province:khovsgol', '81095': 'mn:province:zavkhan',
         '82093': 'mn:province:govi-altai', '83120': 'mn:province:bayan-olgii',
         '84141': 'mn:province:khovd', '85165': 'mn:province:uvs'}
legmap = {r['postcode']: r['area_source_id'] for r in legs}
check('ub-block-17', all(legmap.get(p) == s for p, s in ub.items()) and len(ub) == 17,
      str({p: legmap.get(p) for p in ub if legmap.get(p) != ub[p]}))
check('aimag-block-22', all(legmap.get(p) == s for p, s in aimag.items()) and len(aimag) == 22,
      str({p: legmap.get(p) for p in aimag if legmap.get(p) != aimag[p]}))
linked = {r['area_source_id'] for r in legs}
check('unlinked-duureg-3', linked.isdisjoint({'mn:duureg:baganuur', 'mn:duureg:bagakhangai', 'mn:duureg:nalaikh'})
      and sum(1 for s in linked if s.startswith('mn:duureg')) == 6)
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
