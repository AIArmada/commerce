import csv, sys
# Costa Rica gate. B11 revisit: verify-only — tree 7 provinces +
# 84 cantons (es.wiki per-province 20/16/8/10/11/13/6 exact; new
# cantons Rio Cuarto/Monteverde/Puerto Jimenez present; Cobano/
# Paquera/Jicaral canton bills + Comte Burica district bill
# unenacted — 23189 archived, refiled 25750); postal 492/492 =
# WP 491-row district table + Lagunillas 61103 (Garabito 3rd
# district, law Nov 2020, en.wiki table stale), attribution
# 491/491 exact, 84 prefixes 1:1 with cantons; GeoNames 473 =
# bundled minus 22 documented extras (17 post-GN districts + 5
# new-canton codes) with 3 superseded GN rows correctly excluded
# (20306 Rio Cuarto-as-Grecia, 60109 Monteverde-as-Puntarenas,
# 60702 Puerto Jimenez-as-Golfito — none in the WP table).
# Areas LF; postal files CRLF.
# Run from repo root:
# python3 docs/agents/audit/gate_cr.py
A = './packages/addressing/resources/geography/costa-rica-address-areas.csv'
C = './packages/addressing/resources/geography/costa-rica-postal-codes.csv'
L = './packages/addressing/resources/geography/costa-rica-postal-code-areas.csv'
COUNTS = {'cr:province:san-jose': 20, 'cr:province:alajuela': 16,
          'cr:province:cartago': 8, 'cr:province:heredia': 10,
          'cr:province:guanacaste': 11, 'cr:province:puntarenas': 13,
          'cr:province:limon': 6}
# 3-digit prefix -> (canton source_id, district count)
PX = {'101': ('cr:canton:san-jose', 11),
      '102': ('cr:canton:escazu', 3),
      '103': ('cr:canton:desamparados', 13),
      '104': ('cr:canton:puriscal', 9),
      '105': ('cr:canton:tarrazu', 3),
      '106': ('cr:canton:aserri', 7),
      '107': ('cr:canton:mora', 7),
      '108': ('cr:canton:goicoechea', 7),
      '109': ('cr:canton:santa-ana', 6),
      '110': ('cr:canton:alajuelita', 5),
      '111': ('cr:canton:vazquez-de-coronado', 5),
      '112': ('cr:canton:acosta', 5),
      '113': ('cr:canton:tibas', 5),
      '114': ('cr:canton:moravia', 3),
      '115': ('cr:canton:montes-de-oca', 4),
      '116': ('cr:canton:turrubares', 5),
      '117': ('cr:canton:dota', 3),
      '118': ('cr:canton:curridabat', 4),
      '119': ('cr:canton:perez-zeledon', 12),
      '120': ('cr:canton:leon-cortes-castro', 6),
      '201': ('cr:canton:alajuela', 14),
      '202': ('cr:canton:san-ramon', 14),
      '203': ('cr:canton:grecia', 7),
      '204': ('cr:canton:san-mateo', 4),
      '205': ('cr:canton:atenas', 8),
      '206': ('cr:canton:naranjo', 8),
      '207': ('cr:canton:palmares', 7),
      '208': ('cr:canton:poas', 5),
      '209': ('cr:canton:orotina', 5),
      '210': ('cr:canton:san-carlos', 13),
      '211': ('cr:canton:zarcero', 7),
      '212': ('cr:canton:sarchi', 5),
      '213': ('cr:canton:upala', 8),
      '214': ('cr:canton:los-chiles', 4),
      '215': ('cr:canton:guatuso', 4),
      '216': ('cr:canton:rio-cuarto', 3),
      '301': ('cr:canton:cartago', 11),
      '302': ('cr:canton:paraiso', 6),
      '303': ('cr:canton:la-union', 8),
      '304': ('cr:canton:jimenez', 4),
      '305': ('cr:canton:turrialba', 12),
      '306': ('cr:canton:alvarado', 3),
      '307': ('cr:canton:oreamuno', 5),
      '308': ('cr:canton:el-guarco', 4),
      '401': ('cr:canton:heredia', 5),
      '402': ('cr:canton:barva', 7),
      '403': ('cr:canton:santo-domingo', 8),
      '404': ('cr:canton:santa-barbara', 6),
      '405': ('cr:canton:san-rafael', 5),
      '406': ('cr:canton:san-isidro', 4),
      '407': ('cr:canton:belen', 3),
      '408': ('cr:canton:flores', 3),
      '409': ('cr:canton:san-pablo', 2),
      '410': ('cr:canton:sarapiqui', 5),
      '501': ('cr:canton:liberia', 5),
      '502': ('cr:canton:nicoya', 7),
      '503': ('cr:canton:santa-cruz', 9),
      '504': ('cr:canton:bagaces', 4),
      '505': ('cr:canton:carrillo', 4),
      '506': ('cr:canton:canas', 5),
      '507': ('cr:canton:abangares', 4),
      '508': ('cr:canton:tilaran', 8),
      '509': ('cr:canton:nandayure', 6),
      '510': ('cr:canton:la-cruz', 4),
      '511': ('cr:canton:hojancha', 5),
      '601': ('cr:canton:puntarenas', 15),
      '602': ('cr:canton:esparza', 6),
      '603': ('cr:canton:buenos-aires', 9),
      '604': ('cr:canton:montes-de-oro', 3),
      '605': ('cr:canton:osa', 6),
      '606': ('cr:canton:quepos', 3),
      '607': ('cr:canton:golfito', 3),
      '608': ('cr:canton:coto-brus', 6),
      '609': ('cr:canton:parrita', 1),
      '610': ('cr:canton:corredores', 4),
      '611': ('cr:canton:garabito', 3),
      '612': ('cr:canton:monteverde', 1),
      '613': ('cr:canton:puerto-jimenez', 1),
      '701': ('cr:canton:limon', 4),
      '702': ('cr:canton:pococi', 7),
      '703': ('cr:canton:siquirres', 7),
      '704': ('cr:canton:talamanca', 4),
      '705': ('cr:canton:matina', 3),
      '706': ('cr:canton:guacimo', 5)}
