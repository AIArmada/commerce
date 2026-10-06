import csv, re, sys
from collections import Counter
# Sweden gate. Pins the B17 worker integration (62 postal leg
# moves F-A..F-J + 1 name-only rename F-K Gothenburg->Göteborg;
# 311 areas / 18887 codes / 18887 legs / 0 multis): 21 counties
# ISO 3166-2:SE exact + 290 municipalities SCB/sv.wiki/en.wiki
# exact (codes 290/290). MOVES (each >=2 signals: sv.wiki
# tätort->kommun + Bring postort-valid and/or Nominatim kommun
# and/or hitta listings; GN place labels corroborate where GN
# is not the error source): Kisa 59036-40 + Rimforsa 59041/43/
# 44/46 + Horn 59042 -> kinda (was linkoping/vastervik); 12x
# Storvreta 743xx -> uppsala (was sala); 20x Höör 243xx -> hoor
# (was almhult); 10x Vintrosa 719xx -> orebro (was koping; 71921/
# 22 Bring-lossy but block postort VINTROSA valid); Hållnäs
# 81963-65 -> tierp (was vindeln; GN Hällnäs rows are GN errors);
# Rockneby/Läckeby 38030/31 -> kalmar (was nybro); Stugun 83076
# -> ragunda (was ostersund); Ydre 57374-77 -> ydre (was tranas).
# Post-move: kinda 10, ydre 5, hoor 26, no municipality at zero.
# KEEPS: genuine straddles (74197 Almunge->uppsala, Mariannelund,
# Dikanäs, Slagnäs, Kvicksund, 27035, 29062), GN place-label traps
# (34341/73119/73345/58150 stay), Billdal->gothenburg.
# HOLDS: H1 box/storföretag/svarspost/tävlingspost mixing (~3815
# codes, needs contract decision); H3 3-segment habo slug; H4 empty
# native/geo fields. SKIPPED: F-L CRLF->LF (CRLF is the established
# norm for several postal files repo-wide: FI/KR/DZ; ladder
# preserves EOL byte-wise). EOL: areas LF-only; postal CRLF.
# Asserts POST-fix state; run from repo root:
# python3 docs/agents/audit/gate_se.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/sweden-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/sweden-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/sweden-postal-code-areas.csv'
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
check('areas-311', len(rows) == 311, str(len(rows)))
check('county-21', sum(1 for r in rows if r['type'] == 'county') == 21)
check('municipality-290', sum(1 for r in rows if r['type'] == 'municipality') == 290)
check('goteborg-name', byid.get('se:municipality:gothenburg', {}).get('name') == 'Göteborg')
check('goteborg-code', byid.get('se:municipality:gothenburg', {}).get('code') == '1480')
check('codes-18887', len(codes) == 18887, str(len(codes)))
check('links-18887', len(links) == 18887, str(len(links)))
check('codes-SE', all(c['country_code'] == 'SE' for c in codes))
clist = [c['code'] for c in codes]
check('codes-NNN-NN', all(re.match(r'^\d{3} \d{2}$', c) for c in clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
check('all-primary', all(l['is_primary'] == 'true' for l in links))
have = Counter(l['postcode'] for l in links)
check('multis-0', all(v == 1 for v in have.values()))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
leg = {l['postcode']: l['area_source_id'] for l in links}
expect = {}
expect.update({c: 'se:municipality:kinda' for c in
               ['590 36', '590 37', '590 38', '590 39', '590 40', '590 41',
                '590 43', '590 44', '590 46', '590 42']})
expect.update({c: 'se:municipality:uppsala' for c in
               ['743 01', '743 20', '743 21', '743 22', '743 30', '743 32',
                '743 34', '743 35', '743 40', '743 41', '743 45', '743 91']})
expect.update({c: 'se:municipality:hoor' for c in
               ['243 01', '243 20', '243 21', '243 22', '243 23', '243 26',
                '243 30', '243 31', '243 32', '243 33', '243 34', '243 35',
                '243 36', '243 39', '243 91', '243 92', '243 93', '243 94',
                '243 95', '243 96']})
expect.update({c: 'se:municipality:orebro' for c in
               ['719 21', '719 22', '719 30', '719 31', '719 32', '719 91',
                '719 92', '719 93', '719 94', '719 95']})
expect.update({c: 'se:municipality:tierp' for c in ['819 63', '819 64', '819 65']})
expect.update({c: 'se:municipality:kalmar' for c in ['380 30', '380 31']})
expect['830 76'] = 'se:municipality:ragunda'
expect.update({c: 'se:municipality:ydre' for c in
               ['573 74', '573 75', '573 76', '573 77']})
bad = [(pc, sid, leg.get(pc)) for pc, sid in expect.items() if leg.get(pc) != sid]
check('moves-62', not bad and len(expect) == 62, str(bad[:5]) if bad else f'{len(expect)} pins')
for sid, n in [('se:municipality:kinda', 10), ('se:municipality:ydre', 5),
               ('se:municipality:hoor', 26)]:
    v = sum(1 for l in links if l['area_source_id'] == sid)
    check(f'legcount-{sid}', v == n, str(v))
cov = Counter(l['area_source_id'] for l in links)
zero = [sid for sid, r in byid.items()
        if r['type'] == 'municipality' and cov.get(sid, 0) == 0]
check('no-zero-municipality', not zero, str(zero))
keeps = {'741 97': 'se:municipality:uppsala', '343 41': 'se:municipality:almhult',
         '731 19': 'se:municipality:koping', '733 45': 'se:municipality:sala',
         '581 50': 'se:municipality:linkoping', '570 60': 'se:municipality:ydre'}
for pc, sid in keeps.items():
    check(f'keep-{pc}', leg.get(pc) == sid, str(leg.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
