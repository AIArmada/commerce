import csv, re, sys
from collections import Counter
# Luxembourg gate. B13 revisit 2026-10-04: fix-and-fill, 15 drops + 18 fills + 24 legs.
# Integrator note: worker draft asserted multi-97; simulation of the exact op list
# over the bundle gives 94 (21 single->dual + L-1110 dual; 3 legs grow existing
# multis). Gate pins the simulated 94.
# 2026-10-06 open-case retry (#8): L-7533 Fischbach secondary DROPPED — fresh
# BD-Adresses (ACT, 2026-10-05, 179635 buildings: 7533 = 18/18 Mersch) +
# Nominatim postcode-7533 Mersch-only contradict the dated 2018-file leg.
# L-1634 (Luxembourg P + Hesperange S via Turbelfiels/Itzig) and L-2632
# (Sandweiler P + Luxembourg-city S) secondaries CONFIRMED against the same
# fresh extract; L-7300 still 0-address dormant, hold stands. Now 4434/93.
# Tree verify-only: 12/12 ISO 3166-2:LU cantons, 100/100 CACLR communes, parents exact.
# Postal: CACLR (ACT, 2026-09-28) TR street extract + CODEPT + IMMEUBLE buildings voted
# against fresh GeoNames LU dump (4330 codes, identical universe pre-fix) and the 2018
# Post Luxembourg street file. Drops: 10 GN-only phantoms + 4 retired street codes +
# 4008 phantom. Fills: 17 TR street codes + 9741 building-level survivor. Legs: 24
# cross-commune secondaries (17 Post2018 + 5 Nominatim + 2 dated-new-street).
# Holds: 65 live B-type CEDEX (out of scope), 12 retired-lingering N, 14 reserved N, 7300.
# EOL: areas CRLF, codes LF, links LF (mixed per file, preserved).
# Run from repo root: python3 docs/agents/audit/gate_lu.py [repo-root]
ROOT = sys.argv[1] if len(sys.argv) > 1 else '.'
A = f'{ROOT}/packages/addressing/resources/geography/luxembourg-address-areas.csv'
C = f'{ROOT}/packages/addressing/resources/geography/luxembourg-postal-codes.csv'
L = f'{ROOT}/packages/addressing/resources/geography/luxembourg-postal-code-areas.csv'
fails = []
def check(name, cond, detail=''):
    print(('PASS' if cond else 'FAIL'), name, detail)
    if not cond: fails.append(name)
# --- EOL + trailing newline assertions (raw bytes) ---
raw_a = open(A, 'rb').read()
raw_c = open(C, 'rb').read()
raw_l = open(L, 'rb').read()
check('areas-CRLF-only', raw_a.count(b'\n') == raw_a.count(b'\r\n') and b'\r' in raw_a)
check('areas-trailing-CRLF', raw_a.endswith(b'\r\n'))
check('codes-LF-only', b'\r' not in raw_c)
check('codes-trailing-LF', raw_c.endswith(b'\n') and not raw_c.endswith(b'\r\n'))
check('links-LF-only', b'\r' not in raw_l)
check('links-trailing-LF', raw_l.endswith(b'\n') and not raw_l.endswith(b'\r\n'))
rows = list(csv.DictReader(open(A, newline='', encoding='utf-8')))
codes = list(csv.DictReader(open(C, newline='', encoding='utf-8')))
links = list(csv.DictReader(open(L, newline='', encoding='utf-8')))
byid = {r['source_id']: r for r in rows}
# --- tree: 12 cantons L1 + 100 communes L2 ---
check('areas-112', len(rows) == 112, str(len(rows)))
cantons = [r for r in rows if r['type'] == 'canton']
communes = [r for r in rows if r['type'] == 'commune']
check('cantons-12-l1', len(cantons) == 12 and all(r['level'] == '1' and not r['parent_source_id'] for r in cantons))
check('communes-100-l2', len(communes) == 100 and all(r['level'] == '2' for r in communes))
check('areas-unique-ids', len(byid) == 112)
check('canton-codes-ISO', sorted(r['code'] for r in cantons) == ['CA','CL','DI','EC','ES','GR','LU','ME','RD','RM','VD','WI'])
check('areas-country-LU', all(r['country_code'] == 'LU' for r in rows))
orph = [r['source_id'] for r in rows if r['parent_source_id'] and r['parent_source_id'] not in byid]
check('no-orphans', not orph, str(orph[:3]))
check('commune-parents-are-cantons', all(byid[r['parent_source_id']]['type'] == 'canton' for r in communes))
# post-fusion set pins: merged names present, pre-fusion ghosts absent
names = {r['name'] for r in rows}
check('merged-2023-present', {'Bous-Waldbredimus','Groussbus-Wal'} <= names)
check('merged-2018-present', {'Habscht','Helperknapp','Rosport-Mompach'} <= names)
check('merged-2012-present', {'Käerjeng','Parc Hosingen','Schengen',"Vallée de l'Ernz"} <= names)
check('garnich-current', 'Garnich' in names)
ghosts = {'Bous','Waldbredimus','Grosbous','Wahl','Hobscheid','Septfontaines','Boevange-sur-Attert','Tuntange','Rosport','Mompach','Bascharage','Clemency','Berg','Erpeldange'}
check('no-prefusion-ghosts', not (ghosts & names), str(ghosts & names))
check('display-variants', byid['lu:commune:luxembourg-city']['name'] == 'Luxembourg City' and byid['lu:commune:redange-sur-attert']['name'] == 'Redange-sur-Attert')
# canton membership spot pins (CACLR COMMUALL O-rows)
parent_of = {r['source_id']: r['parent_source_id'] for r in communes}
check('parents-spot', parent_of['lu:commune:bous-waldbredimus'] == 'lu:canton:remich'
    and parent_of['lu:commune:groussbus-wal'] == 'lu:canton:redange'
    and parent_of['lu:commune:habscht'] == 'lu:canton:capellen'
    and parent_of['lu:commune:helperknapp'] == 'lu:canton:mersch'
    and parent_of['lu:commune:garnich'] == 'lu:canton:capellen'
    and parent_of['lu:commune:luxembourg-city'] == 'lu:canton:luxembourg')
