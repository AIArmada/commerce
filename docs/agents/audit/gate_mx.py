#!/usr/bin/env python3
"""B21 MX gate. Pins the pass (2511 areas: 32 L1 + 2463 munis + 16
boroughs; 32448 codes / 32448 legs; areas LF, postal CRLF): tree 32/32
ISO codes, 2479 keys = INEGI 2478 + Villa Juarez 01012 (2026-08-28
creation vindicated), 21/21 new-key deltas vindicated (incl. Playa
del Carmen 2025 rename, Batopilas 2017, Yalalag 2023); 10 name fixes
(Cadereyta Jimenez dup-bug, San Juan Colorado corruption, 4 official-
long restorations, San Martin Hidalgo, Blas Atempa de-prefix,
Atltzayanca, Ziltlaltepec, Medellin de Bravo); T11 Las Casas HELD
(INEGI capital-L; WP agrees), H1 Juchitan + H2 Dto. punctuation held
for AGEEML; postal PF1 77580/77586 Benito Juarez->Puerto Morelos
(2015 split); 74801 Tehuitzingo kept (S1 master isolated error);
PF2 317 adds HELD (single-lineage, current-Correos spot check
blocked: correos + datos.gob.mx timeout from sandbox); PH1 473
bundle-only held; 1285 admin codes correctly absent; 384/384 sample."""
import csv
import sys

GEO = 'packages/addressing/resources/geography'
A = f'{GEO}/mexico-address-areas.csv'
C = f'{GEO}/mexico-postal-codes.csv'
L = f'{GEO}/mexico-postal-code-areas.csv'

fails = []


def check(name, ok, extra=''):
    print(('PASS' if ok else 'FAIL'), name, extra)
    if not ok:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-lines-2512', raw_a.count(b'\n') == 2512, str(raw_a.count(b'\n')))
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 32449,
      str(raw_c.count(b'\r\n')))
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 32449,
      str(raw_l.count(b'\r\n')))

rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
check('areas-2511', len(rows) == 2511, str(len(rows)))
check('source-ids-unique', len({r['source_id'] for r in rows}) == len(rows))
check('L1-32', sum(1 for r in rows if r['level'] == '1') == 32)
check('L2-2479', sum(1 for r in rows if r['level'] == '2') == 2479)
byid = {r['source_id']: r for r in rows}
check('parents-resolve', all(not r['parent_source_id'] or r['parent_source_id'] in byid for r in rows))

for sid, nm in [
        ('mx:municipality:19009', 'Cadereyta Jiménez'),
        ('mx:municipality:20188', 'San Juan Colorado'),
        ('mx:municipality:11014', 'Dolores Hidalgo Cuna de la Independencia Nacional'),
        ('mx:municipality:14077', 'San Martín Hidalgo'),
        ('mx:municipality:15001', 'Acambay de Ruíz Castañeda'),
        ('mx:municipality:20124', 'San Blas Atempa'),
        ('mx:municipality:20334', 'Villa de Tututepec de Melchor Ocampo'),
        ('mx:municipality:29004', 'Atltzayanca'),
        ('mx:municipality:29037', 'Ziltlaltépec de Trinidad Sánchez Santos'),
        ('mx:municipality:30105', 'Medellín de Bravo')]:
    check(f'f-{sid.split(":")[-1]}', byid.get(sid, {}).get('name') == nm, sid)
check('los-ramones-once', sum(1 for r in rows if r['name'] == 'Los Ramones') == 1)
check('T11-held', byid.get('mx:municipality:07078', {}).get('name') == 'San Cristóbal de Las Casas')
check('villa-juarez', 'mx:municipality:01012' in byid)
check('playa-carmen', byid.get('mx:municipality:23008', {}).get('name') == 'Playa del Carmen')

check('codes-32448', len(codes) == 32448, str(len(codes)))
check('codes-unique', len({r['code'] for r in codes}) == len(codes))
check('legs-32448', len(legs) == 32448, str(len(legs)))
sids = set(byid)
check('legs-resolve', all(r['area_source_id'] in sids for r in legs))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
check('every-code-linked', set(bycode) == {r['code'] for r in codes})
check('one-primary-each', all(sum(1 for r in v if r['is_primary'] == 'true') == 1 for v in bycode.values()))
for pc in ['77580', '77586']:
    v = bycode.get(pc, [])
    check(f'pf1-{pc}', len(v) == 1 and v[0]['area_source_id'] == 'mx:municipality:23011')
check('pk1-74801', bycode.get('74801', [{}])[0].get('area_source_id') == 'mx:municipality:21157')
check('pf2-held', '20388' not in bycode and '25123' not in bycode)

print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
