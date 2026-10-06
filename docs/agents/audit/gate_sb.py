import csv, sys
from collections import Counter
# Solomon Islands gate. Pins the B16 inline verify-only pass
# (zero changes; 193 areas: 10 L1 + 183 L2 wards; no postcode
# system, no postal files): L1 = 9 provinces + Honiara capital
# territory with ISO 3166-2:SB codes CE/CH/GU/CT/IS/MK/ML/RB/
# TE/WE. Wards are byte-identical (names + SINSO pcodes +
# parents) to the OCHA COD-AB slb_admbnda_adm3 SINSO census
# geography; per-province counts match citypopulation
# (Central 13, Choiseul 14, Guadalcanal 22 + Honiara 12,
# Isabel 16, Makira-Ulawa 20, Malaita 33, Rennell and Bellona
# 10, Temotu 17, Western 26; citypop groups Honiara wards
# under Guadalcanal presentationally, COD-AB ADM1 SB10
# confirms the Honiara parent). Holds: ~20 citypop/statoids
# spelling variants (Banika, Tepazaka, Baolo, Fataleka,
# Gaongau, Tenggano/Tenggno, Kanava/Kanara, Gangoto/Gantogo,
# Mbuini/Mbuin, Santa Anna, Wagina, etc.) — official SINSO
# spellings kept; directories disagree with official AND with
# each other, and the 2019 census Vol 2 ward list is
# unlocated (Vol 1 only, Pacific Data Hub Cloudflare-walled).
# The West Baegu NBSP + 'Fatale' string is verbatim SINSO
# (SB0707190705), kept deliberately. Asserts POST-fix state;
# run from repo root: python3 docs/agents/audit/gate_sb.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/solomon-islands-address-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-193', len(rows) == 193, str(len(rows)))
check('l1-10', sum(1 for r in rows if r['level'] == '1') == 10)
check('l1-province-9', sum(1 for r in rows if r['level'] == '1' and r['type'] == 'province') == 9)
check('l1-ct-1', sum(1 for r in rows if r['level'] == '1' and r['type'] == 'capital_territory') == 1)
check('wards-183', sum(1 for r in rows if r['type'] == 'ward') == 183)
iso = {'CE': 'Central', 'CH': 'Choiseul', 'GU': 'Guadalcanal', 'CT': 'Honiara', 'IS': 'Isabel',
       'MK': 'Makira-Ulawa', 'ML': 'Malaita', 'RB': 'Rennell and Bellona', 'TE': 'Temotu', 'WE': 'Western'}
got = {r['code']: r['name'] for r in rows if r['level'] == '1'}
check('iso-l1', got == iso, str({k: got.get(k) for k in iso if got.get(k) != iso[k]}))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
counts = Counter(byid[r['parent_source_id']]['code'] for r in rows if r['level'] == '2')
want = {'CE': 13, 'CH': 14, 'GU': 22, 'CT': 12, 'IS': 16, 'MK': 20, 'ML': 33, 'RB': 10, 'TE': 17, 'WE': 26}
check('per-province-counts', dict(counts) == want, str(dict(counts)))
# SINSO pcode spot pins (COD-AB ADM3_PCODE verbatim)
spots = {'sb:ward:nggosi': ('Nggosi', 'SB1010431001'), 'sb:ward:rove-lengakiki': ('Rove - Lengakiki', None),
         'sb:ward:vonunu': ('Vonunu', None), 'sb:ward:mbanika': ('Mbanika', None),
         'sb:ward:waghina': ('Waghina', None), 'sb:ward:havulei': ('Havulei', None)}
for sid, (nm, pc) in spots.items():
    r = byid.get(sid, {})
    ok = r.get('name') == nm and (pc is None or r.get('code') == pc)
    check(f'spot-{sid}', ok, str((r.get('name'), r.get('code'))))
# Held SINSO spellings (verbatim official, directories differ)
holds = {'sb:ward:tepakaza': 'Tepakaza', 'sb:ward:hovukoilo': 'Hovukoilo', 'sb:ward:tirotonga': 'Tirotonga',
         'sb:ward:gulalafou': 'Gulalafou', 'sb:ward:bilua': 'Bilua', 'sb:ward:dovele': 'Dovele',
         'sb:ward:iringgila': 'Iringgila', 'sb:ward:kangava': 'Kangava',
         'sb:ward:mugihenua': 'Mugihenua', 'sb:ward:tetau-nangoto': 'Tetau Nangoto'}
for sid, nm in holds.items():
    check(f'hold-{sid}', byid.get(sid, {}).get('name') == nm, str(byid.get(sid, {}).get('name')))
nbsp = byid.get('sb:ward:west-baegu-fatale', {})
check('nbsp-verbatim', nbsp.get('name') == 'West Baegu \xa0- Fatale', repr(nbsp.get('name')))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
