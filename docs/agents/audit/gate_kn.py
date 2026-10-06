import csv, re, sys
from collections import Counter
# Saint Kitts and Nevis (KN) gate. Pins the B13 pass: 2 states + 14 parishes +
# 92 villages = 108 areas; 32 codes / 39 links. Fixes vs pre-state: KN0111
# primary flipped St Peter→Cayon (Keys-majority district: 5 Keys/Cayon PDF
# mentions + Keys village anchor vs 1 Canada/St Peter mention; dual kept),
# areas CSV EOL normalized (header+L1+L2 were LF, 92 village rows CRLF) to
# LF-only. Tree: parishes ISO 3166-2:KN-exact (01-13+15, 14 skipped by ISO;
# islands K/N), villages per parish articles (holds: Sir Gillee's, St Paul's,
# Spooners, Parsons, Barnaby over article-list variants — PDF + CSV agree;
# Lodge→Christ Church, New Road→St Peter, Keys→Cayon confirmed). Postal:
# post.kn zones PDF code set 32/32 exact; 7 duals each justified (0108 Basseterre
# boundary, 0111 Keys/Canada straddle, 0202 Old Road East, 0403 Newton Ground,
# 0501 Mansion/Christ Church, 0802 Bath, 1201 Craddocks). UPU knaEn (12/2017):
# KN + 4 digits, zone+district. Run from repo root:
# python3 docs/agents/audit/gate_kn.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/saint-kitts-and-nevis-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/saint-kitts-and-nevis-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/saint-kitts-and-nevis-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# --- EOL + trailing newline assertions (raw bytes) ---
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
def is_crlf(raw):
    return b'\r\n' in raw and b'\r' not in raw.replace(b'\r\n', b'') and b'\n' not in raw.replace(b'\r\n', b'')
check('areas-LF-only', b'\r' not in raw_a)
check('areas-trailing-LF', raw_a.endswith(b'\n'))
check('codes-CRLF', is_crlf(raw_c))
check('codes-trailing-CRLF', raw_c.endswith(b'\r\n'))
check('links-CRLF', is_crlf(raw_l))
check('links-trailing-CRLF', raw_l.endswith(b'\r\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-108', len(rows) == 108, str(len(rows)))
check('areas-unique-ids', len(byid) == len(rows))
check('areas-country-KN', all(r['country_code'] == 'KN' for r in rows))
l1 = [r for r in rows if r['level'] == '1']
l2 = [r for r in rows if r['level'] == '2']
l3 = [r for r in rows if r['level'] == '3']
check('states-2', len(l1) == 2 and all(r['type'] == 'state' and not r['parent_source_id'] for r in l1))
check('state-codes', {r['source_id']: r['code'] for r in l1} == {'kn:state:saint-kitts': 'K', 'kn:state:nevis': 'N'})
check('parishes-14', len(l2) == 14 and all(r['type'] == 'parish' for r in l2))
check('villages-92', len(l3) == 92 and all(r['type'] == 'village' for r in l3), str(len(l3)))
check('l2-parents-states', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '1' for r in l2))
check('l3-parents-parishes', all(r['parent_source_id'] in byid and byid[r['parent_source_id']]['level'] == '2' for r in l3))
# ISO 3166-2:KN parish codes (01-13 + 15; 14 skipped by ISO itself).
iso = {'kn:parish:christ-church-nichola-town': '01', 'kn:parish:saint-anne-sandy-point': '02',
 'kn:parish:saint-george-basseterre': '03', 'kn:parish:saint-george-gingerland': '04',
 'kn:parish:saint-james-windward': '05', 'kn:parish:saint-john-capisterre': '06',
 'kn:parish:saint-john-figtree': '07', 'kn:parish:saint-mary-cayon': '08',
 'kn:parish:saint-paul-capisterre': '09', 'kn:parish:saint-paul-charlestown': '10',
 'kn:parish:saint-peter-basseterre': '11', 'kn:parish:saint-thomas-lowland': '12',
 'kn:parish:saint-thomas-middle-island': '13', 'kn:parish:trinity-palmetto-point': '15'}
badiso = [(s, byid.get(s, {}).get('code'), c) for s, c in iso.items() if byid.get(s, {}).get('code') != c]
check('parish-codes-iso', not badiso, str(badiso))
# Per-parish village counts (6/4/4/4/10/8/10/5/4/2/12/5/9/9).
expm = {'kn:parish:christ-church-nichola-town': 6, 'kn:parish:saint-anne-sandy-point': 4,
 'kn:parish:saint-george-basseterre': 4, 'kn:parish:saint-george-gingerland': 4,
 'kn:parish:saint-james-windward': 10, 'kn:parish:saint-john-capisterre': 8,
 'kn:parish:saint-john-figtree': 10, 'kn:parish:saint-mary-cayon': 5,
 'kn:parish:saint-paul-capisterre': 4, 'kn:parish:saint-paul-charlestown': 2,
 'kn:parish:saint-peter-basseterre': 12, 'kn:parish:saint-thomas-lowland': 5,
 'kn:parish:saint-thomas-middle-island': 9, 'kn:parish:trinity-palmetto-point': 9}
