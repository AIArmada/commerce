import csv, re, sys
from collections import Counter
# Finland gate. Pins the B17 inline pass as VERIFY-PLUS-ONE (310
# areas / 3576 codes / 3576 legs / 0 multis): 18/18 ISO 3166-2
# regions (02-19; 01 Aland on the AX provider) + 292/292 L2 exact
# vs fi.wiki kunnat (codes + names + region parents; no missing/
# extras/dups) + 107/107 city types exact vs the cities category;
# postal code-set == GeoNames FI dump 1:1 (3576/3576, zero diffs
# either way) + legs == GN admin3 on official municipality codes
# 0/3576 mismatches modulo the 3 verified post-merger mappings
# (Pertunmaa-6 194xx->mantyharju 2025, Honkajoki-4 389xx->
# kankaanpaa 2021, Valtimo-7 757xx->nurmes 2020; merged names
# absent from kunnat; old codes live-used per Google addresses);
# ONE fix: 00002 hattula->helsinki (GN row internally
# inconsistent — place Helsinki, admin Hattula; Posti's own
# address is FI-00002 Helsinki + 00xxx series + Helsinki coords).
# EOL: areas LF-only; postal CRLF.
# Asserts verified state; run from repo root:
# python3 docs/agents/audit/gate_fi.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/finland-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/finland-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/finland-postal-code-areas.csv'
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
check('areas-310', len(rows) == 310, str(len(rows)))
check('region-18', sum(1 for r in rows if r['type'] == 'region') == 18)
check('municipality-185', sum(1 for r in rows if r['type'] == 'municipality') == 185)
check('city-107', sum(1 for r in rows if r['type'] == 'city') == 107)
iso = {'south-karelia': '02', 'southern-ostrobothnia': '03', 'southern-savonia': '04',
       'kainuu': '05', 'tavastia-proper': '06', 'central-ostrobothnia': '07',
       'central-finland': '08', 'kymenlaakso': '09', 'lapland': '10',
       'pirkanmaa': '11', 'ostrobothnia': '12', 'north-karelia': '13',
       'northern-ostrobothnia': '14', 'northern-savonia': '15',
       'paijanne-tavastia': '16', 'satakunta': '17', 'uusimaa': '18',
       'finland-proper': '19'}
ok = all(byid.get(f'fi:region:{s}', {}).get('code') == c for s, c in iso.items())
check('iso-18', ok and len(iso) == 18)
spots = {'fi:city:helsinki': ('Helsinki', '091', 'fi:region:uusimaa', 'city'),
         'fi:city:akaa': ('Akaa', '020', 'fi:region:pirkanmaa', 'city'),
         'fi:municipality:koski-tl': ('Koski Tl', '284', 'fi:region:finland-proper', 'municipality'),
         'fi:city:mantta-vilppula': ('Mänttä-Vilppula', '508', 'fi:region:pirkanmaa', 'city'),
         'fi:municipality:pedersoren-kunta': ('Pedersören kunta', '599', 'fi:region:ostrobothnia', 'municipality'),
         'fi:municipality:mantyharju': ('Mäntyharju', '507', 'fi:region:southern-savonia', 'municipality'),
         'fi:city:nurmes': ('Nurmes', '541', 'fi:region:north-karelia', 'city'),
         'fi:city:kankaanpaa': ('Kankaanpää', '214', 'fi:region:satakunta', 'city')}
for sid, (nm, code, par, typ) in spots.items():
    r = byid.get(sid, {})
    check(f'spot-{sid}', r.get('name') == nm and r.get('code') == code
          and r.get('parent_source_id') == par and r.get('type') == typ,
          str((r.get('name'), r.get('code'), r.get('parent_source_id'), r.get('type'))))
l2codes = [r['code'] for r in rows if r['level'] == '2']
check('l2codes-292-unique', len(l2codes) == 292 and len(set(l2codes)) == 292 and all(l2codes))
check('codes-3576', len(codes) == 3576, str(len(codes)))
check('links-3576', len(links) == 3576, str(len(links)))
check('codes-FI', all(c['country_code'] == 'FI' for c in codes))
clist = [c['code'] for c in codes]
check('codes-5digit', all(re.match(r'^\d{5}$', c) for c in clist))
check('codes-unique', len(set(clist)) == len(clist))
check('all-served-by', all(l['relationship_type'] == 'served_by' for l in links))
check('all-primary', all(l['is_primary'] == 'true' for l in links))
have = Counter(l['postcode'] for l in links)
check('multis-0', all(v == 1 for v in have.values()))
check('legs-resolve', all(l['area_source_id'] in byid for l in links))
check('legs-L2-only', all(byid[l['area_source_id']]['level'] == '2' for l in links))
leg = {l['postcode']: l['area_source_id'] for l in links}
check('fix-00002-helsinki', leg.get('00002') == 'fi:city:helsinki', str(leg.get('00002')))
for pc in ('19410', '19420', '19430', '19460', '19470', '19480'):
    check(f'merge-pertunmaa-{pc}', leg.get(pc) == 'fi:municipality:mantyharju', str(leg.get(pc)))
for pc in ('38920', '38950', '38951', '38970'):
    check(f'merge-honkajoki-{pc}', leg.get(pc) == 'fi:city:kankaanpaa', str(leg.get(pc)))
for pc in ('75700', '75701', '75710', '75740', '75770', '75790', '75840'):
    check(f'merge-valtimo-{pc}', leg.get(pc) == 'fi:city:nurmes', str(leg.get(pc)))
for pc, sid in [('00100', 'fi:city:helsinki'), ('00102', 'fi:city:helsinki'),
                ('33100', 'fi:city:tampere'), ('20100', 'fi:city:turku')]:
    check(f'anchor-{pc}', leg.get(pc) == sid, str(leg.get(pc)))
print(f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
