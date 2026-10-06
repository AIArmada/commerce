import csv, os, sys
from collections import Counter
# Angola gate. Pins the B18 inline pass as VERIFY-PLUS-ONE (347
# areas: 21 provinces + 326 municipalities; no postal system):
# 21/21 province names exact vs citypopulation (2024-reform,
# census-2024) + geo-ref census-2024 + PCGN factfile (Law 14/24:
# 21/326/378) + GeoNames ADM1 (21 post-reform rows); 325/326
# municipality names exact vs citypopulation + geo-ref (36
# citypop dual-name displays pair 1:1 with bundle singles) with
# 0 parent mismatches across all three sources; no-postal
# verdict: GeoNames has no AO postal dump (404) + azpostcodes
# "no postal code system" + UPU addressing (codeless B.P.).
# ONE fix: Alto Chipaca->Alto Chicapa (name + slug; citypop
# INE-1106 + geo-ref LSU-01 + GN PPLA2 + ANGOP all Chicapa;
# GN Chipaca/Tchipaca are different places in Cuanza-Sul/
# Malanje; only a 2001 UN typo agrees with Chipaca).
# New-province codes CUA/CUB/IEB/MLE are provisional (PCGN:
# ISO 3166-2:AO = N/A for all four; GN uses its own
# CUB/CBG/ICB/MLE admin1 scheme, not ISO).
# EOL: areas LF-only.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_ao.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/angola-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/angola-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/angola-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
check('no-postal-codes-file', not os.path.exists(C))
check('no-postal-links-file', not os.path.exists(L))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-347', len(rows) == 347, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('province-21', sum(1 for r in rows if r['level'] == '1') == 21)
check('municipality-326', sum(1 for r in rows if r['level'] == '2') == 326)
check('no-cuando-cubango', 'ao:province:cuando-cubango' not in byid)
iso = {'bengo': 'BGO', 'benguela': 'BGU', 'bie': 'BIE', 'cabinda': 'CAB',
       'cuanza-norte': 'CNO', 'cuanza-sul': 'CUS', 'cunene': 'CNN',
       'huambo': 'HUA', 'huila': 'HUI', 'luanda': 'LUA',
       'lunda-norte': 'LNO', 'lunda-sul': 'LSU', 'malanje': 'MAL',
       'moxico': 'MOX', 'namibe': 'NAM', 'uige': 'UIG', 'zaire': 'ZAI'}
ok = all(byid.get(f'ao:province:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-17', ok and len(iso) == 17)
prov = {'cuando': 'CUA', 'cubango': 'CUB', 'icolo-e-bengo': 'IEB',
        'moxico-leste': 'MLE'}
ok = all(byid.get(f'ao:province:{s}', {}).get('code') == c for s, c in prov.items())
check('provisional-4', ok and len(prov) == 4)
counts = {'bengo': 12, 'benguela': 23, 'bie': 19, 'cabinda': 10,
          'cuando': 9, 'cuanza-norte': 17, 'cuanza-sul': 24,
          'cubango': 11, 'cunene': 14, 'huambo': 17, 'huila': 23,
          'icolo-e-bengo': 7, 'luanda': 16, 'lunda-norte': 19,
          'lunda-sul': 14, 'malanje': 27, 'moxico': 12,
          'moxico-leste': 9, 'namibe': 9, 'uige': 23, 'zaire': 11}
have = Counter(r['parent_source_id'] for r in rows if r['level'] == '2')
ok = all(have.get(f'ao:province:{s}') == n for s, n in counts.items())
check('per-province-counts', ok, str({k: have.get(f'ao:province:{k}') for k in counts if have.get(f'ao:province:{k}') != counts[k]}))
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
check('fix-chicapa-name', byid.get('ao:municipality:alto-chicapa', {}).get('name') == 'Alto Chicapa')
check('fix-chicapa-parent', byid.get('ao:municipality:alto-chicapa', {}).get('parent_source_id') == 'ao:province:lunda-sul')
check('fix-chipaca-gone', 'ao:municipality:alto-chipaca' not in byid)
spots = {'ao:municipality:tombwa': ('Tômbwa', 'ao:province:namibe'),
         'ao:municipality:waku-kungo': ('Waku Kungo', 'ao:province:cuanza-sul'),
         'ao:municipality:mbanza-kongo': ('Mbanza Kongo', 'ao:province:zaire'),
         'ao:municipality:nzeto': ('Nzeto', 'ao:province:zaire'),
         'ao:municipality:cangola': ('Cangola', 'ao:province:uige'),
         'ao:municipality:viana': ('Viana', 'ao:province:luanda')}
for sid, (nm, par) in spots.items():
    r = byid.get(sid, {})
    check(f'spot-{sid}', r.get('name') == nm and r.get('parent_source_id') == par,
          str((r.get('name'), r.get('parent_source_id'))))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