havem = Counter(r['parent_source_id'] for r in l3)
check('per-parish-villages', dict(havem) == expm, str([(k, havem.get(k, 0), v) for k, v in expm.items() if havem.get(k, 0) != v]))
# Judgment filings + naming holds.
check('pin-lodge-christ-church', byid.get('kn:village:lodge-village', {}).get('parent_source_id') == 'kn:parish:christ-church-nichola-town')
check('pin-new-road-st-peter', byid.get('kn:village:new-road', {}).get('parent_source_id') == 'kn:parish:saint-peter-basseterre')
check('pin-keys-cayon', byid.get('kn:village:keys', {}).get('parent_source_id') == 'kn:parish:saint-mary-cayon')
check('hold-sir-gillees', byid.get('kn:village:sir-gillees', {}).get('name') == "Sir Gillee's")
check('hold-st-pauls', byid.get('kn:village:st-pauls', {}).get('name') == "St Paul's")
check('hold-spooners', byid.get('kn:village:spooners', {}).get('name') == 'Spooners')
check('hold-parsons', byid.get('kn:village:parsons', {}).get('name') == 'Parsons')
check('hold-barnaby', byid.get('kn:village:barnaby', {}).get('name') == 'Barnaby')
check('pin-camps-twins', byid.get('kn:village:saint-james-windward:camps', {}).get('parent_source_id') == 'kn:parish:saint-james-windward'
    and byid.get('kn:village:camps', {}).get('parent_source_id') == 'kn:parish:trinity-palmetto-point')
# --- postal: 32 codes / 39 links ---
check('codes-32', len(codes) == 32, str(len(codes)))
check('links-39', len(links) == 39, str(len(links)))
clist = [c['code'] for c in codes]
check('codes-unique', len(set(clist)) == len(clist))
check('codes-sorted', clist == sorted(clist))
bad = [c for c in clist if not re.match(r'^KN\d{4}$', c)]
check('code-format-KN4', not bad, str(bad[:3]))
check('codes-country-KN', all(c['country_code'] == 'KN' for c in codes))
check('no-zone-07', not any(c.startswith('KN07') for c in clist))
by = {}
prim = {}
seenc = []
for l in links:
    by.setdefault(l['postcode'], []).append(l)
    if l['postcode'] != (seenc[-1] if seenc else None):
        seenc.append(l['postcode'])
    if l['is_primary'] == 'true':
        prim[l['postcode']] = l['area_source_id']
check('links-grouped-codes-order', seenc == clist)
check('every-code-one-primary', len(prim) == len(codes) and all(sum(1 for l in v if l['is_primary'] == 'true') == 1 for v in by.values()))
check('links-served-by', all(l['relationship_type'] == 'served_by' for l in links))
check('links-all-l2', all(byid[l['area_source_id']]['level'] == '2' for l in links))
dang = [l['postcode'] for l in links if l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
# Dual links: exact table (7). KN0111 Cayon-primary post-flip.
dual = {
 'KN0108': ('kn:parish:saint-peter-basseterre', ['kn:parish:saint-george-basseterre']),
 'KN0111': ('kn:parish:saint-mary-cayon', ['kn:parish:saint-peter-basseterre']),
 'KN0202': ('kn:parish:trinity-palmetto-point', ['kn:parish:saint-thomas-middle-island']),
 'KN0403': ('kn:parish:saint-john-capisterre', ['kn:parish:saint-paul-capisterre']),
 'KN0501': ('kn:parish:saint-john-capisterre', ['kn:parish:christ-church-nichola-town']),
 'KN0802': ('kn:parish:saint-paul-charlestown', ['kn:parish:saint-john-figtree']),
 'KN1201': ('kn:parish:saint-thomas-lowland', ['kn:parish:saint-paul-charlestown']),
}
havemulti = {p: v for p, v in by.items() if len(v) > 1}
wrongdual = [p for p, (pp, sss) in dual.items()
             if prim.get(p) != pp or sorted(l['area_source_id'] for l in havemulti.get(p, []) if l['is_primary'] == 'false') != sorted(sss)]
check('dual-set-7-exact', set(havemulti) == set(dual), str(sorted(set(havemulti) ^ set(dual))))
check('dual-pairs-exact', not wrongdual, str(wrongdual[:5]))
check('secondary-7', sum(1 for l in links if l['is_primary'] == 'false') == 7)
# Pins: Basseterre city run, SEP, Nevis parish-named pairs.
for code in ['KN0101', 'KN0102', 'KN0103', 'KN0104', 'KN0105', 'KN0106']:
    check(f'pin-basseterre-{code}', prim.get(code) == 'kn:parish:saint-george-basseterre')
check('pin-7000-sep', prim.get('KN7000') == 'kn:parish:saint-george-basseterre')
check('pin-0111-cayon', prim.get('KN0111') == 'kn:parish:saint-mary-cayon')
check('pin-0801-charlestown', prim.get('KN0801') == 'kn:parish:saint-paul-charlestown')
check('pin-0902-figtree', prim.get('KN0902') == 'kn:parish:saint-john-figtree')
check('pin-1002-gingerland', prim.get('KN1002') == 'kn:parish:saint-george-gingerland')
check('pin-1102-windward', prim.get('KN1102') == 'kn:parish:saint-james-windward')
check('pin-1202-lowland', prim.get('KN1202') == 'kn:parish:saint-thomas-lowland')
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