# --- postal: 4333 codes / 4434 links, sorted, L-NNNN, exactly-one-primary ---
check('codes-4333', len(codes) == 4333, str(len(codes)))
check('links-4434', len(links) == 4434, str(len(links)))
clist = [c['code'] for c in codes]
check('codes-unique', len(set(clist)) == 4333)
check('codes-sorted', clist == sorted(clist))
bad = [c for c in clist if not re.match(r'^L-\d{4}$', c)]
check('code-format-L-NNNN', not bad, str(bad[:3]))
check('codes-country-LU', all(c['country_code'] == 'LU' for c in codes))
check('codes-range', min(clist) == 'L-1110' and max(clist) == 'L-9999', f'{min(clist)}..{max(clist)}')
ll = [l['postcode'] for l in links]
check('links-grouped-sorted', ll == sorted(ll))
dang = [l['postcode'] for l in links if l['postcode'] not in set(clist) or l['area_source_id'] not in byid]
check('no-dangling', not dang, str(dang[:3]))
from collections import defaultdict
per = defaultdict(list)
for l in links: per[l['postcode']].append(l)
noprim = [p for p, v in per.items() if sum(1 for x in v if x['is_primary'] == 'true') != 1]
check('exactly-one-primary', not noprim, str(noprim[:3]))
check('reltypes-served_by', all(l['relationship_type'] == 'served_by' for l in links))
multi = {p: v for p, v in per.items() if len(v) > 1}
check('multi-93', len(multi) == 93, str(len(multi)))
check('all-commune-links', all(byid[l['area_source_id']]['type'] == 'commune' for l in links))
# --- dropped cells absent (10 phantoms + 4 retired + 4008) ---
cset = set(clist)
for pc in ['L-3208','L-3556','L-4006','L-4007','L-4009','L-4100','L-7202','L-8007','L-8302','L-9203','L-3613','L-3923','L-4008','L-4262','L-6721']:
    check(f'dropped-absent-{pc[2:]}', pc not in cset and pc not in per)
# --- filled cells present + linked ---
pin = {}
for l in links:
    pin.setdefault(l['postcode'], []).append((l['area_source_id'], l['is_primary']))
def legs(pc): return sorted(pin.get(pc, []))
check('fill-1110-dual', legs('L-1110') == [('lu:commune:niederanven','false'),('lu:commune:sandweiler','true')], str(legs('L-1110')))
for pc, sid in {'L-1507':'lu:commune:niederanven','L-1614':'lu:commune:hesperange','L-1843':'lu:commune:sandweiler','L-1846':'lu:commune:luxembourg-city','L-2264':'lu:commune:luxembourg-city','L-2618':'lu:commune:luxembourg-city','L-3942':'lu:commune:mondercange','L-4090':'lu:commune:esch-sur-alzette','L-4091':'lu:commune:esch-sur-alzette','L-4092':'lu:commune:esch-sur-alzette','L-4093':'lu:commune:esch-sur-alzette','L-4329':'lu:commune:esch-sur-alzette','L-5827':'lu:commune:hesperange','L-7461':'lu:commune:larochette','L-7611':'lu:commune:larochette','L-7616':'lu:commune:larochette','L-9741':'lu:commune:wincrange'}.items():
    check(f'fill-{pc[2:]}', legs(pc) == [(sid,'true')], str(legs(pc)))
