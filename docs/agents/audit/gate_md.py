import csv, sys
from collections import Counter
# Moldova gate. Pins the B20 pass (1018 areas: 37 L1 + 981 L2;
# 1215 codes / 1220 legs, all LF): tree PASS — L1 37/37
# codes+types vs ISO 3166-2:MD (32 raions + 3 municipalities
# Chisinau/Balti/Bender + Gagauzia ATO + Transnistria TU;
# Law 764's other 2 municipii Comrat/Tiraspol are L2);
# L2 981/981 names+types vs Law 764-XV annex transcription.
# Postal: legs copied GN admin1 1:1, inheriting 3 GN defects
# -> 15 moves + 1 delete + 1 add (de-jure Law 764 admin):
# 67xx Basarabeasca folded into Cimislia (7 moves, MD-6717
# Troitcoe correctly stays), Bender-zone right-bank places
# filed under Transnistria (3200/3252 Bender, 3251 Anenii
# Noi, 4316/4317/3351/5714 Causeni), Roghi 4523 Dubasari;
# MD-5222 Riscani leg deleted (Stepanovca absent from Law
# 764, quarter of Moara de Piatra); MD-5219 Lazo added
# (GN row + infobiz live use). HOLDS: 5 dual primaries
# incl. 7333 1v1 tie; de-jure-over-de-facto policy; 2025
# amalgamations not absorbed; Posta SPA + legis.md blocked.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_md.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/moldova-address-areas.csv'
C = f'{G}/moldova-postal-codes.csv'
L = f'{G}/moldova-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
for tag, p, n in [('areas', A, 1019), ('codes', C, 1216), ('legs', L, 1221)]:
    raw = open(p, 'rb').read()
    check(f'{tag}-LF-only', b'\r' not in raw)
    check(f'{tag}-lines-{n}', raw.count(b'\n') == n, str(raw.count(b'\n')))
    check(f'{tag}-trailing-LF', raw.endswith(b'\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-1018', len(rows) == 1018, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-37', sum(1 for r in rows if r['level'] == '1') == 37)
check('L2-981', sum(1 for r in rows if r['level'] == '2') == 981)
l1types = Counter(r['type'] for r in rows if r['level'] == '1')
check('L1-composition', l1types.get('district') == 32 and l1types.get('city') == 3
      and l1types.get('autonomous_territorial_unit') == 1 and l1types.get('territorial_unit') == 1, str(dict(l1types)))
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-1215', len(codes) == 1215, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-1220', len(legs) == 1220, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('fix-6701', bycode['MD-6701'] == [('md:district:basarabeasca', 'true')])
check('fix-6716', bycode['MD-6716'] == [('md:district:basarabeasca', 'true')])
check('stay-6717', bycode['MD-6717'] == [('md:district:cimislia', 'true')])
check('fix-3200', bycode['MD-3200'] == [('md:city:bender', 'true')])
check('fix-3251', bycode['MD-3251'] == [('md:district:anenii-noi', 'true')])
check('fix-3252', bycode['MD-3252'] == [('md:city:bender', 'true')])
check('fix-4316', bycode['MD-4316'] == [('md:district:causeni', 'true')])
check('fix-4317', bycode['MD-4317'] == [('md:district:causeni', 'true')])
check('fix-3351', bycode['MD-3351'] == [('md:district:causeni', 'true')])
check('fix-5714', bycode['MD-5714'] == [('md:district:causeni', 'true')])
check('fix-4523', bycode['MD-4523'] == [('md:district:dubasari', 'true')])
check('fix-5222-single', bycode['MD-5222'] == [('md:district:drochia', 'true')])
check('fix-5219', bycode['MD-5219'] == [('md:district:drochia', 'true')])
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