fails = []


def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond:
        fails.append(name)


raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-lf', b'\r' not in raw_a)
check('codes-crlf', raw_c.count(b'\r\n') == 493
      and raw_c.endswith(b'\r\n'))
check('links-crlf', raw_l.count(b'\r\n') == 493
      and raw_l.endswith(b'\r\n'))
areas = list(csv.DictReader(open(A, encoding='utf-8')))
check('areas-91', len(areas) == 91, str(len(areas)))
l2 = [r for r in areas if r['level'] == '2']
check('l2-84', len(l2) == 84, str(len(l2)))
from collections import Counter
cc = Counter(r['parent_source_id'] for r in l2)
check('prov-counts', dict(cc) == COUNTS, str(dict(cc)))
codes = list(csv.DictReader(open(C, encoding='utf-8')))
links = list(csv.DictReader(open(L, encoding='utf-8')))
check('codes-492', len(codes) == 492, str(len(codes)))
check('links-492', len(links) == 492, str(len(links)))
check('links-primary',
      all(r['is_primary'] == 'true' for r in links))
check('codes-link-match',
      {r['code'] for r in codes} == {r['postcode'] for r in links})
bycode = {r['postcode']: r['area_source_id'] for r in links}
check('dead-excluded',
      all(c not in bycode for c in ('20306', '60109', '60702')))
check('lagunillas', bycode.get('61103') == 'cr:canton:garabito')
check('new-cantons', bycode.get('21601') == 'cr:canton:rio-cuarto'
      and bycode.get('61201') == 'cr:canton:monteverde'
      and bycode.get('61301') == 'cr:canton:puerto-jimenez')
got = {}
for r in links:
    got.setdefault(r['postcode'][:3], []).append(r)
bad = [p for p in PX
       if {r['area_source_id'] for r in got.get(p, [])} != {PX[p][0]}
       or len(got.get(p, [])) != PX[p][1]]
check('prefix-xmap', not bad, str(bad))
check('prefix-count-84', set(got) == set(PX),
      str(set(got) ^ set(PX)))
print('ALL PASS' if not fails else 'FAILURES: ' + ','.join(fails))
sys.exit(1 if fails else 0)
