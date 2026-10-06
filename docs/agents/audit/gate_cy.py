import csv, sys
from collections import Counter
# Cyprus gate. Pins the B20 pass (761 areas: 6 L1 + 755 L2;
# 1132 codes / 1135 legs; areas header+L1 LF + L2 CRLF,
# postal pure CRLF): L1 6/6 exact vs ISO 3166-2:CY.
# KEY FINDING: bundle was a 1:1 GeoNames derivation, so GN
# proves nothing — verification rests on the official Cyprus
# Post directory xlsx (34k street rows + 757 communities) +
# live finder AJAX + wiki district lists. Fixes: P1 +8 codes
# (1000/3014/5000/6029/8203/8204/8652/8653, +3 L2 anchors
# inc. Ammochostos city); P2 5720 spurious dropped + L2
# dedup (GN double-rowed one Agios Georgios) + 5520 repoint;
# P3 Fylousa pair swapped (GN had them crossed); P4 4528
# primary -> Pentakomo (Kyverniti not a community); P5 1025
# second leg Omorfita (+L2); R1 homoglyph U+03BF Kato
# Zodia->Zodeia + slug, R2-R5 renames (Pano Zodeia,
# Tremetousia, Komi Kebir, Tziaos). Policy: RoC 4-digit
# system island-wide incl. north (like S1); TRNC 5-digit
# out of scope; 392 POB-only codes correctly excluded.
# HOLDS: 9 community-only codes valid; name variants kept;
# 5 further multi-community codes single-signal, not added.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_cy.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
G = f'{ROOT}/packages/addressing/resources/geography'
A = f'{G}/cyprus-address-areas.csv'
C = f'{G}/cyprus-postal-codes.csv'
L = f'{G}/cyprus-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)
raw_a = open(A, 'rb').read()
check('areas-mixed-EOL-preserved', raw_a.count(b'\r\n') == 755 and raw_a.count(b'\n') == 762,
      f"{raw_a.count(b'\r\n')}/{raw_a.count(b'\n')}")
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\r\n'))
raw_c = open(C, 'rb').read()
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 1133, str(raw_c.count(b'\r\n')))
raw_l = open(L, 'rb').read()
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 1136, str(raw_l.count(b'\r\n')))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
check('areas-761', len(rows) == 761, str(len(rows)))
check('source-ids-unique', len(byid) == len(rows))
check('L1-6', sum(1 for r in rows if r['level'] == '1') == 6)
check('L2-755', sum(1 for r in rows if r['level'] == '2') == 755)
check('parents-resolve', all(r['parent_source_id'] in byid for r in rows if r['level'] == '2'))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1' for r in rows if r['level'] == '2'))
iso = {'nicosia-lefkosa': '01', 'limassol-leymasun': '02', 'larnaca-larnaka': '03',
       'famagusta-magusa': '04', 'paphos-pafos': '05', 'kyrenia-keryneia': '06'}
check('iso-6', all(byid.get(f'cy:district:{s}', {}).get('code') == c for s, c in iso.items()))
check('add-ammochostos', byid.get('cy:locality:ammochostos', {}).get('parent_source_id') == 'cy:district:famagusta-magusa')
check('add-agios-fotios', byid.get('cy:locality:agios-fotios', {}).get('parent_source_id') == 'cy:district:paphos-pafos')
check('add-ampelitis', byid.get('cy:locality:ampelitis', {}).get('parent_source_id') == 'cy:district:paphos-pafos')
check('add-omorfita', byid.get('cy:locality:lefkosia-omorfita', {}).get('name') == 'Lefkosia (Omorfita)')
check('del-acheritou', 'cy:locality:agios-georgios-acheritou' not in byid)
check('r1-zodeia', byid.get('cy:locality:kato-zodeia', {}).get('name') == 'Kato Zodeia')
check('r1-homoglyph-gone', 'ο'.isascii() is False and 'ο' not in (byid.get('cy:locality:kato-zodeia', {}).get('name') or ''))
check('r2-pano', byid.get('cy:locality:pano-zodeia', {}).get('name') == 'Pano Zodeia')
check('r3-tremetousia', byid.get('cy:locality:tremetousia', {}).get('name') == 'Tremetousia')
check('r4-komi-kebir', byid.get('cy:locality:komi-kebir', {}).get('name') == 'Komi Kebir')
check('r5-tziaos', byid.get('cy:locality:tziaos', {}).get('name') == 'Tziaos')
codes = [r['code'] for r in csv.DictReader(open(C, newline='', encoding='utf-8'))]
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
check('codes-1132', len(codes) == 1132, str(len(codes)))
check('codes-unique', len(set(codes)) == len(codes))
check('legs-1135', len(legs) == 1135, str(len(legs)))
check('legs-resolve', all(r['area_source_id'] in byid for r in legs))
prim = Counter(r['postcode'] for r in legs if r['is_primary'] == 'true')
check('every-code-linked', set(codes) == {r['postcode'] for r in legs})
check('one-primary-each', all(prim.get(c) == 1 for c in codes))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append((r['area_source_id'], r['is_primary']))
check('p1-1000', bycode['1000'] == [('cy:locality:lefkosia', 'true')])
check('p1-5000', bycode['5000'] == [('cy:locality:ammochostos', 'true')])
check('p1-8652', bycode['8652'] == [('cy:locality:agios-fotios', 'true')])
check('p1-8653', bycode['8653'] == [('cy:locality:ampelitis', 'true')])
check('p2-5720-gone', '5720' not in set(codes) and '5720' not in bycode)
check('p2-5520', bycode['5520'] == [('cy:locality:agios-georgios-ammochostou', 'true')])
check('p3-8629', bycode['8629'] == [('cy:locality:fylousa-kelokedaron', 'true')])
check('p3-8811', bycode['8811'] == [('cy:locality:fylousa-chrysochous', 'true')])
check('p4-4528', sorted(bycode['4528']) == [('cy:locality:akti-kyverniti', 'false'), ('cy:locality:pentakomo', 'true')])
check('p5-1025', sorted(bycode['1025']) == [('cy:locality:lefkosia-kaimakli', 'true'), ('cy:locality:lefkosia-omorfita', 'false')])
check('r1-2723', bycode['2723'] == [('cy:locality:kato-zodeia', 'true')])
check('r4-5828', bycode['5828'] == [('cy:locality:komi-kebir', 'true')])
check('r5-5654', bycode['5654'] == [('cy:locality:tziaos', 'true')])
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
