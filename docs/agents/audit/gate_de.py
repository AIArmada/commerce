#!/usr/bin/env python3
"""B19 DE gate. Pins the pass (417 areas: 16 L1 + 401 L2; 10812 codes /
10911 legs; areas LF, postal CRLF): tree membership/types/parents exact
vs ISO + WP LK294/SK107 KrS lists + GN AGS (Hanau 27th-HE vindicated);
11 name endonymizations (6 English exonyms -> German + Hohenlohekreis,
Saarpfalz-Kreis, St. Wendel, Frankfurt (Oder), Neustadt an der Aisch-BW;
English kept as provider alternatives). Postal set == fresh GN
10,812 exactly; 20 same-name-town retargets + 4 primary swaps (07919,
12529, 21465, 92637) + 1 Stormarn secondary (22113); S5 98711 HELD
(Nominatim empty, medium confidence); 10/10 anchors + 10/10 integrator
OSM spot codes agree."""
import csv
import sys

GEO = 'packages/addressing/resources/geography'
A = f'{GEO}/germany-address-areas.csv'
C = f'{GEO}/germany-postal-codes.csv'
L = f'{GEO}/germany-postal-code-areas.csv'

fails = []


def check(name, ok, extra=''):
    print(('PASS' if ok else 'FAIL'), name, extra)
    if not ok:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-LF-only', b'\r' not in raw_a)
check('areas-lines-418', raw_a.count(b'\n') == 418, str(raw_a.count(b'\n')))
check('areas-trailing-LF', raw_a.endswith(b'\n') and not raw_a.endswith(b'\r\n'))
check('codes-pure-CRLF', raw_c.count(b'\r\n') == raw_c.count(b'\n') == 10813,
      str(raw_c.count(b'\r\n')))
check('legs-pure-CRLF', raw_l.count(b'\r\n') == raw_l.count(b'\n') == 10912,
      str(raw_l.count(b'\r\n')))

rows = list(csv.DictReader(open(A, newline='', encoding='utf-8-sig')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8-sig')))
legs = list(csv.DictReader(open(L, newline='', encoding='utf-8-sig')))
check('areas-417', len(rows) == 417, str(len(rows)))
check('source-ids-unique', len({r['source_id'] for r in rows}) == len(rows))
check('L1-16', sum(1 for r in rows if r['level'] == '1') == 16)
check('L2-401', sum(1 for r in rows if r['level'] == '2') == 401)
byid = {r['source_id']: r for r in rows}
check('parents-resolve', all(not r['parent_source_id'] or r['parent_source_id'] in byid for r in rows))
check('parents-are-L1', all(byid[r['parent_source_id']]['level'] == '1'
                            for r in rows if r['parent_source_id']))

for sid, nm in [
        ('de:district:cleves', 'Kleve'), ('de:district:cologne', 'Köln'),
        ('de:district:hanover', 'Hannover'), ('de:district:munich', 'München'),
        ('de:district:bayern:munich', 'München'), ('de:district:nuremberg', 'Nürnberg'),
        ('de:district:hohenlohe', 'Hohenlohekreis'),
        ('de:district:saarpfalz', 'Saarpfalz-Kreis'),
        ('de:district:sankt-wendel', 'St. Wendel'),
        ('de:district:frankfurt-an-der-oder', 'Frankfurt (Oder)'),
        ('de:district:neustadt-aisch-bad-windsheim', 'Neustadt an der Aisch-Bad Windsheim')]:
    check(f'rename-{sid.split(":")[-1][:12]}', byid.get(sid, {}).get('name') == nm, sid)
check('hanau-urban', byid.get('de:district:hanau', {}).get('type') == 'urban_district')

check('codes-10812', len(codes) == 10812, str(len(codes)))
check('codes-unique', len({r['code'] for r in codes}) == len(codes))
check('legs-10911', len(legs) == 10911, str(len(legs)))
sids = set(byid)
check('legs-resolve', all(r['area_source_id'] in sids for r in legs))
bycode = {}
for r in legs:
    bycode.setdefault(r['postcode'], []).append(r)
check('every-code-linked', set(bycode) == {r['code'] for r in codes})
check('one-primary-each', all(sum(1 for r in v if r['is_primary'] == 'true') == 1 for v in bycode.values()))

single = lambda pc: (bycode[pc][0]['area_source_id'], len(bycode[pc]))  # noqa: E731
for pc, tgt in [
        ('29633', 'de:district:heidekreis'), ('33790', 'de:district:gutersloh'),
        ('35096', 'de:district:marburg-biedenkopf'), ('37620', 'de:district:holzminden'),
        ('49170', 'de:district:osnabruck'), ('49632', 'de:district:cloppenburg'),
        ('50586', 'de:district:cologne'), ('55246', 'de:district:wiesbaden'),
        ('55252', 'de:district:wiesbaden'), ('55424', 'de:district:mainz-bingen'),
        ('64658', 'de:district:bergstrasse'), ('64839', 'de:district:darmstadt-dieburg'),
        ('67580', 'de:district:alzey-worms'), ('68794', 'de:district:karlsruhe'),
        ('84095', 'de:district:landshut'), ('86692', 'de:district:donau-ries'),
        ('86697', 'de:district:neuburg-schrobenhausen'), ('86854', 'de:district:unterallgau'),
        ('93437', 'de:district:cham'), ('94405', 'de:district:dingolfing-landau')]:
    got, n = single(pc)
    check(f'r-{pc}', got == tgt and n == 1, got)

for pc, prim in [('07919', 'de:district:vogtlandkreis'),
                 ('12529', 'de:district:dahme-spreewald'),
                 ('21465', 'de:district:stormarn'),
                 ('92637', 'de:district:weiden-in-der-oberpfalz')]:
    pr = [r for r in bycode[pc] if r['is_primary'] == 'true']
    check(f'swap-{pc}', len(pr) == 1 and pr[0]['area_source_id'] == prim,
          pr[0]['area_source_id'] if pr else '?')

w22113 = sorted((r['area_source_id'], r['is_primary']) for r in bycode['22113'])
check('insert-22113', w22113 == [('de:district:hamburg', 'true'),
                                 ('de:district:stormarn', 'false')], str(w22113))
h98711 = sorted((r['area_source_id'], r['is_primary']) for r in bycode['98711'])
check('hold-98711', h98711 == [('de:district:ilm-kreis', 'true'),
                               ('de:district:suhl', 'false')], str(h98711))

print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