# --- new secondary legs (23; L-7533 Fischbach dropped 2026-10-06, see header) ---
newlegs = {'L-1319':'lu:commune:sandweiler','L-1634':'lu:commune:hesperange','L-1748':'lu:commune:sandweiler','L-2220':'lu:commune:sandweiler','L-2632':'lu:commune:luxembourg-city','L-3260':'lu:commune:roeser','L-4367':'lu:commune:esch-sur-alzette','L-4374':'lu:commune:sanem','L-4499':'lu:commune:sanem','L-4620':'lu:commune:sanem','L-4645':'lu:commune:kaerjeng','L-6240':'lu:commune:bech','L-6360':'lu:commune:waldbillig','L-6688':'lu:commune:grevenmacher','L-7597':'lu:commune:helperknapp','L-7738':'lu:commune:colmar-berg','L-8018':'lu:commune:bertrange','L-9153':'lu:commune:esch-sur-sure','L-9181':'lu:commune:goesdorf','L-9378':'lu:commune:diekirch','L-9659':'lu:commune:goesdorf','L-9696':'lu:commune:wiltz','L-9974':'lu:commune:weiswampach'}
for pc, sid in newlegs.items():
    check(f'leg-{pc[2:]}', (sid,'false') in legs(pc), str(legs(pc)))
check('leg-7533-single-mersch', legs('L-7533') == [('lu:commune:mersch','true')], str(legs('L-7533')))
# triple/quad legs incl. extended 9378 (Bourscheid primary + Parc Hosingen + Tandel + Diekirch)
check('legs-9378-4', legs('L-9378') == [('lu:commune:bourscheid','true'),('lu:commune:diekirch','false'),('lu:commune:parc-hosingen','false'),('lu:commune:tandel','false')], str(legs('L-9378')))
check('legs-4149-3', legs('L-4149') == [('lu:commune:esch-sur-alzette','true'),('lu:commune:mondercange','false'),('lu:commune:schifflange','false')], str(legs('L-4149')))
check('legs-7634-4', legs('L-7634') == [('lu:commune:heffingen','false'),('lu:commune:larochette','false'),('lu:commune:vallee-de-l-ernz','false'),('lu:commune:waldbillig','true')], str(legs('L-7634')))
# --- hold pins: must stay absent ---
for pc in ['L-1010','L-2010','L-3205','L-4701','L-8305','L-9204',  # live B-type CEDEX, out of scope
           'L-1360','L-4056','L-4659','L-6487','L-7614','L-9093',  # retired-lingering N
           'L-1009','L-2990','L-3226','L-8300','L-9951',          # reserved N
           'L-7300','L-5845']:                                    # dormant provisional / fully retired
    check(f'hold-absent-{pc[2:]}', pc not in cset and pc not in per)
