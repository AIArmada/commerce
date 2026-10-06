import csv, sys
# Armenia gate. B11 revisit: fix-and-fill — 2 fills + 1 retarget
# (779 -> 781 codes/links). Fills: 0109 -> Kentron (new Haypost
# branch 2026-09-30, Arshakunyats 18/4; Photon + Nominatim both
# Kentron) and 0236 -> Ashtarak (new 2026-09-28 Postmobil branch,
# city Artashavan; WP + Nominatim both Ashtarak community; junk
# 1,6 coords are a Haypost data error like 3014/4112). Retarget:
# 3518 Vaghatin Goris -> Sisian (mtad.am Sisian settlement list
# + Nominatim; Goris page lacks it). Keep 2102 Tashir (Haypost API
# dropped it but Spyur branch directory + OSM HayPost-2102 POI
# show Tashir post office No.2 live -> stability). New Haypost
# city typo: 0919 "v. Artashat" = Artashar (branch coords reverse-
# geocode Artashar/Metsamor on both engines; no Artashat village
# in any Armavir settlement list) so Metsamor holds. Tree 93
# verify-only vs citypopulation 70 municipalities (q/k + j/ch
# romanization variants only; WP Armavir "7" lede is stale, its
# table lists 8 incl. Khoy, mtad confirms 8). 30-link sample:
# 29/30 correct, only 3518 moved. Areas LF; postal files CRLF.
# Run from repo root:
# python3 docs/agents/audit/gate_am.py
A = './packages/addressing/resources/geography/armenia-address-areas.csv'
C = './packages/addressing/resources/geography/armenia-postal-codes.csv'
L = './packages/addressing/resources/geography/armenia-postal-code-areas.csv'
COUNTS = {'am:region:aragatsotn': 8, 'am:region:ararat': 5,
          'am:region:armavir': 8, 'am:region:gegharkunik': 5,
          'am:region:kotayk': 11, 'am:region:lori': 11,
          'am:region:shirak': 6, 'am:region:syunik': 7,
          'am:region:tavush': 4, 'am:region:vayots-dzor': 5,
          'am:city:yerevan': 12}
PINS = {'0109': 'am:district:kentron',
        '0236': 'am:municipality:ashtarak',
        '3518': 'am:municipality:sisian',
        '2102': 'am:municipality:tashir',
        '0725': 'am:municipality:artashat',
        '0614': 'am:municipality:vedi',
        '3019': 'am:municipality:artik',
        '0513': 'am:municipality:talin',
        '1741': 'am:municipality:alaverdi',
        '3902': 'am:municipality:dilijan',
        '2032': 'am:municipality:pambak',
        '2610': 'am:municipality:akhuryan',
        '0919': 'am:municipality:metsamor',
        '1128': 'am:municipality:khoy',
        '1817': 'am:municipality:spitak',
        '3401': 'am:municipality:meghri',
        '2410': 'am:municipality:nor-hachn',
        '4215': 'am:municipality:berd',
        '0010': 'am:district:kentron',
        '0022': 'am:district:avan',
        '0074': 'am:district:shengavit'}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('codes-crlf', raw_c.count(b'\r\n') == 782
      and raw_c.endswith(b'\r\n'))
check('links-crlf', raw_l.count(b'\r\n') == 782
      and raw_l.endswith(b'\r\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-93', len(areas) == 93, str(len(areas)))
l1 = [r for r in areas if r['level'] == '1']
check('l1-11', len(l1) == 11, str(len(l1)))
l2 = [r for r in areas if r['level'] == '2']
check('l2-82', len(l2) == 82, str(len(l2)))
from collections import Counter
cc = Counter(r['parent_source_id'] for r in l2)
check('parent-counts', dict(cc) == COUNTS, str(dict(cc)))
codes = list(csv.DictReader(open(C, encoding='utf-8')))
links = list(csv.DictReader(open(L, encoding='utf-8')))
check('codes-781', len(codes) == 781, str(len(codes)))
check('links-781', len(links) == 781, str(len(links)))
check('links-primary',
      all(r['is_primary'] == 'true' for r in links))
check('codes-link-match',
      {r['code'] for r in codes} == {r['postcode'] for r in links})
bycode = {r['postcode']: r['area_source_id'] for r in links}
bad = {c for c in PINS if bycode.get(c) != PINS[c]}
check('pins-21', not bad, str(sorted(bad)))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)
