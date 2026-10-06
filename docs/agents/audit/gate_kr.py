import csv, re, sys
from collections import Counter
# South Korea gate. Pins the B17 worker pass as VERIFIED-STALE:
# the bundle is a correct pre-2026-07-01 snapshot (245 areas /
# 34249 codes / 34249 legs / 0 multis) and two effective
# 2026-07-01 reorganizations are HELD, not applied (see below).
# Tree: 17 L1 ISO 3166-2:KR exact (bundle ahead of ISO on Jeonbuk
# State per Jan-2024 law) + 228 L2 with Gunwi->Daegu (2023),
# Michuhol rename, Gangwon/Jeonbuk special status all correct.
# Postal: bundle set == GN Oct-2026 set exactly (34249/34249);
# 452/452 p3 blocks agree with the en.wiki postal table; 29/29
# Nominatim hits agree; Sejong single-tier 142-code block
# 30000-30154; Incheon old blocks Jung 223-224 (109) + Dong 225
# (76) + Seo 226-228 (256).
# HOLD H-STRUCT-1 (Jeonnam-Gwangju merger, verified effective:
# en+ko.wiki + Aju Press 2026-07-01 + Nominatim): new L1 row needs
# maintainer type vocabulary + code policy (ISO cell blank; admin
# code 12 single-signal) — not exact cells, held.
# HOLD H-STRUCT-2 (Incheon reorg, verified effective: ko.wiki +
# Incheon city official + district articles + Nominatim): 441-code
# re-attribution needs per-code dong mapping (Korea Post DB); tree-
# only application would orphan 441 legs — held together with H1.
# Mixed-vintage application (merger without Incheon) is incoherent:
# both share effective date 2026-07-01. New names pinned ABSENT
# (jemulpo/yeongjong/geomdan/seohae/jeonnam-gwangju) and old names
# pinned PRESENT so the held state is explicit, not silent.
# EOL: areas LF-only; postal CRLF.
# Asserts HELD pre-reorg state; run from repo root:
# python3 docs/agents/audit/gate_kr.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/south-korea-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/south-korea-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/south-korea-postal-code-areas.csv'
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
check('codes-CRLF', b'\r\n' in raw_c and b'\r' not in raw_c.replace(b'\r\n', b''))
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF', b'\r\n' in raw_l and b'\r' not in raw_l.replace(b'\r\n', b''))
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
try:
    rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
    codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
    links = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
except FileNotFoundError as e:
    check('files-present', False, str(e))
    print(f'{len(fails)} FAILURES')
    sys.exit(1)
byid = {r['source_id']: r for r in rows}
check('areas-245', len(rows) == 245, str(len(rows)))
l1 = [r for r in rows if r['level'] == '1']
check('L1-17', len(l1) == 17, str(len(l1)))
check('L2-228', sum(1 for r in rows if r['level'] == '2') == 228)
codeset = {r['code'] for r in l1}
check('iso-L1', codeset == {'11', '26', '27', '28', '29', '30', '31', '41',
                            '42', '43', '44', '45', '46', '47', '48', '49', '50'},
      str(sorted(codeset)))
check('gunwi-daegu', byid.get('kr:county:gunwi', {}).get('parent_source_id') ==
      'kr:metropolitan_city:daegu')
check('michuhol-incheon', byid.get('kr:district:michuhol', {}).get('parent_source_id') ==
      'kr:metropolitan_city:incheon')
check('jeonbuk-state', byid.get('kr:special_self_governing_province:jeonbuk-state', {}).get('name') ==
      'Jeonbuk State')
for sid in ['kr:metropolitan_city:gwangju', 'kr:province:south-jeolla',
            'kr:district:incheon:jung', 'kr:district:incheon:dong',
            'kr:district:incheon:seo']:
    check(f'held-present-{sid}', sid in byid)
blob = '\n'.join(byid)
for slug in ['jemulpo', 'yeongjong', 'geomdan', 'seohae', 'jeonnam-gwangju']:
    check(f'held-absent-{slug}', slug not in blob)
check('codes-34249', len(codes) == 34249, str(len(codes)))
check('links-34249', len(links) == 34249, str(len(links)))
check('codes-KR', all(c['country_code'] == 'KR' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('codes-unique', len(set(clist)) == len(clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
check('all-primary', all(l['is_primary'] == 'true' for l in links))
have = Counter(l['postcode'] for l in links)
check('multis-0', all(v == 1 for v in have.values()))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
cov = Counter(l['area_source_id'] for l in links)
for sid, n in [('kr:special_self_governing_city:sejong-city', 142),
               ('kr:district:incheon:jung', 109),
               ('kr:district:incheon:dong', 76),
               ('kr:district:incheon:seo', 256)]:
    check(f'legcount-{sid}', cov.get(sid, 0) == n, str(cov.get(sid, 0)))
sej = sorted(l['postcode'] for l in links
             if l['area_source_id'] == 'kr:special_self_governing_city:sejong-city')
check('sejong-range', sej and sej[0] == '30000' and sej[-1] == '30154',
      f'{sej[0] if sej else "?"}..{sej[-1] if sej else "?"}')
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
