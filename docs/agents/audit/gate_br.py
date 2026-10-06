#!/usr/bin/env python3
"""B21 BR gate. Pins the pass (5598 areas: 27 L1 + 5570 munis + 1
district; 5547 codes / 5547 legs; areas LF, postal CRLF): tree ZERO
changes — 27/27 ISO, 5570/5570 byte-exact vs IBGE, FnN correctly typed
district under PE, Boa Esperanca do Norte present; postal one-major-
CEP-per-municipality granularity confirmed: 38 RO renumber replaces
789xx->768/769xx (ViaCEP old-erro 52/52 + new-OK IBGE-match + OSM
postcode), 78937-000 stale Itapua dup deleted (76861 kept), 68948-000
relinked Pedra Branca->Serra do Navio, 22 general-CEP adds (ViaCEP-OK
+ OSM); follow-up: 3 more RO (Jaru/Medici/Mamore via integrator OSM) + 3
small-city generals + Bujari 69923->69926 (ViaCEP Rio Branco street + OSM);
10 RO stale held (big-city policy + L1-only), 42 unlinked
held, DF-19 + Bujari + small-city-13 + Noronha held; 144/144
ViaCEP-OK vindications + 10 rename resolutions + Buritirana kept."""
import csv
import sys

GEO = 'packages/addressing/resources/geography'
A = f'{GEO}/brazil-address-areas.csv'
C = f'{GEO}/brazil-postal-codes.csv'
L = f'{GEO}/brazil-postal-code-areas.csv'

fails = []


def check(name, ok, extra=''):
    print(('PASS' if ok else 'FAIL'), name, extra)
    if not ok:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-lines-5599', raw_a.count(b'\n') == 5599, str(raw_a.count(b'\n')))
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 5548,
      str(raw_c.count(b'\r\n')))
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 5548,
      str(raw_l.count(b'\r\n')))

rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
check('areas-5598', len(rows) == 5598, str(len(rows)))
check('source-ids-unique', len({r['source_id'] for r in rows}) == len(rows))
check('L1-27', sum(1 for r in rows if r['level'] == '1') == 27)
check('L2-5571', sum(1 for r in rows if r['level'] == '2') == 5571)
byid = {r['source_id']: r for r in rows}
check('parents-resolve', all(not r['parent_source_id'] or r['parent_source_id'] in byid for r in rows))
check('fnn-district', byid.get('br:district:2605459', {}).get('parent_source_id') == 'br:state:pernambuco')
check('boa-esperanca', 'br:municipality:5101837' in byid)

check('codes-5547', len(codes) == 5547, str(len(codes)))
check('codes-unique', len({r['code'] for r in codes}) == len(codes))
check('codes-sorted', [r['code'] for r in codes] == sorted(r['code'] for r in codes))
check('legs-5547', len(legs) == 5547, str(len(legs)))
sids = set(byid)
check('legs-resolve', all(r['area_source_id'] in sids for r in legs))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
check('every-code-linked', set(bycode) == {r['code'] for r in codes})
check('one-primary-each', all(sum(1 for r in v if r['is_primary'] == 'true') == 1 for v in bycode.values()))
check('single-leg-each', all(len(v) == 1 for v in bycode.values()))

for pc, ibge in [('76954-000', '1100015'), ('76994-000', '1100031'),
                 ('76850-000', '1100106'), ('76868-000', '1100130'),
                 ('76970-000', '1100189'), ('76863-000', '1100262'),
                 ('76940-000', '1100288'), ('76930-000', '1100346'),
                 ('76952-000', '1100379'), ('76862-000', '1100403'),
                 ('76880-000', '1100452'), ('76956-000', '1100502'),
                 ('76889-000', '1100601'), ('76887-000', '1100700'),
                 ('76948-000', '1100908'), ('76990-000', '1100924'),
                 ('76864-000', '1100940'), ('76898-000', '1101005'),
                 ('76919-000', '1101203'), ('76926-000', '1101302'),
                 ('76888-000', '1101401'), ('76924-000', '1101435'),
                 ('76979-000', '1101450'), ('76999-000', '1101468'),
                 ('76976-000', '1101476'), ('76977-000', '1101484'),
                 ('76935-000', '1101492'), ('76934-000', '1101500'),
                 ('76928-000', '1101559'), ('76866-000', '1101609'),
                 ('76929-000', '1101708'), ('76867-000', '1101757'),
                 ('76923-000', '1101807')]:
    v = bycode.get(pc, [])
    check(f'ro-{pc[:5]}', len(v) == 1 and v[0]['area_source_id'] == f'br:municipality:{ibge}')
held10 = ['78900-000', '78930-000', '78938-000',
          '78950-000', '78960-000', '78966-000', '78970-000',
          '78975-000', '78983-000', '78995-000']
check('held10-present', all(pc in bycode for pc in held10))
check('stale-gone', not any(pc.startswith('789') and pc not in held10 for pc in bycode))
for pc, ibge in [('76890-000', '1100114'), ('76916-000', '1100254'), ('76857-000', '1100338')]:
    v = bycode.get(pc, [])
    check(f'ro2-{pc[:5]}', len(v) == 1 and v[0]['area_source_id'] == f'br:municipality:{ibge}')
for pc, ibge in [('75398-000', '5219100'), ('58489-000', '2501302'), ('35567-000', '3164605')]:
    v = bycode.get(pc, [])
    check(f'small-{pc[:5]}', len(v) == 1 and v[0]['area_source_id'] == f'br:municipality:{ibge}')
check('bujari-69926', bycode.get('69926-000', [{}])[0].get('area_source_id') == 'br:municipality:1200138')
check('bujari-69923-gone', '69923-000' not in bycode)
check('dup-gone', '78937-000' not in bycode)
check('itapua-kept', bycode.get('76861-000', [{}])[0].get('area_source_id') == 'br:municipality:1101104')
check('relink-68948', bycode.get('68948-000', [{}])[0].get('area_source_id') == 'br:municipality:1600055')
check('pedra-kept', bycode.get('68945-000', [{}])[0].get('area_source_id') == 'br:municipality:1600154')

for pc, ibge in [('68129-000', '1504752'), ('57255-000', '2703759'),
                 ('29720-000', '3202256'), ('99523-000', '4300471'),
                 ('95933-000', '4304614'), ('95308-000', '4304622'),
                 ('97753-000', '4304655'), ('95538-000', '4310652'),
                 ('99457-000', '4310876'), ('96920-000', '4311239'),
                 ('97935-000', '4312179'), ('95717-000', '4314548'),
                 ('99720-000', '4315313'), ('97843-000', '4315958'),
                 ('97335-000', '4316972'), ('95893-000', '4323770'),
                 ('78678-000', '5101852'), ('78237-000', '5103437'),
                 ('78579-000', '5104542'), ('78674-000', '5106315'),
                 ('78628-000', '5107792'), ('75160-000', '5204854')]:
    v = bycode.get(pc, [])
    check(f'add-{pc}', len(v) == 1 and v[0]['area_source_id'] == f'br:municipality:{ibge}'
          and v[0]['is_primary'] == 'true')

print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