# --- remap + anchor pins ---
check('pin-remap-helperknapp', legs('L-7415') == [('lu:commune:helperknapp','true'),('lu:commune:mersch','false')], str(legs('L-7415')))
check('pin-remap-groussbus', [x for x in legs('L-9144') if x[1]=='true'] == [('lu:commune:groussbus-wal','true')], str(legs('L-9144')))
check('pin-remap-bous', [x for x in legs('L-5421') if x[1]=='true'] == [('lu:commune:bous-waldbredimus','true')], str(legs('L-5421')))
check('pin-4906-kaerjeng-B', legs('L-4906') == [('lu:commune:kaerjeng','true')], str(legs('L-4906')))
check('pin-8060-bertrange-bldg', legs('L-8060') == [('lu:commune:bertrange','true')], str(legs('L-8060')))
check('pin-9281-diekirch-bldg', legs('L-9281') == [('lu:commune:diekirch','true')], str(legs('L-9281')))
check('pin-9185-ringel', [x for x in legs('L-9185') if x[1]=='true'] == [('lu:commune:esch-sur-sure','true')], str(legs('L-9185')))
# --- per-commune link counts, full 100-row table (post-fix) ---
exp_counts = {
 'lu:commune:beaufort': 9,
 'lu:commune:bech': 9,
 'lu:commune:beckerich': 12,
 'lu:commune:berdorf': 9,
 'lu:commune:bertrange': 52,
 'lu:commune:bettembourg': 81,
 'lu:commune:bettendorf': 17,
 'lu:commune:betzdorf': 21,
 'lu:commune:bissen': 36,
 'lu:commune:biwer': 13,
 'lu:commune:boulaide': 4,
 'lu:commune:bourscheid': 11,
 'lu:commune:bous-waldbredimus': 11,
 'lu:commune:clervaux': 35,
 'lu:commune:colmar-berg': 27,
 'lu:commune:consdorf': 12,
 'lu:commune:contern': 28,
 'lu:commune:dalheim': 14,
 'lu:commune:diekirch': 85,
 'lu:commune:differdange': 177,
 'lu:commune:dippach': 15,
 'lu:commune:dudelange': 157,
 'lu:commune:echternach': 82,
 'lu:commune:ell': 7,
 'lu:commune:erpeldange-sur-sure': 11,
 'lu:commune:esch-sur-alzette': 272,
 'lu:commune:esch-sur-sure': 15,
 'lu:commune:ettelbruck': 74,
 'lu:commune:feulen': 8,
 'lu:commune:fischbach': 5,
 'lu:commune:flaxweiler': 8,
 'lu:commune:frisange': 27,
 'lu:commune:garnich': 9,
 'lu:commune:goesdorf': 9,
 'lu:commune:grevenmacher': 73,
 'lu:commune:groussbus-wal': 10,
 'lu:commune:habscht': 32,
 'lu:commune:heffingen': 9,
 'lu:commune:helperknapp': 18,
 'lu:commune:hesperange': 148,
 'lu:commune:junglinster': 64,
 'lu:commune:kaerjeng': 73,
 'lu:commune:kayl': 68,
 'lu:commune:kehlen': 47,
 'lu:commune:kiischpelt': 7,
 'lu:commune:koerich': 12,
 'lu:commune:kopstal': 55,
 'lu:commune:lac-de-la-haute-sure': 10,
 'lu:commune:larochette': 22,
 'lu:commune:lenningen': 8,
 'lu:commune:leudelange': 33,
 'lu:commune:lintgen': 22,
 'lu:commune:lorentzweiler': 36,
 'lu:commune:luxembourg-city': 792,
 'lu:commune:mamer': 91,
 'lu:commune:manternach': 6,
 'lu:commune:mersch': 80,
 'lu:commune:mertert': 55,
 'lu:commune:mertzig': 5,
 'lu:commune:mondercange': 58,
 'lu:commune:mondorf-les-bains': 42,
 'lu:commune:niederanven': 65,
 'lu:commune:nommern': 5,
 'lu:commune:parc-hosingen': 21,
 'lu:commune:petange': 135,
 'lu:commune:preizerdaul': 8,
 'lu:commune:putscheid': 8,
 'lu:commune:rambrouch': 22,
 'lu:commune:reckange-sur-mess': 10,
 'lu:commune:redange-sur-attert': 12,
 'lu:commune:reisdorf': 6,
 'lu:commune:remich': 48,
 'lu:commune:roeser': 26,
 'lu:commune:rosport-mompach': 25,
 'lu:commune:rumelange': 42,
 'lu:commune:saeul': 6,
 'lu:commune:sandweiler': 42,
 'lu:commune:sanem': 120,
 'lu:commune:schengen': 18,
 'lu:commune:schieren': 26,
 'lu:commune:schifflange': 65,
 'lu:commune:schuttrange': 26,
 'lu:commune:stadtbredimus': 6,
 'lu:commune:steinfort': 44,
 'lu:commune:steinsel': 48,
 'lu:commune:strassen': 68,
 'lu:commune:tandel': 15,
 'lu:commune:troisvierges': 18,
 'lu:commune:useldange': 7,
 'lu:commune:vallee-de-l-ernz': 14,
 'lu:commune:vianden': 23,
 'lu:commune:vichten': 4,
 'lu:commune:waldbillig': 10,
 'lu:commune:walferdange': 63,
 'lu:commune:weiler-la-tour': 12,
 'lu:commune:weiswampach': 14,
 'lu:commune:wiltz': 62,
 'lu:commune:wincrange': 29,
 'lu:commune:winseler': 6,
 'lu:commune:wormeldange': 17,
}
have = Counter(l['area_source_id'] for l in links)
wrong = [(sid, have.get(sid, 0), n) for sid, n in exp_counts.items() if have.get(sid, 0) != n]
check('per-commune-counts-100', not wrong, str(wrong[:4]))
check('covered-100', sum(1 for sid in exp_counts if have.get(sid, 0) > 0) == 100)
print('ALL PASS' if not fails else f'{len(fails)} FAILURES')
sys.exit(1 if fails else 0)
